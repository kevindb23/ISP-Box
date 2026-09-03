<?php

namespace App\Modules\CgnatManagement\Repositories;

use PDO;

class CgnatManagementRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function findAvailableSvlan(int $vlanId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM network_vlans
            WHERE vlan_id = :vlan_id AND UPPER(vlan_type) = 'S_VLAN'
            LIMIT 1
        ");
        $stmt->execute(['vlan_id' => $vlanId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getAvailableSvlans(): array
    {
        $stmt = $this->db->query("
            SELECT v.id, v.vlan_id, v.name,
                   CASE WHEN b.vlan_id IS NOT NULL THEN 'DEPLOYED' ELSE 'AVAILABLE' END AS status
            FROM network_vlans v
            LEFT JOIN bng_vlan_interfaces b ON b.vlan_id = v.vlan_id
            WHERE v.vlan_type = 'S_VLAN'
            ORDER BY v.vlan_id ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /* =========================================================
       CGNAT POOLS
    ========================================================= */

    public function getAllPools(): array
    {
        $stmt = $this->db->query("
            SELECT *
            FROM cgnat_pools
            ORDER BY
                CASE status
                    WHEN 'ACTIVE' THEN 1
                    WHEN 'DRAFT' THEN 2
                    ELSE 3
                END,
                id DESC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findPoolById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM cgnat_pools WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findPoolByName(string $poolName, ?int $excludeId = null): ?array
    {
        $sql = 'SELECT * FROM cgnat_pools WHERE pool_name = :pool_name';
        $params = ['pool_name' => $poolName];

        if ($excludeId !== null) {
            $sql .= ' AND id <> :exclude_id';
            $params['exclude_id'] = $excludeId;
        }

        $sql .= ' LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findPoolByAccelPoolName(string $accelPoolName, ?int $excludeId = null): ?array
    {
        $sql = 'SELECT * FROM cgnat_pools WHERE accel_pool_name = :accel_pool_name';
        $params = ['accel_pool_name' => $accelPoolName];

        if ($excludeId !== null) {
            $sql .= ' AND id <> :exclude_id';
            $params['exclude_id'] = $excludeId;
        }

        $sql .= ' LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function createPool(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO cgnat_pools (
                pool_name, network, gateway, range_start, range_end,
                accel_pool_name, type, status, remarks
            ) VALUES (
                :pool_name, :network, :gateway, :range_start, :range_end,
                :accel_pool_name, :type, :status, :remarks
            )
        ");

        $stmt->execute([
            'pool_name' => $data['pool_name'],
            'network' => $data['network'],
            'gateway' => $data['gateway'],
            'range_start' => $data['range_start'],
            'range_end' => $data['range_end'],
            'accel_pool_name' => $data['accel_pool_name'],
            'type' => $data['type'],
            'status' => $data['status'],
            'remarks' => $data['remarks'],
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function updatePool(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE cgnat_pools
            SET
                pool_name = :pool_name,
                network = :network,
                gateway = :gateway,
                range_start = :range_start,
                range_end = :range_end,
                accel_pool_name = :accel_pool_name,
                type = :type,
                status = :status,
                remarks = :remarks
            WHERE id = :id
        ");

        return $stmt->execute([
            'id' => $id,
            'pool_name' => $data['pool_name'],
            'network' => $data['network'],
            'gateway' => $data['gateway'],
            'range_start' => $data['range_start'],
            'range_end' => $data['range_end'],
            'accel_pool_name' => $data['accel_pool_name'],
            'type' => $data['type'],
            'status' => $data['status'],
            'remarks' => $data['remarks'],
        ]);
    }

    public function deletePool(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM cgnat_pools WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    /* =========================================================
       DEPLOYMENTS
    ========================================================= */

    public function createDeployment(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO cgnat_deployments (
                pool_id, target_type, status, rendered_config, result_message, applied_at
            ) VALUES (
                :pool_id, :target_type, :status, :rendered_config, :result_message, :applied_at
            )
        ");

        $stmt->execute([
            'pool_id' => $data['pool_id'],
            'target_type' => $data['target_type'],
            'status' => $data['status'],
            'rendered_config' => $data['rendered_config'],
            'result_message' => $data['result_message'],
            'applied_at' => $data['applied_at'],
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function getDeployments(?int $poolId = null): array
    {
        $sql = "
            SELECT d.*, p.pool_name, p.type AS pool_type
            FROM cgnat_deployments d
            INNER JOIN cgnat_pools p ON p.id = d.pool_id
        ";

        $params = [];

        if ($poolId !== null) {
            $sql .= ' WHERE d.pool_id = :pool_id';
            $params['pool_id'] = $poolId;
        }

        $sql .= ' ORDER BY d.id DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /* =========================================================
       USAGE
    ========================================================= */

    public function insertBngVlanInterface(int $vlanId, string $iface): void
    {
        $stmt = $this->db->prepare("
        INSERT INTO bng_vlan_interfaces (vlan_id, interface)
        VALUES (:vlan_id, :interface)
    ");

        $stmt->execute([
            'vlan_id' => $vlanId,
            'interface' => $iface,
        ]);
    }
    public function getUsageRows(int $limit = 200): array
    {
        $stmt = $this->db->prepare("
            SELECT
                ra.username,
                ra.framed_ip,
                ra.session_id,
                ra.nas_ip,
                ra.session_start,
                ra.session_stop,
                ra.session_time,
                ss.id AS service_id,
                s.full_name AS subscriber_name
            FROM radius_accounting ra
            LEFT JOIN subscriber_services ss
                ON ss.ppp_username = ra.username
            LEFT JOIN subscribers s
                ON s.id = ss.subscriber_id
            WHERE ra.framed_ip IS NOT NULL
              AND ra.framed_ip <> ''
            ORDER BY COALESCE(ra.session_start, ra.created_at) DESC
            LIMIT :limit_rows
        ");

        $stmt->bindValue(':limit_rows', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
