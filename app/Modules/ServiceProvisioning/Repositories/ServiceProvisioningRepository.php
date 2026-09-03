<?php

namespace App\Modules\ServiceProvisioning\Repositories;

use App\Infrastructure\Database\DatabaseConnection;
use App\Infrastructure\Security\SecretCipher;
use PDO;

class ServiceProvisioningRepository
{
    private PDO $db;

    public function __construct(DatabaseConnection $connection, private SecretCipher $secrets)
    {
        $this->db = $connection->get();
    }

    public function getPdo(): PDO
    {
        return $this->db;
    }

    public function transaction(callable $callback): mixed
    {
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $result = $callback();
            if ($ownsTransaction) {
                $this->db->commit();
            }
            return $result;
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function acquireLock(string $name, int $timeoutSeconds = 15): void
    {
        $stmt = $this->db->prepare('SELECT GET_LOCK(?, ?)');
        $stmt->execute([$name, max(0, $timeoutSeconds)]);
        if ((int)$stmt->fetchColumn() !== 1) {
            throw new \RuntimeException('Provisioning resources are busy. Please retry shortly.');
        }
    }

    public function releaseLock(string $name): void
    {
        $stmt = $this->db->prepare('SELECT RELEASE_LOCK(?)');
        $stmt->execute([$name]);
    }

    public function paginateJobs(int $page = 1, int $limit = 20, string $search = '', string $status = ''): array
    {
        $page = max(1, $page);
        $limit = max(1, $limit);
        $offset = ($page - 1) * $limit;

        $where = [];
        $params = [];

        if ($search !== '') {
            $where[] = "(
                spj.job_no LIKE :search
                OR spj.ont_serial LIKE :search
                OR s.full_name LIKE :search
                OR ss.ppp_username LIKE :search
                OR ss.service_number LIKE :search
            )";
            $params['search'] = '%' . $search . '%';
        }

        if ($status !== '') {
            $where[] = "spj.job_status = :status";
            $params['status'] = $status;
        }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $countStmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM service_provisioning_jobs spj
            LEFT JOIN subscribers s ON s.id = spj.subscriber_id
            LEFT JOIN subscriber_services ss ON ss.id = spj.service_id
            {$whereSql}
        ");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $stmt = $this->db->prepare("
            SELECT
                spj.*,
                s.full_name AS subscriber_name,
                ss.ppp_username,
                ss.service_number,
                ss.status AS service_status,
                p.plan_name,
                od.name AS olt_name,
                od.ip_address AS olt_ip_address,
                CONCAT_WS('/', op.frame, op.slot, op.port) AS olt_port_label,
                nb.box_name AS network_box_name
            FROM service_provisioning_jobs spj
            LEFT JOIN subscribers s ON s.id = spj.subscriber_id
            LEFT JOIN subscriber_services ss ON ss.id = spj.service_id
            LEFT JOIN plans p ON p.id = spj.plan_id
            LEFT JOIN olt_devices od ON od.id = spj.olt_id
            LEFT JOIN olt_ports op ON op.id = spj.olt_port_id
            LEFT JOIN network_boxes nb ON nb.id = spj.network_box_id
            {$whereSql}
            ORDER BY spj.id DESC
            LIMIT {$limit} OFFSET {$offset}
        ");
        $stmt->execute($params);

        return [
            'items' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => max(1, (int)ceil($total / $limit)),
            ],
        ];
    }

    public function findJobById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                spj.*,
                s.full_name AS subscriber_name,
                s.account_number AS subscriber_account_number,
                s.contact_number AS subscriber_contact_number,
                s.status AS subscriber_status,

                ss.ppp_username,
                ss.service_number,
                ss.status AS service_status,
                ss.account_type,
                ss.next_due_date,
                ss.expires_at,

                p.plan_name,
                p.price AS plan_price,
                p.plan_type,
                p.speed_down,
                p.speed_up,
                p.speed_mbps,

                od.name AS olt_name,
                od.ip_address AS olt_ip_address,
                od.username AS olt_username,
                od.vendor AS olt_vendor,

                CONCAT_WS('/', op.frame, op.slot, op.port) AS olt_port_label,
                op.frame AS olt_frame,
                op.slot AS olt_slot,
                op.port AS olt_port_no,
                op.svlan AS olt_port_svlan,
                op.link_status AS olt_port_link_status,
                op.board_name AS olt_board_name,
                op.board_type AS olt_board_type,
                op.port_type AS olt_port_type,
                op.active_state AS olt_active_state,
                op.ont_count AS olt_reported_ont_count,
                op.ont_online AS olt_reported_ont_online,

                nb.box_name AS network_box_name,
                nb.box_code AS network_box_code,
                nb.location AS network_box_location,
                nb.latitude AS network_box_latitude,
                nb.longitude AS network_box_longitude,
                nb.status AS network_box_status,

                bs.splitter_model,
                bs.splitter_ratio,
                bs.status AS splitter_status,

                sop.port_number AS splitter_output_port_number,
                sop.status AS splitter_output_port_status,
                sop.reserved_label AS splitter_output_port_reserved_label,

                ont.serial_number AS inventory_ont_serial,
                ont.model AS inventory_ont_model,
                ont.vendor AS inventory_ont_vendor,
                ont.status AS inventory_ont_status,
                ont.frame AS inventory_ont_frame,
                ont.slot AS inventory_ont_slot,
                ont.port AS inventory_ont_port,
                ont.ont_id AS inventory_ont_assigned_id,

                spb.id AS binding_id,
                spb.cvlan_network_vlan_id AS binding_cvlan_network_vlan_id,
                spb.cvlan AS binding_cvlan,
                spb.svlan AS binding_svlan,
                spb.ont_assigned_id AS binding_ont_assigned_id,
                spb.pppoe_service_port AS binding_pppoe_service_port,
                spb.tr069_service_port AS binding_tr069_service_port,
                spb.lineprofile_id,
                spb.srvprofile_id,
                spb.tr069_profile_id,
                spb.internet_wan_profile_id,
                spb.tr069_wan_profile_id,
                spb.assigned_at,
                spb.installed_at,
                spb.activated_at,

                oa.id AS acs_id,
                oa.serial_number AS acs_serial_number,
                oa.wan_ip AS acs_wan_ip,
                oa.status AS acs_status,
                oa.last_seen AS acs_last_seen,
                oa.firmware_version AS acs_firmware_version,
                oa.uptime AS acs_uptime

            FROM service_provisioning_jobs spj
            LEFT JOIN subscribers s ON s.id = spj.subscriber_id
            LEFT JOIN subscriber_services ss ON ss.id = spj.service_id
            LEFT JOIN plans p ON p.id = spj.plan_id
            LEFT JOIN olt_devices od ON od.id = spj.olt_id
            LEFT JOIN olt_ports op ON op.id = spj.olt_port_id
            LEFT JOIN network_boxes nb ON nb.id = spj.network_box_id
            LEFT JOIN box_splitters bs ON bs.id = spj.splitter_id
            LEFT JOIN splitter_output_ports sop ON sop.id = spj.splitter_output_port_id
            LEFT JOIN ont_devices ont ON ont.id = spj.ont_id
            LEFT JOIN service_provisioning_bindings spb ON spb.service_id = spj.service_id
            LEFT JOIN ont_acs oa ON UPPER(oa.serial_number) = UPPER(spj.ont_serial)
            WHERE spj.id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function subscriberExists(int $subscriberId): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM subscribers WHERE id = ?");
        $stmt->execute([$subscriberId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function planExists(int $planId): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM plans WHERE id = ?");
        $stmt->execute([$planId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function getPlanById(int $planId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM plans
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$planId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function serviceExists(int $serviceId): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM subscriber_services WHERE id = ?");
        $stmt->execute([$serviceId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function serviceBelongsToSubscriber(int $serviceId, int $subscriberId): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM subscriber_services
            WHERE id = ? AND subscriber_id = ?
        ");
        $stmt->execute([$serviceId, $subscriberId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function findSubscriberServiceBySubscriberAndPlan(int $subscriberId, int $planId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM subscriber_services
            WHERE subscriber_id = ?
              AND plan_id = ?
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([$subscriberId, $planId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function createSubscriberServiceForProvisioning(int $subscriberId, int $planId, array $plan = []): int
    {
        $serviceNumber = 'SVC-' . date('YmdHis') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        $pppUsername = 'sub' . $subscriberId . '@nexusbox';
        $pppPassword = strtoupper(substr(bin2hex(random_bytes(6)), 0, 12));

        $stmt = $this->db->prepare("
            INSERT INTO subscriber_services (
                subscriber_id,
                plan_id,
                service_number,
                ppp_username,
                ppp_password,
                status,
                account_type,
                created_at,
                updated_at
            ) VALUES (
                :subscriber_id,
                :plan_id,
                :service_number,
                :ppp_username,
                :ppp_password,
                :status,
                :account_type,
                NOW(),
                NOW()
            )
        ");

        $stmt->execute([
            'subscriber_id' => $subscriberId,
            'plan_id' => $planId,
            'service_number' => $serviceNumber,
            'ppp_username' => $pppUsername,
            'ppp_password' => $pppPassword,
            'status' => 'PENDING',
            'account_type' => $plan['plan_type'] ?? 'PREPAID',
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function ontExists(int $ontId): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM ont_devices WHERE id = ?");
        $stmt->execute([$ontId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function isOntKnown(int $ontId): bool
    {
        $stmt = $this->db->prepare("
            SELECT id
            FROM ont_devices
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$ontId]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function oltExists(int $oltId): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM olt_devices WHERE id = ?");
        $stmt->execute([$oltId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function networkBoxExists(int $networkBoxId): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM network_boxes WHERE id = ?");
        $stmt->execute([$networkBoxId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function splitterExists(int $splitterId): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM box_splitters
            WHERE id = ?
              AND deleted_at IS NULL
        ");
        $stmt->execute([$splitterId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function splitterOutputPortExists(int $portId): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM splitter_output_ports
            WHERE id = ?
              AND deleted_at IS NULL
        ");
        $stmt->execute([$portId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function findAvailableCvlanForOlt(int $oltId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                nv.id,
                nv.olt_id,
                nv.olt_port_id,
                nv.vlan_id,
                nv.vlan_type,
                nv.name,
                nv.description,
                nv.deployment_status,
                nv.deployed_at
            FROM network_vlans nv
            LEFT JOIN service_provisioning_bindings spb
                ON spb.cvlan_network_vlan_id = nv.id
            WHERE nv.olt_id = :olt_id
              AND nv.vlan_type = 'C_VLAN'
              AND nv.deployment_status = 'DEPLOYED'
              AND spb.cvlan_network_vlan_id IS NULL
            ORDER BY nv.vlan_id ASC
            LIMIT 1
        ");

        $stmt->execute(['olt_id' => $oltId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findDeployedSvlanForOltPort(int $oltId, int $oltPortId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                nv.id,
                nv.olt_id,
                nv.olt_port_id,
                nv.vlan_id,
                nv.vlan_type,
                nv.name,
                nv.description,
                nv.deployment_status,
                nv.deployed_at
            FROM network_vlans nv
            WHERE nv.olt_id = :olt_id
              AND nv.olt_port_id = :olt_port_id
              AND nv.vlan_type = 'S_VLAN'
              AND nv.deployment_status = 'DEPLOYED'
            ORDER BY nv.vlan_id ASC
            LIMIT 1
        ");

        $stmt->execute([
            'olt_id' => $oltId,
            'olt_port_id' => $oltPortId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findMgmtVlanForOlt(int $oltId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, olt_id, mgmt_vlan, description, created_at
            FROM olt_mgmt_vlans
            WHERE olt_id = :olt_id
            ORDER BY id ASC
            LIMIT 1
        ");

        $stmt->execute(['olt_id' => $oltId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findLineProfileByCvlan(int $oltId, int $cvlan): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM olt_ont_line_profiles
            WHERE olt_id = :olt_id
              AND customer_cvlan = :cvlan
            LIMIT 1
        ");

        $stmt->execute([
            'olt_id' => $oltId,
            'cvlan' => $cvlan,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findDefaultSrvProfile(int $oltId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM olt_srv_profiles
            WHERE olt_id = :olt_id
            ORDER BY id ASC
            LIMIT 1
        ");

        $stmt->execute(['olt_id' => $oltId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findDefaultTr069Profile(int $oltId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM olt_tr069_profiles
            WHERE olt_id = :olt_id
            ORDER BY id ASC
            LIMIT 1
        ");

        $stmt->execute(['olt_id' => $oltId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findWanProfileByType(int $oltId, string $connectionType): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM olt_wan_profiles
            WHERE olt_id = :olt_id
              AND connection_type = :connection_type
            ORDER BY id ASC
            LIMIT 1
        ");

        $stmt->execute([
            'olt_id' => $oltId,
            'connection_type' => strtoupper($connectionType),
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findNetworkVlanByNumber(int $vlanNumber, string $vlanType = 'C_VLAN'): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM network_vlans
            WHERE vlan_id = ? AND vlan_type = ?
            LIMIT 1
        ");
        $stmt->execute([$vlanNumber, $vlanType]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function markVlanUsed(int $networkVlanId, ?int $serviceId = null, ?int $oltPortId = null): void
    {
        // VLAN usage is enforced by service_provisioning_bindings.cvlan_network_vlan_id.
    }

    public function getOltPort(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT p.*, COALESCE(p.ont_count, 0) AS reported_ont_count
            FROM olt_ports p
            WHERE p.id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getActiveBindingCountForOltPort(int $oltPortId): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM service_provisioning_bindings
            WHERE olt_port_id = ?
              AND activated_at IS NOT NULL
        ");
        $stmt->execute([$oltPortId]);

        return (int)$stmt->fetchColumn();
    }

    public function getOltById(int $oltId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM olt_devices WHERE id = ? LIMIT 1");
        $stmt->execute([$oltId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $row['password'] = $this->secrets->decrypt($row['password'] ?? null);
        }
        return $row ?: null;
    }

    public function getOntById(int $ontId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM ont_devices WHERE id = ? LIMIT 1");
        $stmt->execute([$ontId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getServiceById(int $serviceId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM subscriber_services WHERE id = ? LIMIT 1");
        $stmt->execute([$serviceId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getServicePlanId(int $serviceId): ?int
    {
        $stmt = $this->db->prepare("
            SELECT plan_id
            FROM subscriber_services
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$serviceId]);

        $value = $stmt->fetchColumn();
        return $value !== false && $value !== null ? (int)$value : null;
    }

    public function findBindingByServiceId(int $serviceId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM service_provisioning_bindings
            WHERE service_id = ?
            LIMIT 1
        ");
        $stmt->execute([$serviceId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function createJob(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO service_provisioning_jobs (
                job_no, subscriber_id, service_id, plan_id, ont_id, ont_serial,
                olt_id, olt_port_id, network_box_id, splitter_id, splitter_output_port_id,
                frame, slot, port, ont_assigned_id, global_id, cvlan, svlan,
                pppoe_service_port, tr069_service_port, lineprofile_id, srvprofile_id,
                tr069_profile_id, internet_wan_profile_id, tr069_wan_profile_id,
                provision_mode, job_status, current_stage, error_message,
                request_payload, result_payload, created_by, started_at, completed_at
            ) VALUES (
                :job_no, :subscriber_id, :service_id, :plan_id, :ont_id, :ont_serial,
                :olt_id, :olt_port_id, :network_box_id, :splitter_id, :splitter_output_port_id,
                :frame, :slot, :port, :ont_assigned_id, :global_id, :cvlan, :svlan,
                :pppoe_service_port, :tr069_service_port, :lineprofile_id, :srvprofile_id,
                :tr069_profile_id, :internet_wan_profile_id, :tr069_wan_profile_id,
                :provision_mode, :job_status, :current_stage, :error_message,
                :request_payload, :result_payload, :created_by, :started_at, :completed_at
            )
        ");

        $stmt->execute([
            'job_no' => $data['job_no'],
            'subscriber_id' => $data['subscriber_id'],
            'service_id' => $data['service_id'],
            'plan_id' => $data['plan_id'],
            'ont_id' => $data['ont_id'],
            'ont_serial' => $data['ont_serial'],
            'olt_id' => $data['olt_id'],
            'olt_port_id' => $data['olt_port_id'],
            'network_box_id' => $data['network_box_id'],
            'splitter_id' => $data['splitter_id'],
            'splitter_output_port_id' => $data['splitter_output_port_id'],
            'frame' => $data['frame'],
            'slot' => $data['slot'],
            'port' => $data['port'],
            'ont_assigned_id' => $data['ont_assigned_id'],
            'global_id' => $data['global_id'],
            'cvlan' => $data['cvlan'],
            'svlan' => $data['svlan'],
            'pppoe_service_port' => $data['pppoe_service_port'],
            'tr069_service_port' => $data['tr069_service_port'],
            'lineprofile_id' => $data['lineprofile_id'],
            'srvprofile_id' => $data['srvprofile_id'],
            'tr069_profile_id' => $data['tr069_profile_id'],
            'internet_wan_profile_id' => $data['internet_wan_profile_id'],
            'tr069_wan_profile_id' => $data['tr069_wan_profile_id'],
            'provision_mode' => $data['provision_mode'],
            'job_status' => $data['job_status'],
            'current_stage' => $data['current_stage'],
            'error_message' => $data['error_message'],
            'request_payload' => $data['request_payload'],
            'result_payload' => $data['result_payload'],
            'created_by' => $data['created_by'],
            'started_at' => $data['started_at'],
            'completed_at' => $data['completed_at'],
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function updateJobExecutionState(
        int $jobId,
        string $jobStatus,
        ?string $currentStage = null,
        ?string $errorMessage = null,
        bool $setStartedAt = false,
        bool $setCompletedAt = false,
        ?string $resultPayload = null
    ): void {
        $sql = "
            UPDATE service_provisioning_jobs
            SET
                job_status = :job_status,
                current_stage = :current_stage,
                error_message = :error_message,
                result_payload = COALESCE(:result_payload, result_payload)
        ";

        if ($setStartedAt) {
            $sql .= ", started_at = NOW()";
        }

        if ($setCompletedAt) {
            $sql .= ", completed_at = NOW()";
        }

        $sql .= " WHERE id = :id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'job_status' => $jobStatus,
            'current_stage' => $currentStage,
            'error_message' => $errorMessage,
            'result_payload' => $resultPayload,
            'id' => $jobId,
        ]);
    }

    public function updateJobProvisioningResult(
        int $jobId,
        ?int $ontAssignedId,
        ?int $pppoeServicePort,
        ?int $tr069ServicePort,
        ?int $globalId = null
    ): void {
        $stmt = $this->db->prepare("
            UPDATE service_provisioning_jobs
            SET
                ont_assigned_id = :ont_assigned_id,
                pppoe_service_port = :pppoe_service_port,
                tr069_service_port = :tr069_service_port,
                global_id = :global_id
            WHERE id = :id
        ");

        $stmt->execute([
            'ont_assigned_id' => $ontAssignedId,
            'pppoe_service_port' => $pppoeServicePort,
            'tr069_service_port' => $tr069ServicePort,
            'global_id' => $globalId,
            'id' => $jobId,
        ]);
    }

    public function addJobLog(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO service_provisioning_job_logs (
                job_id, stage, action, status, message, payload_json, created_by
            ) VALUES (
                :job_id, :stage, :action, :status, :message, :payload_json, :created_by
            )
        ");

        $stmt->execute([
            'job_id' => $data['job_id'],
            'stage' => $data['stage'],
            'action' => $data['action'],
            'status' => $data['status'],
            'message' => $data['message'],
            'payload_json' => $data['payload_json'],
            'created_by' => $data['created_by'],
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function getJobLogs(int $jobId): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM service_provisioning_job_logs
            WHERE job_id = ?
            ORDER BY id ASC
        ");
        $stmt->execute([$jobId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function upsertProvisioningBinding(array $data): void
    {
        $existing = $this->findBindingByServiceId((int)$data['service_id']);

        if ($existing) {
            $stmt = $this->db->prepare("
                UPDATE service_provisioning_bindings
                SET
                    ont_id = :ont_id,
                    ont_serial = :ont_serial,
                    olt_id = :olt_id,
                    olt_port_id = :olt_port_id,
                    network_box_id = :network_box_id,
                    splitter_id = :splitter_id,
                    splitter_output_port_id = :splitter_output_port_id,
                    parent_box_id = :parent_box_id,
                    cvlan_network_vlan_id = :cvlan_network_vlan_id,
                    cvlan = :cvlan,
                    svlan = :svlan,
                    ont_assigned_id = :ont_assigned_id,
                    global_id = :global_id,
                    pppoe_service_port = :pppoe_service_port,
                    tr069_service_port = :tr069_service_port,
                    lineprofile_id = :lineprofile_id,
                    srvprofile_id = :srvprofile_id,
                    tr069_profile_id = :tr069_profile_id,
                    internet_wan_profile_id = :internet_wan_profile_id,
                    tr069_wan_profile_id = :tr069_wan_profile_id,
                    assigned_at = :assigned_at,
                    installed_at = :installed_at,
                    activated_at = :activated_at
                WHERE service_id = :service_id
            ");
        } else {
            $stmt = $this->db->prepare("
                INSERT INTO service_provisioning_bindings (
                    service_id, ont_id, ont_serial, olt_id, olt_port_id,
                    network_box_id, splitter_id, splitter_output_port_id, parent_box_id,
                    cvlan_network_vlan_id, cvlan, svlan, ont_assigned_id, global_id,
                    pppoe_service_port, tr069_service_port, lineprofile_id, srvprofile_id,
                    tr069_profile_id, internet_wan_profile_id, tr069_wan_profile_id,
                    assigned_at, installed_at, activated_at
                ) VALUES (
                    :service_id, :ont_id, :ont_serial, :olt_id, :olt_port_id,
                    :network_box_id, :splitter_id, :splitter_output_port_id, :parent_box_id,
                    :cvlan_network_vlan_id, :cvlan, :svlan, :ont_assigned_id, :global_id,
                    :pppoe_service_port, :tr069_service_port, :lineprofile_id, :srvprofile_id,
                    :tr069_profile_id, :internet_wan_profile_id, :tr069_wan_profile_id,
                    :assigned_at, :installed_at, :activated_at
                )
            ");
        }

        $stmt->execute([
            'service_id' => $data['service_id'],
            'ont_id' => $data['ont_id'],
            'ont_serial' => $data['ont_serial'],
            'olt_id' => $data['olt_id'],
            'olt_port_id' => $data['olt_port_id'],
            'network_box_id' => $data['network_box_id'],
            'splitter_id' => $data['splitter_id'],
            'splitter_output_port_id' => $data['splitter_output_port_id'],
            'parent_box_id' => $data['parent_box_id'],
            'cvlan_network_vlan_id' => $data['cvlan_network_vlan_id'] ?? null,
            'cvlan' => $data['cvlan'],
            'svlan' => $data['svlan'],
            'ont_assigned_id' => $data['ont_assigned_id'],
            'global_id' => $data['global_id'],
            'pppoe_service_port' => $data['pppoe_service_port'],
            'tr069_service_port' => $data['tr069_service_port'],
            'lineprofile_id' => $data['lineprofile_id'],
            'srvprofile_id' => $data['srvprofile_id'],
            'tr069_profile_id' => $data['tr069_profile_id'],
            'internet_wan_profile_id' => $data['internet_wan_profile_id'],
            'tr069_wan_profile_id' => $data['tr069_wan_profile_id'],
            'assigned_at' => $data['assigned_at'],
            'installed_at' => $data['installed_at'],
            'activated_at' => $data['activated_at'],
        ]);
    }

    public function findAcsOntBySerial(string $serial): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM ont_acs
            WHERE UPPER(serial_number) = UPPER(?)
            LIMIT 1
        ");
        $stmt->execute([$serial]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function activateProvisioningBinding(int $serviceId, string $activatedAt): void
    {
        $stmt = $this->db->prepare("
            UPDATE service_provisioning_bindings
            SET activated_at = ?
            WHERE service_id = ?
        ");
        $stmt->execute([$activatedAt, $serviceId]);
    }

    public function updateServiceStatus(int $serviceId, string $status): void
    {
        $stmt = $this->db->prepare("
            UPDATE subscriber_services
            SET status = ?
            WHERE id = ?
        ");
        $stmt->execute([$status, $serviceId]);
    }

    public function assignOntToSubscriber(int $ontId, int $subscriberId): void
    {
        $stmt = $this->db->prepare("
            UPDATE ont_devices
            SET status = 'ASSIGNED', subscriber_id = ?
            WHERE id = ?
              AND (subscriber_id IS NULL OR subscriber_id = ?)
        ");
        $stmt->execute([$subscriberId, $ontId, $subscriberId]);

        if ($stmt->rowCount() !== 1) {
            $check = $this->db->prepare("
                SELECT subscriber_id, status
                FROM ont_devices
                WHERE id = ?
                LIMIT 1
            ");
            $check->execute([$ontId]);
            $current = $check->fetch(PDO::FETCH_ASSOC);
            if (!$current
                || (int)($current['subscriber_id'] ?? 0) !== $subscriberId
                || strtoupper((string)($current['status'] ?? '')) !== 'ASSIGNED') {
                throw new \RuntimeException('ONT inventory assignment failed or the ONT belongs to another subscriber.');
            }
        }
    }

    public function getSupportSubscribers(string $search = ''): array
    {
        $sql = "
            SELECT id, full_name, account_number, contact_number, status
            FROM subscribers
        ";

        $params = [];

        if ($search !== '') {
            $sql .= " WHERE full_name LIKE :search OR account_number LIKE :search";
            $params['search'] = '%' . $search . '%';
        }

        $sql .= " ORDER BY full_name ASC LIMIT 200";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getSupportPlans(string $search = '', int $subscriberId = 0): array
    {
        $sql = "
            SELECT
                p.id,
                p.id AS plan_id,
                existing_service.id AS service_id,
                existing_service.subscriber_id,
                existing_service.ppp_username,
                existing_service.status AS service_status,
                existing_service.service_number,
                p.plan_name,
                p.price,
                p.plan_type,
                p.speed_down,
                p.speed_up,
                p.speed_mbps
            FROM plans p
            LEFT JOIN subscriber_services existing_service
                ON existing_service.plan_id = p.id
               AND existing_service.subscriber_id = :subscriber_id
            WHERE 1 = 1
        ";

        $params = [
            'subscriber_id' => $subscriberId,
        ];

        if ($search !== '') {
            $sql .= "
                AND (
                    p.plan_name LIKE :search
                    OR p.plan_type LIKE :search
                )
            ";
            $params['search'] = '%' . $search . '%';
        }

        $sql .= "
            GROUP BY p.id
            ORDER BY p.plan_name ASC
            LIMIT 500
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getSupportOlts(string $search = ''): array
    {
        $sql = "
            SELECT id, name, ip_address, vendor
            FROM olt_devices
        ";

        $params = [];

        if ($search !== '') {
            $sql .= " WHERE name LIKE :search OR ip_address LIKE :search";
            $params['search'] = '%' . $search . '%';
        }

        $sql .= " ORDER BY name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getSupportOltPorts(int $oltId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                id,
                olt_id,
                frame,
                slot,
                port,
                board_name,
                board_type,
                port_type,
                link_status,
                active_state,
                COALESCE(ont_count, 0) AS ont_count,
                ont_online,
                svlan,
                CONCAT_WS('/', frame, slot, port) AS label
            FROM olt_ports
            WHERE olt_id = ?
              AND UPPER(COALESCE(port_type, '')) LIKE '%GPON%'
            ORDER BY frame ASC, slot ASC, port ASC
        ");
        $stmt->execute([$oltId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getSupportNetworkBoxes(int $oltId = 0, int $oltPortId = 0, string $boxType = 'NAP'): array
    {
        $boxType = strtoupper($boxType);

        if ($boxType === 'NAP' && $oltId > 0 && $oltPortId > 0) {
            $stmt = $this->db->prepare("
                WITH RECURSIVE topology_walk AS (
                    SELECT nn.id AS node_id, 0 AS depth
                    FROM network_nodes nn
                    INNER JOIN odf_nodes odf
                        ON nn.reference_table = 'odf_nodes'
                       AND nn.reference_id = odf.id
                    WHERE nn.node_type = 'ODF'
                      AND nn.status = 'ACTIVE'
                      AND odf.olt_id = :olt_id
                      AND odf.olt_port_id = :olt_port_id

                    UNION DISTINCT

                    SELECT
                        CASE
                            WHEN nl.source_node_id = tw.node_id THEN nl.target_node_id
                            ELSE nl.source_node_id
                        END AS node_id,
                        tw.depth + 1 AS depth
                    FROM topology_walk tw
                    INNER JOIN network_links nl
                        ON nl.is_active = 1
                       AND (
                            nl.source_node_id = tw.node_id
                            OR nl.target_node_id = tw.node_id
                       )
                    WHERE tw.depth < 4
                )
                SELECT DISTINCT
                    nb.id,
                    nb.box_type,
                    nb.box_code,
                    nb.box_name,
                    nb.olt_id,
                    nb.olt_port_id,
                    nb.status,
                    nb.location,
                    nb.latitude,
                    nb.longitude
                FROM topology_walk tw
                INNER JOIN network_nodes nn
                    ON nn.id = tw.node_id
                   AND nn.node_type = 'NAP'
                   AND nn.status = 'ACTIVE'
                   AND nn.reference_table = 'network_boxes'
                INNER JOIN network_boxes nb
                    ON nb.id = nn.reference_id
                   AND nb.box_type = 'NAP'
                   AND nb.deleted_at IS NULL
                   AND nb.status = 'ACTIVE'
                ORDER BY nb.box_name ASC
            ");

            $stmt->execute([
                'olt_id' => $oltId,
                'olt_port_id' => $oltPortId,
            ]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        $sql = "
            SELECT
                id,
                box_type,
                box_code,
                box_name,
                olt_id,
                olt_port_id,
                status,
                location,
                latitude,
                longitude
            FROM network_boxes
            WHERE box_type = :box_type
              AND deleted_at IS NULL
        ";

        $params = ['box_type' => $boxType];

        if ($oltId > 0) {
            $sql .= " AND olt_id = :olt_id";
            $params['olt_id'] = $oltId;
        }

        if ($oltPortId > 0) {
            $sql .= " AND olt_port_id = :olt_port_id";
            $params['olt_port_id'] = $oltPortId;
        }

        $sql .= " ORDER BY box_name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getSupportSplitters(int $networkBoxId): array
    {
        $stmt = $this->db->prepare("
            SELECT id, box_id, splitter_ratio, splitter_model, splitter_role, status
            FROM box_splitters
            WHERE box_id = ?
              AND deleted_at IS NULL
            ORDER BY id ASC
        ");
        $stmt->execute([$networkBoxId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getSupportSplitterOutputPorts(int $splitterId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                id,
                splitter_id,
                port_number,
                status,
                connected_entity_type,
                connected_entity_id,
                service_id,
                reserved_label
            FROM splitter_output_ports
            WHERE splitter_id = ?
              AND deleted_at IS NULL
            ORDER BY port_number ASC
        ");
        $stmt->execute([$splitterId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getSupportOnts(string $search = ''): array
    {
        $sql = "
            SELECT
                od.id,
                od.serial_number,
                od.model,
                od.vendor,
                od.status,
                od.olt_id,
                od.frame,
                od.slot,
                od.port,
                oa.status AS acs_status,
                oa.wan_ip,
                oa.last_seen
            FROM ont_devices od
            LEFT JOIN ont_acs oa ON UPPER(oa.serial_number) = UPPER(od.serial_number)
            WHERE 1 = 1
        ";

        $params = [];

        if ($search !== '') {
            $sql .= "
                AND (
                    od.serial_number LIKE :search
                    OR od.model LIKE :search
                    OR od.vendor LIKE :search
                )
            ";
            $params['search'] = '%' . $search . '%';
        }

        $sql .= " ORDER BY od.serial_number ASC LIMIT 500";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getNextServicePortId(string $type = 'PPPOE'): int
    {
        $base = strtoupper($type) === 'TR069' ? 20000 : 10000;

        $stmt = $this->db->prepare("
            SELECT GREATEST(
                COALESCE(MAX(CASE WHEN :type = 'PPPOE' THEN pppoe_service_port ELSE tr069_service_port END), 0),
                :base
            ) + 1 AS next_port
            FROM service_provisioning_jobs
        ");
        $stmt->execute([
            'type' => strtoupper($type),
            'base' => $base,
        ]);

        return (int)$stmt->fetchColumn();
    }

    public function markSplitterOutputPortUsed(int $id, ?int $serviceId = null, ?string $reservedLabel = null): void
    {
        $stmt = $this->db->prepare("
            UPDATE splitter_output_ports
            SET
                status = 'USED',
                service_id = ?,
                reserved_label = ?
            WHERE id = ?
        ");
        $stmt->execute([$serviceId, $reservedLabel, $id]);
    }

    public function reserveSplitterOutputPort(int $id, int $serviceId): void
    {
        $stmt = $this->db->prepare("
            UPDATE splitter_output_ports
            SET status = 'RESERVED', service_id = ?, reserved_label = 'Provisioning reservation'
            WHERE id = ? AND UPPER(status) = 'AVAILABLE'
        ");
        $stmt->execute([$serviceId, $id]);
        if ($stmt->rowCount() !== 1) {
            throw new \RuntimeException('The selected splitter output port is no longer available.');
        }
    }

    public function releaseSplitterOutputPortReservation(int $id, int $serviceId): void
    {
        $stmt = $this->db->prepare("
            UPDATE splitter_output_ports
            SET status = 'AVAILABLE', service_id = NULL, reserved_label = NULL
            WHERE id = ? AND service_id = ? AND UPPER(status) = 'RESERVED'
        ");
        $stmt->execute([$id, $serviceId]);
    }

    public function deleteUnactivatedBinding(int $serviceId): void
    {
        $stmt = $this->db->prepare("
            DELETE FROM service_provisioning_bindings
            WHERE service_id = ? AND assigned_at IS NULL AND activated_at IS NULL
        ");
        $stmt->execute([$serviceId]);
    }
}
