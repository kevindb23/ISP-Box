<?php

namespace App\Modules\Subscribers\Repositories;

use App\Modules\Radius\Repositories\RadiusSettingsRepository;
use Framework\DatabaseConnection;
use PDO;
use PDOException;
use RuntimeException;

class SubscriberRepository
{
    private PDO $db;
    private ?PDO $radiusDb = null;
    private ?array $radiusConfig;

    public function __construct(DatabaseConnection $database, RadiusSettingsRepository $radiusSettings)
    {
        $this->db = $database->get();

        $this->radiusConfig = $radiusSettings->getOptionalConnectionConfig();
    }

    private function radiusDb(): PDO
    {
        if ($this->radiusDb instanceof PDO) return $this->radiusDb;
        if ($this->radiusConfig === null) {
            throw new RuntimeException('Radius database settings are required for this operation.');
        }
        $radius = $this->radiusConfig;
        return $this->radiusDb = new PDO(
            "mysql:host={$radius['host']};dbname={$radius['name']};charset=utf8mb4",
            $radius['user'],
            $radius['pass'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    }

    public function getActivePlans(): array
    {
        $stmt = $this->db->query("
            SELECT id, plan_name, plan_type, validity_days, speed_mbps, price
            FROM plans
            WHERE is_active = 1
            ORDER BY plan_name ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findPlanById(int $planId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM plans
            WHERE id = ?
              AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$planId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function portalUserExists(string $email): bool
    {
        $email = trim($email);

        if ($email === '') {
            return false;
        }

        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM users
            WHERE username = :email
               OR email = :email
        ");

        $stmt->execute([
            ':email' => $email,
        ]);

        return (int)$stmt->fetchColumn() > 0;
    }

    public function all(string $search = ''): array
    {
        $search = trim($search);

        $baseSelect = "
            SELECT
                s.id,
                s.account_number,
                s.full_name,
                s.address,
                s.contact_number,
                s.email,
                s.status,
                s.created_at,
                s.updated_at,
                s.deleted_at,

                ss.id AS service_id,
                ss.ppp_username,
                ss.plan_id,
                ss.account_type,
                ss.status AS service_status,
                ss.next_due_date,
                ss.expires_at,
                ss.service_number,
                (SELECT COUNT(*) FROM subscriber_services service_count_rows WHERE service_count_rows.subscriber_id = s.id) AS service_count,

                p.plan_name,

                spb.id AS provisioning_id,
                spb.ont_serial,
                spb.installed_at,
                spb.ont_id,
                spb.olt_port_id,
                spb.assigned_at,
                spb.activated_at,
                spb.cvlan,
                spb.svlan,
                od.name AS olt_name,
                od.ip_address AS olt_ip_address,
                CONCAT_WS('/', op.frame, op.slot, op.port) AS olt_port_name,
                ont.ont_id AS ont_assigned_id,
                ont.status AS ont_status,
                nb.box_name AS nap_name,
                nb.box_code AS nap_code,
                bs.splitter_model,
                bs.splitter_ratio,
                sop.port_number AS nap_splitter_port,
                spj.id AS provisioning_job_id,
                spj.job_no AS provisioning_job_no,
                spj.job_status AS provisioning_status,
                spj.created_at AS provisioning_date,
                oa.status AS acs_status,
                oa.wan_ip AS acs_wan_ip,
                oa.last_seen AS acs_last_seen

            FROM subscribers s
            INNER JOIN subscriber_services ss
                ON ss.subscriber_id = s.id
            INNER JOIN plans p
                ON p.id = ss.plan_id
            LEFT JOIN service_provisioning_bindings spb ON spb.service_id = ss.id
            LEFT JOIN olt_devices od ON od.id = spb.olt_id
            LEFT JOIN olt_ports op ON op.id = spb.olt_port_id
            LEFT JOIN ont_devices ont ON ont.id = spb.ont_id
            LEFT JOIN network_boxes nb ON nb.id = spb.network_box_id
            LEFT JOIN box_splitters bs ON bs.id = spb.splitter_id AND bs.deleted_at IS NULL
            LEFT JOIN splitter_output_ports sop ON sop.id = spb.splitter_output_port_id AND sop.deleted_at IS NULL
            LEFT JOIN service_provisioning_jobs spj ON spj.id = (
                SELECT latest_spj.id
                FROM service_provisioning_jobs latest_spj
                WHERE latest_spj.service_id = ss.id
                ORDER BY latest_spj.id DESC
                LIMIT 1
            )
            LEFT JOIN ont_acs oa ON UPPER(oa.serial_number) = UPPER(spb.ont_serial)

            WHERE s.deleted_at IS NULL
              AND ss.plan_id IS NOT NULL
              AND ss.status IS NOT NULL
              AND ss.id = (
                  SELECT preferred_service.id
                  FROM subscriber_services preferred_service
                  WHERE preferred_service.subscriber_id = s.id
                  ORDER BY CASE UPPER(preferred_service.status)
                      WHEN 'ACTIVE' THEN 0 WHEN 'SUSPENDED' THEN 1 WHEN 'PENDING' THEN 2 ELSE 3 END,
                      preferred_service.id DESC
                  LIMIT 1
              )
        ";

        if ($search !== '') {
            $like = '%' . $search . '%';

            $stmt = $this->db->prepare($baseSelect . "
                AND (
                        CAST(s.account_number AS CHAR) LIKE ?
                     OR s.full_name LIKE ?
                     OR s.email LIKE ?
                     OR s.contact_number LIKE ?
                     OR ss.ppp_username LIKE ?
                     OR CAST(ss.service_number AS CHAR) LIKE ?
                )
                ORDER BY s.id DESC
                LIMIT 500
            ");

            $stmt->execute([$like, $like, $like, $like, $like, $like]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } else {
            $stmt = $this->db->query($baseSelect . "
                ORDER BY s.id DESC
                LIMIT 500
            ");

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        if (empty($rows)) {
            return [];
        }

        return $this->attachOnlineState($rows);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                s.id,
                s.account_number,
                s.full_name,
                s.address,
                s.contact_number,
                s.email,
                s.status,
                s.created_at,
                s.updated_at,
                s.deleted_at,

                ss.id AS service_id,
                ss.ppp_username,
                ss.plan_id,
                ss.account_type,
                ss.status AS service_status,
                ss.next_due_date,
                ss.expires_at,
                ss.service_number,
                (SELECT COUNT(*) FROM subscriber_services service_count_rows WHERE service_count_rows.subscriber_id = s.id) AS service_count,

                p.plan_name,

                spb.id AS provisioning_id,
                spb.ont_serial,
                spb.installed_at,
                spb.ont_id,
                spb.olt_port_id,
                spb.assigned_at,
                spb.activated_at,
                spb.cvlan,
                spb.svlan,
                od.name AS olt_name,
                od.ip_address AS olt_ip_address,
                CONCAT_WS('/', op.frame, op.slot, op.port) AS olt_port_name,
                ont.ont_id AS ont_assigned_id,
                ont.status AS ont_status,
                nb.box_name AS nap_name,
                nb.box_code AS nap_code,
                bs.splitter_model,
                bs.splitter_ratio,
                sop.port_number AS nap_splitter_port,
                spj.id AS provisioning_job_id,
                spj.job_no AS provisioning_job_no,
                spj.job_status AS provisioning_status,
                spj.created_at AS provisioning_date,
                oa.status AS acs_status,
                oa.wan_ip AS acs_wan_ip,
                oa.last_seen AS acs_last_seen

            FROM subscribers s
            INNER JOIN subscriber_services ss
                ON ss.subscriber_id = s.id
            INNER JOIN plans p
                ON p.id = ss.plan_id
            LEFT JOIN service_provisioning_bindings spb ON spb.service_id = ss.id
            LEFT JOIN olt_devices od ON od.id = spb.olt_id
            LEFT JOIN olt_ports op ON op.id = spb.olt_port_id
            LEFT JOIN ont_devices ont ON ont.id = spb.ont_id
            LEFT JOIN network_boxes nb ON nb.id = spb.network_box_id
            LEFT JOIN box_splitters bs ON bs.id = spb.splitter_id AND bs.deleted_at IS NULL
            LEFT JOIN splitter_output_ports sop ON sop.id = spb.splitter_output_port_id AND sop.deleted_at IS NULL
            LEFT JOIN service_provisioning_jobs spj ON spj.id = (
                SELECT latest_spj.id
                FROM service_provisioning_jobs latest_spj
                WHERE latest_spj.service_id = ss.id
                ORDER BY latest_spj.id DESC
                LIMIT 1
            )
            LEFT JOIN ont_acs oa ON UPPER(oa.serial_number) = UPPER(spb.ont_serial)

            WHERE s.id = ?
              AND s.deleted_at IS NULL
            ORDER BY CASE UPPER(ss.status)
                WHEN 'ACTIVE' THEN 0 WHEN 'SUSPENDED' THEN 1 WHEN 'PENDING' THEN 2 ELSE 3 END,
                ss.id DESC
            LIMIT 1
        ");
        $stmt->execute([$id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $rows = $this->attachOnlineState([$row]);
        return $rows[0] ?? null;
    }

    public function findPppCredentialContext(int $subscriberId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                s.id AS subscriber_id,
                s.full_name,
                ss.id AS service_id,
                ss.ppp_username,
                ss.ppp_password,
                spb.ont_serial
            FROM subscribers s
            INNER JOIN subscriber_services ss ON ss.subscriber_id = s.id
            LEFT JOIN service_provisioning_bindings spb ON spb.service_id = ss.id
            WHERE s.id = ?
              AND s.deleted_at IS NULL
            ORDER BY CASE UPPER(ss.status)
                WHEN 'ACTIVE' THEN 0 WHEN 'SUSPENDED' THEN 1 WHEN 'PENDING' THEN 2 ELSE 3 END,
                ss.id DESC
            LIMIT 1
        ");
        $stmt->execute([$subscriberId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function sessionStates(string $search = ''): array
    {
        $search = trim($search);

        $baseSelect = "
            SELECT
                s.id,
                ss.ppp_username
            FROM subscribers s
            INNER JOIN subscriber_services ss
                ON ss.subscriber_id = s.id
            INNER JOIN plans p
                ON p.id = ss.plan_id
            WHERE s.deleted_at IS NULL
              AND ss.plan_id IS NOT NULL
              AND ss.status IS NOT NULL
              AND ss.id = (
                  SELECT preferred_session_service.id
                  FROM subscriber_services preferred_session_service
                  WHERE preferred_session_service.subscriber_id = s.id
                  ORDER BY CASE UPPER(preferred_session_service.status)
                      WHEN 'ACTIVE' THEN 0 WHEN 'SUSPENDED' THEN 1 WHEN 'PENDING' THEN 2 ELSE 3 END,
                      preferred_session_service.id DESC
                  LIMIT 1
              )
        ";

        if ($search !== '') {
            $like = '%' . $search . '%';

            $stmt = $this->db->prepare($baseSelect . "
                AND (
                        CAST(s.account_number AS CHAR) LIKE ?
                     OR s.full_name LIKE ?
                     OR s.email LIKE ?
                     OR s.contact_number LIKE ?
                     OR ss.ppp_username LIKE ?
                     OR CAST(ss.service_number AS CHAR) LIKE ?
                )
                ORDER BY s.id DESC
                LIMIT 500
            ");
            $stmt->execute([$like, $like, $like, $like, $like, $like]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } else {
            $stmt = $this->db->query($baseSelect . "
                ORDER BY s.id DESC
                LIMIT 500
            ");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        if (empty($rows)) {
            return [];
        }

        $rows = $this->attachOnlineState($rows);

        return array_map(static function (array $row): array {
            $online = (int)($row['online'] ?? 0);

            return [
                'id' => (int)($row['id'] ?? 0),
                'ppp_username' => (string)($row['ppp_username'] ?? ''),
                'online' => $online,
                'online_text' => $online === 1 ? 'ONLINE' : 'OFFLINE',
            ];
        }, $rows);
    }

    private function attachOnlineState(array $rows): array
    {
        if (empty($rows)) {
            return [];
        }

        $usernames = [];
        foreach ($rows as $row) {
            if (!empty($row['ppp_username'])) {
                $usernames[] = (string)$row['ppp_username'];
            }
        }

        $usernames = array_values(array_unique($usernames));
        $onlineMap = [];

        if (!empty($usernames)) {
            $placeholders = implode(',', array_fill(0, count($usernames), '?'));

            try {
                $stmt = $this->radiusDb()->prepare("
                    SELECT DISTINCT username
                    FROM radacct
                    WHERE acctstoptime IS NULL
                      AND username IN ($placeholders)
                ");
                $stmt->execute($usernames);

                $active = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                foreach ($active as $item) {
                    $username = (string)($item['username'] ?? '');
                    if ($username !== '') {
                        $onlineMap[$username] = 1;
                    }
                }
            } catch (PDOException|RuntimeException $e) {
                // Subscriber records remain available while RADIUS telemetry is unavailable.
                error_log('[SubscriberRepository] RADIUS online-state lookup unavailable: ' . $e->getCode());
            }
        }

        foreach ($rows as &$row) {
            $username = (string)($row['ppp_username'] ?? '');
            $online = (int)($onlineMap[$username] ?? 0);
            $row['online'] = $online;
            $row['online_text'] = $online === 1 ? 'ONLINE' : 'OFFLINE';
        }
        unset($row);

        return $rows;
    }

    public function nextAccountNumber(): int
    {
        $stmt = $this->db->query("SELECT COALESCE(MAX(account_number), 1000000000) AS max_no FROM subscribers");
        $max = (int)($stmt->fetchColumn() ?: 1000000000);

        return $max + 1;
    }

    public function nextServiceNumber(): int
    {
        $stmt = $this->db->query("SELECT COALESCE(MAX(service_number), 2000000000) AS max_no FROM subscriber_services");
        $max = (int)($stmt->fetchColumn() ?: 2000000000);

        return $max + 1;
    }

    public function create(
        array $data,
        array $plan,
        string $pppUsername,
        string $pppPassword,
        string $portalUsername,
        string $portalPassword
    ): array {
        $accountNumber = 0;
        $serviceNumber = 0;
        $numberLockHeld = false;

        $nextDueDate = null;
        $expiresAt = null;

        if (($plan['plan_type'] ?? 'POSTPAID') === 'POSTPAID') {
            $nextDueDate = date('Y-m-d', strtotime('+30 days'));
        } else {
            $expiresAt = date('Y-m-d H:i:s', strtotime('+' . (int)$plan['validity_days'] . ' days'));
        }

        try {
            $this->db->beginTransaction();

            $lockStmt = $this->db->query("SELECT GET_LOCK('nexusbox_subscriber_numbers', 10)");
            $numberLockHeld = (int)$lockStmt->fetchColumn() === 1;

            if (!$numberLockHeld) {
                throw new PDOException('Unable to reserve subscriber numbers.');
            }

            $accountNumber = $this->nextAccountNumber();
            $serviceNumber = $this->nextServiceNumber();
            $pppUsername = sprintf('CST%07d', $serviceNumber);

            /*
            |--------------------------------------------------------------------------
            | Create Subscriber Portal User
            |--------------------------------------------------------------------------
            */

            $stmtUser = $this->db->prepare("
                INSERT INTO users
                (
                    username,
                    password,
                    full_name,
                    email,
                    role,
                    status
                )
                VALUES
                (?, ?, ?, ?, 'SUBSCRIBER', 'ACTIVE')
            ");

            $stmtUser->execute([
                $portalUsername,
                password_hash($portalPassword, PASSWORD_DEFAULT),
                $data['full_name'],
                $portalUsername,
            ]);

            $userId = (int)$this->db->lastInsertId();

            /*
            |--------------------------------------------------------------------------
            | Create Subscriber Profile
            |--------------------------------------------------------------------------
            */

            $stmt1 = $this->db->prepare("
                INSERT INTO subscribers
                (
                    user_id,
                    account_number,
                    full_name,
                    address,
                    contact_number,
                    email,
                    status
                )
                VALUES
                (?, ?, ?, ?, ?, ?, 'ACTIVE')
            ");

            $stmt1->execute([
                $userId,
                $accountNumber,
                $data['full_name'],
                $data['address'],
                $data['contact_number'],
                $data['email'],
            ]);

            $subscriberId = (int)$this->db->lastInsertId();

            /*
            |--------------------------------------------------------------------------
            | Create Subscriber Service
            |--------------------------------------------------------------------------
            */

            $stmt2 = $this->db->prepare("
                INSERT INTO subscriber_services
                (
                    subscriber_id,
                    ppp_username,
                    ppp_password,
                    plan_id,
                    account_type,
                    status,
                    next_due_date,
                    expires_at,
                    service_number
                )
                VALUES
                (?, ?, ?, ?, ?, 'ACTIVE', ?, ?, ?)
            ");

            $stmt2->execute([
                $subscriberId,
                $pppUsername,
                $pppPassword,
                (int)$plan['id'],
                $plan['plan_type'],
                $nextDueDate,
                $expiresAt,
                $serviceNumber,
            ]);

            $this->db->commit();
            $this->db->query("SELECT RELEASE_LOCK('nexusbox_subscriber_numbers')");
            $numberLockHeld = false;

            return [
                'ok' => true,
                'user_id' => $userId,
                'subscriber_id' => $subscriberId,
                'account_number' => $accountNumber,
                'service_number' => $serviceNumber,
                'ppp_username' => $pppUsername,
                'ppp_password' => $pppPassword,
                'portal_username' => $portalUsername,
                'portal_password' => $portalPassword,
                'next_due_date' => $nextDueDate,
                'expires_at' => $expiresAt,
            ];
        } catch (PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            if ($numberLockHeld) {
                $this->db->query("SELECT RELEASE_LOCK('nexusbox_subscriber_numbers')");
            }

            return [
                'ok' => false,
                'message' => 'Portal insert failed.',
            ];
        }
    }

    public function updateProfileAndService(int $id, array $data, array $plan): bool
    {
        $this->db->beginTransaction();

        try {
        $stmt1 = $this->db->prepare("
            UPDATE subscribers
            SET
                full_name = ?,
                address = ?,
                contact_number = ?,
                email = ?
            WHERE id = ?
              AND deleted_at IS NULL
        ");

        $ok1 = $stmt1->execute([
            $data['full_name'],
            $data['address'],
            $data['contact_number'],
            $data['email'],
            $id,
        ]);

        $nextDueDate = null;
        $expiresAt = null;

        if (($plan['plan_type'] ?? 'POSTPAID') === 'POSTPAID') {
            $nextDueDate = date('Y-m-d', strtotime('+30 days'));
        } else {
            $expiresAt = date('Y-m-d H:i:s', strtotime('+' . (int)$plan['validity_days'] . ' days'));
        }

        $stmt2 = $this->db->prepare("
            UPDATE subscriber_services
            SET
                plan_id = ?,
                account_type = ?,
                next_due_date = ?,
                expires_at = ?
            WHERE subscriber_id = ?
        ");

        $ok2 = $stmt2->execute([
            (int)$plan['id'],
            $plan['plan_type'],
            $nextDueDate,
            $expiresAt,
            $id,
        ]);

            if (!$ok1 || !$ok2) {
                throw new PDOException('Unable to update subscriber records.');
            }

            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return false;
        }
    }

    public function restoreProfileAndService(int $id, array $existing): void
    {
        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare("
                UPDATE subscribers
                SET full_name = ?, address = ?, contact_number = ?, email = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $existing['full_name'] ?? '',
                $existing['address'] ?? '',
                $existing['contact_number'] ?? '',
                $existing['email'] ?? '',
                $id,
            ]);

            $stmt = $this->db->prepare("
                UPDATE subscriber_services
                SET plan_id = ?, account_type = ?, next_due_date = ?, expires_at = ?
                WHERE subscriber_id = ?
            ");
            $stmt->execute([
                $existing['plan_id'] ?? null,
                $existing['account_type'] ?? null,
                $existing['next_due_date'] ?? null,
                $existing['expires_at'] ?? null,
                $id,
            ]);

            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function rollbackCreatedSubscriber(int $subscriberId, int $userId): void
    {
        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare('DELETE FROM subscriber_services WHERE subscriber_id = ?');
            $stmt->execute([$subscriberId]);
            $stmt = $this->db->prepare('DELETE FROM subscribers WHERE id = ?');
            $stmt->execute([$subscriberId]);
            $stmt = $this->db->prepare("DELETE FROM users WHERE id = ? AND role = 'SUBSCRIBER'");
            $stmt->execute([$userId]);
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function updateSubscriberStatus(int $id, string $status): bool
    {
        $stmt = $this->db->prepare("
            UPDATE subscribers
            SET status = ?
            WHERE id = ?
              AND deleted_at IS NULL
        ");

        return $stmt->execute([$status, $id]);
    }

    public function updateServiceStatus(int $subscriberId, string $status): bool
    {
        $stmt = $this->db->prepare("
            UPDATE subscriber_services
            SET status = ?
            WHERE subscriber_id = ?
        ");

        return $stmt->execute([$status, $subscriberId]);
    }

    public function updateAccountStatuses(int $subscriberId, string $subscriberStatus, string $serviceStatus): bool
    {
        $this->db->beginTransaction();

        try {
            if (!$this->updateSubscriberStatus($subscriberId, $subscriberStatus)) {
                throw new PDOException('Unable to update subscriber status.');
            }

            if (!$this->updateServiceStatus($subscriberId, $serviceStatus)) {
                throw new PDOException('Unable to update service status.');
            }

            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return false;
        }
    }

    public function updatePppPassword(int $subscriberId, string $password): bool
    {
        $stmt = $this->db->prepare("
            UPDATE subscriber_services
            SET ppp_password = ?
            WHERE subscriber_id = ?
        ");

        return $stmt->execute([$password, $subscriberId]);
    }

    public function updateServicePppPassword(int $serviceId, string $password): bool
    {
        $stmt = $this->db->prepare("
            UPDATE subscriber_services
            SET ppp_password = ?
            WHERE id = ?
        ");

        return $stmt->execute([$password, $serviceId]);
    }

    public function softDelete(int $subscriberId): bool
    {
        $this->db->beginTransaction();

        try {
        $stmt1 = $this->db->prepare("
            UPDATE subscribers
            SET
                status = 'INACTIVE',
                deleted_at = NOW()
            WHERE id = ?
        ");

        $stmt2 = $this->db->prepare("
            UPDATE subscriber_services
            SET status = 'TERMINATED'
            WHERE subscriber_id = ?
        ");

            if (!$stmt1->execute([$subscriberId]) || !$stmt2->execute([$subscriberId])) {
                throw new PDOException('Unable to delete subscriber.');
            }

            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return false;
        }
    }

    public function radiusTableExists(string $tableName): bool
    {
        $stmt = $this->radiusDb()->prepare("
            SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = ?
        ");
        $stmt->execute([$tableName]);

        return (int)$stmt->fetchColumn() > 0;
    }

    public function syncRadiusCreate(
        string $username,
        string $password,
        string $planName,
        string $status = 'ACTIVE',
        ?string $expiresAt = null
    ): void {
        $del1 = $this->radiusDb()->prepare("
            DELETE FROM radcheck
            WHERE username = ?
              AND attribute = 'Cleartext-Password'
        ");
        $del1->execute([$username]);

        $ins1 = $this->radiusDb()->prepare("
            INSERT INTO radcheck (username, attribute, op, value)
            VALUES (?, 'Cleartext-Password', ':=', ?)
        ");
        $ins1->execute([$username, $password]);

        $del2 = $this->radiusDb()->prepare("
            DELETE FROM radusergroup
            WHERE username = ?
        ");
        $del2->execute([$username]);

        $ins2 = $this->radiusDb()->prepare("
            INSERT INTO radusergroup (username, groupname, priority)
            VALUES (?, ?, 1)
        ");
        $ins2->execute([$username, $planName]);

        if ($this->radiusTableExists('isp_subscribers')) {
            $del3 = $this->radiusDb()->prepare("
                DELETE FROM isp_subscribers
                WHERE username = ?
            ");
            $del3->execute([$username]);

            $ins3 = $this->radiusDb()->prepare("
                INSERT INTO isp_subscribers (username, status, expires_at, plan)
                VALUES (?, ?, ?, ?)
            ");
            $ins3->execute([$username, $status, $expiresAt, $planName]);
        }
    }

    public function syncRadiusPlan(string $username, string $planName, ?string $expiresAt = null): void
    {
        $del = $this->radiusDb()->prepare("
            DELETE FROM radusergroup
            WHERE username = ?
        ");
        $del->execute([$username]);

        $ins = $this->radiusDb()->prepare("
            INSERT INTO radusergroup (username, groupname, priority)
            VALUES (?, ?, 1)
        ");
        $ins->execute([$username, $planName]);

        if ($this->radiusTableExists('isp_subscribers')) {
            $stmt = $this->radiusDb()->prepare("
                UPDATE isp_subscribers
                SET plan = ?, expires_at = ?
                WHERE username = ?
            ");
            $stmt->execute([$planName, $expiresAt, $username]);
        }
    }

    public function syncRadiusStatus(string $username, string $status, ?string $expiresAt = null): void
    {
        if ($this->radiusTableExists('isp_subscribers')) {
            $stmt = $this->radiusDb()->prepare("
                UPDATE isp_subscribers
                SET status = ?, expires_at = ?
                WHERE username = ?
            ");
            $stmt->execute([$status, $expiresAt, $username]);
        }
    }

    public function syncRadiusPassword(string $username, string $password): void
    {
        $del = $this->radiusDb()->prepare("
            DELETE FROM radcheck
            WHERE username = ?
              AND attribute = 'Cleartext-Password'
        ");
        $del->execute([$username]);

        $ins = $this->radiusDb()->prepare("
            INSERT INTO radcheck (username, attribute, op, value)
            VALUES (?, 'Cleartext-Password', ':=', ?)
        ");
        $ins->execute([$username, $password]);
    }

    public function radiusDelete(string $username): void
    {
        foreach (['radcheck', 'radusergroup'] as $table) {
            $stmt = $this->radiusDb()->prepare("DELETE FROM {$table} WHERE username = ?");
            $stmt->execute([$username]);
        }

        if ($this->radiusTableExists('isp_subscribers')) {
            $stmt = $this->radiusDb()->prepare("DELETE FROM isp_subscribers WHERE username = ?");
            $stmt->execute([$username]);
        }
    }

    public function resetPortalPassword(int $subscriberId, string $newPassword): array
    {
        $stmt = $this->db->prepare("
        SELECT
            s.id AS subscriber_id,
            s.user_id,
            s.email,
            s.full_name,
            u.username,
            u.email AS user_email,
            u.role,
            u.status AS user_status
        FROM subscribers s
        LEFT JOIN users u
            ON u.id = s.user_id
        WHERE s.id = ?
          AND s.deleted_at IS NULL
        LIMIT 1
    ");

        $stmt->execute([$subscriberId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return [
                'ok' => false,
                'message' => 'Subscriber not found.',
            ];
        }

        $userId = (int)($row['user_id'] ?? 0);

        if ($userId <= 0) {
            return [
                'ok' => false,
                'message' => 'Subscriber has no linked portal user account.',
            ];
        }

        $role = strtoupper((string)($row['role'] ?? ''));

        if ($role !== 'SUBSCRIBER') {
            return [
                'ok' => false,
                'message' => 'Linked user account is not a subscriber account.',
            ];
        }

        $stmtUpdate = $this->db->prepare("
        UPDATE users
        SET
            password = ?,
            status = 'ACTIVE',
            updated_at = NOW()
        WHERE id = ?
          AND role = 'SUBSCRIBER'
        LIMIT 1
    ");

        $ok = $stmtUpdate->execute([
            password_hash($newPassword, PASSWORD_DEFAULT),
            $userId,
        ]);

        if (!$ok) {
            return [
                'ok' => false,
                'message' => 'Failed to reset portal password.',
            ];
        }

        return [
            'ok' => true,
            'portal_username' => $row['username'] ?: ($row['user_email'] ?: $row['email']),
        ];
    }
}
