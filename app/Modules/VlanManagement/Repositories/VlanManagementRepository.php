<?php

namespace App\Modules\VlanManagement\Repositories;

use App\Infrastructure\Security\SecretCipher;
use PDO;

class VlanManagementRepository
{
    private PDO $db;

    public function __construct(PDO $db, private SecretCipher $secrets)
    {
        $this->db = $db;
    }

    public function getSummary(): array
    {
        $summary = [
            'total_vlans' => 0,
            'total_c_vlans' => 0,
            'total_s_vlans' => 0,
            'deployed_count' => 0,
        ];

        $sql = "
            SELECT
                (SELECT COUNT(*) FROM network_vlans) AS total_vlans,
                (SELECT COUNT(*) FROM network_vlans WHERE vlan_type = 'C_VLAN') AS total_c_vlans,
                (SELECT COUNT(*) FROM network_vlans WHERE vlan_type = 'S_VLAN') AS total_s_vlans,
                (SELECT COUNT(*) FROM network_vlans WHERE deployment_status = 'DEPLOYED') AS deployed_count
        ";

        $stmt = $this->db->query($sql);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $summary['total_vlans'] = (int)($row['total_vlans'] ?? 0);
            $summary['total_c_vlans'] = (int)($row['total_c_vlans'] ?? 0);
            $summary['total_s_vlans'] = (int)($row['total_s_vlans'] ?? 0);
            $summary['deployed_count'] = (int)($row['deployed_count'] ?? 0);
        }

        return $summary;
    }

    public function getVlans(): array
    {
        $stmt = $this->db->query("
            SELECT
                nv.id,
                nv.olt_id,
                nv.olt_port_id,
                nv.parent_svlan_id,
                nv.vlan_id,
                nv.vlan_type,
                nv.name,
                nv.description,
                nv.deployment_status,
                nv.deployed_at,
                nv.deployment_output,
                nv.created_at,
                od.ip_address AS olt_ip_address,
                op.frame,
                op.slot,
                op.port,
                CASE
                    WHEN op.id IS NOT NULL THEN CONCAT(op.frame, '/', op.slot, '/', op.port)
                    ELSE NULL
                END AS olt_port_path
            FROM network_vlans nv
            LEFT JOIN olt_devices od ON od.id = nv.olt_id
            LEFT JOIN olt_ports op ON op.id = nv.olt_port_id
            ORDER BY nv.olt_id ASC, nv.vlan_type ASC, nv.vlan_id ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findVlanById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                nv.id,
                nv.olt_id,
                nv.olt_port_id,
                nv.parent_svlan_id,
                nv.vlan_id,
                nv.vlan_type,
                nv.name,
                nv.description,
                nv.deployment_status,
                nv.deployed_at,
                nv.deployment_output,
                nv.created_at,
                od.ip_address AS olt_ip_address,
                op.frame,
                op.slot,
                op.port,
                CASE
                    WHEN op.id IS NOT NULL THEN CONCAT(op.frame, '/', op.slot, '/', op.port)
                    ELSE NULL
                END AS olt_port_path
            FROM network_vlans nv
            LEFT JOIN olt_devices od ON od.id = nv.olt_id
            LEFT JOIN olt_ports op ON op.id = nv.olt_port_id
            WHERE nv.id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findDeployedSvlanIdsForOlt(int $oltId): array
    {
        $stmt = $this->db->prepare("
            SELECT DISTINCT vlan_id
            FROM network_vlans
            WHERE olt_id = ?
              AND vlan_type = 'S_VLAN'
              AND deployment_status = 'DEPLOYED'
            ORDER BY vlan_id ASC
        ");
        $stmt->execute([$oltId]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    public function findParentSvlan(int $id, int $oltId): ?array
    {
        $stmt=$this->db->prepare("SELECT id,olt_id,vlan_id,deployment_status FROM network_vlans WHERE id=? AND olt_id=? AND vlan_type='S_VLAN' LIMIT 1");
        $stmt->execute([$id,$oltId]); return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    public function countChildVlans(int $svlanId): int { $stmt=$this->db->prepare("SELECT COUNT(*) FROM network_vlans WHERE parent_svlan_id=? AND vlan_type='C_VLAN'");$stmt->execute([$svlanId]);return (int)$stmt->fetchColumn(); }

    /**
     * VLAN ID must be unique per OLT regardless of VLAN type.
     * This blocks the same VLAN number from existing as both C_VLAN and S_VLAN on the same OLT.
     */
    public function vlanNumberExists(int $vlanNumber, ?int $excludeId = null, ?int $oltId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM network_vlans WHERE vlan_id = ?";
        $params = [$vlanNumber];

        if ($oltId !== null) {
            $sql .= " AND olt_id = ?";
            $params[] = $oltId;
        }

        if ($excludeId !== null) {
            $sql .= " AND id <> ?";
            $params[] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * One S-VLAN per OLT port.
     */
    public function sVlanPortExists(int $oltId, int $oltPortId, ?int $excludeId = null): bool
    {
        $sql = "
            SELECT COUNT(*)
            FROM network_vlans
            WHERE olt_id = ?
              AND olt_port_id = ?
              AND vlan_type = 'S_VLAN'
        ";
        $params = [$oltId, $oltPortId];

        if ($excludeId !== null) {
            $sql .= " AND id <> ?";
            $params[] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn() > 0;
    }

    public function createVlan(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO network_vlans
            (
                olt_id,
                olt_port_id,
                parent_svlan_id,
                vlan_id,
                vlan_type,
                name,
                description,
                deployment_status,
                deployed_at,
                deployment_output
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            (int)$data['olt_id'],
            isset($data['olt_port_id']) && $data['olt_port_id'] !== '' ? (int)$data['olt_port_id'] : null,
            isset($data['parent_svlan_id']) && $data['parent_svlan_id'] !== '' ? (int)$data['parent_svlan_id'] : null,
            (int)$data['vlan_id'],
            (string)$data['vlan_type'],
            (string)$data['name'],
            (string)($data['description'] ?? ''),
            (string)($data['deployment_status'] ?? 'PENDING'),
            $data['deployed_at'] ?? null,
            $data['deployment_output'] ?? null,
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function updateVlan(int $id, array $data): void
    {
        $stmt = $this->db->prepare("
            UPDATE network_vlans
            SET
                olt_id = ?,
                olt_port_id = ?,
                parent_svlan_id = ?,
                vlan_id = ?,
                vlan_type = ?,
                name = ?,
                description = ?
            WHERE id = ?
        ");

        $stmt->execute([
            (int)$data['olt_id'],
            isset($data['olt_port_id']) && $data['olt_port_id'] !== '' ? (int)$data['olt_port_id'] : null,
            isset($data['parent_svlan_id']) && $data['parent_svlan_id'] !== '' ? (int)$data['parent_svlan_id'] : null,
            (int)$data['vlan_id'],
            (string)$data['vlan_type'],
            (string)$data['name'],
            (string)($data['description'] ?? ''),
            $id,
        ]);
    }

    public function updateDeploymentState(int $id, string $status, ?string $deployedAt, ?string $deploymentOutput): void
    {
        $stmt = $this->db->prepare("
            UPDATE network_vlans
            SET
                deployment_status = ?,
                deployed_at = ?,
                deployment_output = ?
            WHERE id = ?
        ");

        $stmt->execute([
            strtoupper($status),
            $deployedAt,
            $deploymentOutput,
            $id,
        ]);
    }

    public function deleteVlan(int $id): void
    {
        $stmt = $this->db->prepare("DELETE FROM network_vlans WHERE id = ?");
        $stmt->execute([$id]);
    }

    public function findOltById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                id,
                ip_address,
                username,
                password
            FROM olt_devices
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $row['password'] = $this->secrets->decrypt($row['password'] ?? null);
        return $row;
    }

    public function getOlts(): array
    {
        $stmt = $this->db->query("
            SELECT
                id,
                ip_address,
                username,
                password
            FROM olt_devices
            ORDER BY id ASC
        ");

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$row) {
            $row['password'] = $this->secrets->decrypt($row['password'] ?? null);
        }
        unset($row);

        return $rows;
    }

    public function findOltPortById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM olt_ports
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getPortsByOltId(int $oltId): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM olt_ports
            WHERE olt_id = ?
            ORDER BY frame ASC, slot ASC, port ASC, id ASC
        ");
        $stmt->execute([$oltId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getMgmtVlans(): array
    {
        $stmt = $this->db->query("
            SELECT
                mv.id,
                mv.olt_id,
                mv.olt_port_id,
                mv.mgmt_vlan,
                mv.description,
                mv.created_at,
                od.ip_address AS olt_ip_address,
                CONCAT(op.frame, '/', op.slot, '/', op.port) AS olt_port_path
            FROM olt_mgmt_vlans mv
            LEFT JOIN olt_devices od ON od.id = mv.olt_id
            LEFT JOIN olt_ports op ON op.id = mv.olt_port_id
            ORDER BY mv.olt_id ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findMgmtVlanById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                mv.id,
                mv.olt_id,
                mv.olt_port_id,
                mv.mgmt_vlan,
                mv.description,
                mv.created_at,
                od.ip_address AS olt_ip_address,
                CONCAT(op.frame, '/', op.slot, '/', op.port) AS olt_port_path
            FROM olt_mgmt_vlans mv
            LEFT JOIN olt_devices od ON od.id = mv.olt_id
            LEFT JOIN olt_ports op ON op.id = mv.olt_port_id
            WHERE mv.id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function mgmtVlanExists(int $oltId, int $mgmtVlan, ?int $excludeId = null): bool
{
    $sql = "
        SELECT COUNT(*)
        FROM olt_mgmt_vlans
        WHERE olt_id = ?
          AND mgmt_vlan = ?
    ";

    $params = [$oltId, $mgmtVlan];

    if ($excludeId !== null) {
        $sql .= " AND id <> ?";
        $params[] = $excludeId;
    }

    $stmt = $this->db->prepare($sql);
    $stmt->execute($params);

    return (int)$stmt->fetchColumn() > 0;
}

public function createMgmtVlan(int $oltId, int $oltPortId, int $mgmtVlan, ?string $description = null): int
{
    $stmt = $this->db->prepare("
        INSERT INTO olt_mgmt_vlans
        (
            olt_id,
            olt_port_id,
            mgmt_vlan,
            description
        )
        VALUES (?, ?, ?, ?)
    ");

    $stmt->execute([
        $oltId,
        $oltPortId,
        $mgmtVlan,
        $description,
    ]);

    return (int)$this->db->lastInsertId();
}

public function updateMgmtVlan(int $id, int $oltId, int $oltPortId, int $mgmtVlan, ?string $description = null): void
{
    $stmt = $this->db->prepare("
        UPDATE olt_mgmt_vlans
        SET
            olt_id = ?,
            olt_port_id = ?,
            mgmt_vlan = ?,
            description = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $oltId,
        $oltPortId,
        $mgmtVlan,
        $description,
        $id,
    ]);
}

    public function deleteMgmtVlan(int $id): void
    {
        $stmt = $this->db->prepare("DELETE FROM olt_mgmt_vlans WHERE id = ?");
        $stmt->execute([$id]);
    }
}
