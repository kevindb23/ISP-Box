<?php

namespace App\Modules\OltManagement\Repositories;

use App\Infrastructure\Security\SecretCipher;
use Framework\DatabaseConnection;
use PDO;

class OltManagementRepository
{
    private PDO $db;

    public function __construct(DatabaseConnection $database, private SecretCipher $secrets)
    {
        $this->db = $database->get();
    }

    public function getAllDevices(): array
    {
        $stmt = $this->db->query("
        SELECT
            id,
            name,
            ip_address,
            username,
            password,
            vendor,
            enable_home_gateway_omci,
            auto_detect_omci_support,
            created_at
        FROM olt_devices
        ORDER BY id DESC
    ");

        return $this->decryptRows($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'password');
    }

    public function findDeviceById(int $id): ?array
    {
        $stmt = $this->db->prepare("
        SELECT
            id,
            name,
            ip_address,
            username,
            password,
            vendor,
            enable_home_gateway_omci,
            auto_detect_omci_support,
            created_at
        FROM olt_devices
        WHERE id = ?
        LIMIT 1
    ");
        $stmt->execute([$id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->decryptRow($row, 'password') : null;
    }

    public function createDevice(array $data): int
    {
        $stmt = $this->db->prepare("
        INSERT INTO olt_devices
        (
            name,
            ip_address,
            username,
            password,
            vendor,
            enable_home_gateway_omci,
            auto_detect_omci_support
        )
        VALUES
        (?, ?, ?, ?, ?, ?, ?)
    ");

        $stmt->execute([
            $data['name'],
            $data['ip_address'],
            $data['username'],
            $this->secrets->encrypt((string)$data['password']),
            $data['vendor'],
            (int)($data['enable_home_gateway_omci'] ?? 0),
            (int)($data['auto_detect_omci_support'] ?? 0),
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function updateDevice(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
        UPDATE olt_devices
        SET
            name = ?,
            ip_address = ?,
            username = ?,
            password = ?,
            vendor = ?,
            enable_home_gateway_omci = ?,
            auto_detect_omci_support = ?
        WHERE id = ?
    ");

        return $stmt->execute([
            $data['name'],
            $data['ip_address'],
            $data['username'],
            $this->secrets->encrypt((string)$data['password']),
            $data['vendor'],
            (int)($data['enable_home_gateway_omci'] ?? 0),
            (int)($data['auto_detect_omci_support'] ?? 0),
            $id,
        ]);
    }

    public function deleteDevice(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM olt_devices WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function deviceHasPorts(int $oltId): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM olt_ports
            WHERE olt_id = ?
        ");
        $stmt->execute([$oltId]);

        return (int)$stmt->fetchColumn() > 0;
    }

    public function getAllPorts(): array
    {
        $stmt = $this->db->query("
            SELECT
                p.id,
                p.olt_id,
                d.name AS olt_name,
                p.frame,
                p.slot,
                p.port,
                CONCAT(COALESCE(p.frame, 0), '/', COALESCE(p.slot, 0), '/', COALESCE(p.port, 0)) AS port_path,
                p.board_name,
                p.board_status,
                p.board_type,
                p.port_type,
                p.svlan,
                p.description,
                p.link_status,
                p.optic_status,
                p.speed,
                p.duplex,
                p.active_state,
                p.ont_count,
                p.ont_online,
                p.created_at
            FROM olt_ports p
            JOIN olt_devices d ON d.id = p.olt_id
            ORDER BY p.olt_id ASC, p.slot ASC, p.port ASC
        ");

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$row) {
            $row['allowed_svlans'] = $this->getPortSvlanMembers((int)$row['id']);
            $row['allowed_svlans_csv'] = implode(',', $row['allowed_svlans']);
        }
        unset($row);

        return $rows;
    }

    public function getPortsByOltId(int $oltId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                p.id,
                p.olt_id,
                d.name AS olt_name,
                p.frame,
                p.slot,
                p.port,
                CONCAT(COALESCE(p.frame, 0), '/', COALESCE(p.slot, 0), '/', COALESCE(p.port, 0)) AS port_path,
                p.board_name,
                p.board_status,
                p.board_type,
                p.port_type,
                p.svlan,
                p.description,
                p.link_status,
                p.optic_status,
                p.speed,
                p.duplex,
                p.active_state,
                p.ont_count,
                p.ont_online,
                p.created_at
            FROM olt_ports p
            JOIN olt_devices d ON d.id = p.olt_id
            WHERE p.olt_id = ?
            ORDER BY p.slot ASC, p.port ASC
        ");
        $stmt->execute([$oltId]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$row) {
            $row['allowed_svlans'] = $this->getPortSvlanMembers((int)$row['id']);
            $row['allowed_svlans_csv'] = implode(',', $row['allowed_svlans']);
        }
        unset($row);

        return $rows;
    }

    public function findPortById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                p.id,
                p.olt_id,
                d.name AS olt_name,
                p.frame,
                p.slot,
                p.port,
                CONCAT(COALESCE(p.frame, 0), '/', COALESCE(p.slot, 0), '/', COALESCE(p.port, 0)) AS port_path,
                p.board_name,
                p.board_status,
                p.board_type,
                p.port_type,
                p.svlan,
                p.description,
                p.link_status,
                p.optic_status,
                p.speed,
                p.duplex,
                p.active_state,
                p.ont_count,
                p.ont_online,
                p.created_at
            FROM olt_ports p
            JOIN olt_devices d ON d.id = p.olt_id
            WHERE p.id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $row['allowed_svlans'] = $this->getPortSvlanMembers((int)$row['id']);
        $row['allowed_svlans_csv'] = implode(',', $row['allowed_svlans']);

        return $row;
    }

    public function createPort(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO olt_ports
            (
                olt_id,
                frame,
                slot,
                port,
                svlan,
                description,
                board_name,
                board_status,
                board_type,
                port_type,
                link_status,
                optic_status,
                speed,
                duplex,
                active_state,
                ont_count,
                ont_online
            )
            VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $data['olt_id'],
            $data['frame'],
            $data['slot'],
            $data['port'],
            $data['svlan'],
            $data['description'],
            $data['board_name'],
            $data['board_status'],
            $data['board_type'],
            $data['port_type'],
            $data['link_status'],
            $data['optic_status'],
            $data['speed'],
            $data['duplex'],
            $data['active_state'],
            $data['ont_count'],
            $data['ont_online'],
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function updatePort(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE olt_ports
            SET
                olt_id = ?,
                frame = ?,
                slot = ?,
                port = ?,
                svlan = ?,
                description = ?,
                board_name = ?,
                board_status = ?,
                board_type = ?,
                port_type = ?,
                link_status = ?,
                optic_status = ?,
                speed = ?,
                duplex = ?,
                active_state = ?,
                ont_count = ?,
                ont_online = ?
            WHERE id = ?
        ");

        return $stmt->execute([
            $data['olt_id'],
            $data['frame'],
            $data['slot'],
            $data['port'],
            $data['svlan'],
            $data['description'],
            $data['board_name'],
            $data['board_status'],
            $data['board_type'],
            $data['port_type'],
            $data['link_status'],
            $data['optic_status'],
            $data['speed'],
            $data['duplex'],
            $data['active_state'],
            $data['ont_count'],
            $data['ont_online'],
            $id,
        ]);
    }

    public function deletePort(int $id): bool
    {
        $this->replacePortSvlanMembers($id, []);

        $stmt = $this->db->prepare("DELETE FROM olt_ports WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function findPhysicalPort(int $oltId, int $frame, int $slot, int $port): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM olt_ports
            WHERE olt_id = ?
              AND frame = ?
              AND slot = ?
              AND port = ?
            LIMIT 1
        ");
        $stmt->execute([$oltId, $frame, $slot, $port]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function physicalPortExists(int $oltId, int $frame, int $slot, int $port, ?int $excludeId = null): bool
    {
        $sql = "
            SELECT COUNT(*)
            FROM olt_ports
            WHERE olt_id = ?
              AND frame = ?
              AND slot = ?
              AND port = ?
        ";

        $params = [$oltId, $frame, $slot, $port];

        if ($excludeId !== null) {
            $sql .= " AND id <> ?";
            $params[] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn() > 0;
    }

    public function svlanExists(int $svlan, ?int $excludeId = null): bool
    {
        $sql = "
            SELECT COUNT(*)
            FROM olt_ports
            WHERE svlan = ?
        ";
        $params = [$svlan];

        if ($excludeId !== null) {
            $sql .= " AND id <> ?";
            $params[] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn() > 0;
    }

    public function portIsUsedByLcp(int $portId): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM lcp_boxes
            WHERE olt_port_id = ?
              AND deleted_at IS NULL
        ");
        $stmt->execute([$portId]);

        return (int)$stmt->fetchColumn() > 0;
    }

    public function getPortSvlanMembers(int $portId): array
{
    if (!$this->tableExists('olt_port_svlan_members')) {
        return [];
    }

    $stmt = $this->db->prepare("
        SELECT svlan
        FROM olt_port_svlan_members
        WHERE olt_port_id = ?
        ORDER BY svlan ASC
    ");
    $stmt->execute([$portId]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    return array_map(fn($x) => (int)$x['svlan'], $rows);
}

    public function replacePortSvlanMembers(int $portId, array $svlans): void
{
    $tableExists = $this->tableExists('olt_port_svlan_members');
    if (!$tableExists) {
        return;
    }

    $del = $this->db->prepare("DELETE FROM olt_port_svlan_members WHERE olt_port_id = ?");
    $del->execute([$portId]);

    if (empty($svlans)) {
        return;
    }

    $ins = $this->db->prepare("
        INSERT INTO olt_port_svlan_members (olt_port_id, svlan)
        VALUES (?, ?)
    ");

    foreach ($svlans as $svlan) {
        $ins->execute([$portId, (int)$svlan]);
    }
}

    /* =========================================================
     * DBA PROFILES
     * ========================================================= */

    public function getAllDbaProfiles(): array
    {
        if (!$this->tableExists('olt_dba_profiles')) return [];

        $stmt = $this->db->query("
        SELECT
            p.id,
            p.olt_id,
            p.profile_id,
            p.profile_name,
            p.dba_type AS profile_type,
            p.max_bandwidth,
            p.max_bandwidth AS max,
            p.description,
            p.created_at,
            d.name AS olt_name,
            d.ip_address AS olt_ip
        FROM olt_dba_profiles p
        LEFT JOIN olt_devices d ON d.id = p.olt_id
        ORDER BY p.olt_id ASC, p.profile_id ASC, p.id ASC
    ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getDbaProfilesByOltId(int $oltId): array
    {
        if (!$this->tableExists('olt_dba_profiles')) return [];

        $stmt = $this->db->prepare("
        SELECT
            p.id,
            p.olt_id,
            p.profile_id,
            p.profile_name,
            p.dba_type AS profile_type,
            p.max_bandwidth,
            p.max_bandwidth AS max,
            p.description,
            p.created_at,
            d.name AS olt_name,
            d.ip_address AS olt_ip
        FROM olt_dba_profiles p
        LEFT JOIN olt_devices d ON d.id = p.olt_id
        WHERE p.olt_id = ?
        ORDER BY p.profile_id ASC, p.id ASC
    ");
        $stmt->execute([$oltId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findDbaProfileById(int $id): ?array
    {
        if (!$this->tableExists('olt_dba_profiles')) return null;

        $stmt = $this->db->prepare("
        SELECT
            p.id,
            p.olt_id,
            p.profile_id,
            p.profile_name,
            p.dba_type AS profile_type,
            p.max_bandwidth,
            p.max_bandwidth AS max,
            p.description,
            p.created_at,
            d.name AS olt_name,
            d.ip_address AS olt_ip
        FROM olt_dba_profiles p
        LEFT JOIN olt_devices d ON d.id = p.olt_id
        WHERE p.id = ?
        LIMIT 1
    ");
        $stmt->execute([$id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function createDbaProfile(array $data): int
    {
        $stmt = $this->db->prepare("
        INSERT INTO olt_dba_profiles
        (olt_id, profile_id, profile_name, dba_type, max_bandwidth, description)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

        $stmt->execute([
            $data['olt_id'] ?? null,
            $data['profile_id'],
            $data['profile_name'],
            $data['profile_type'] ?? 'type4',
            $data['max_bandwidth'],
            $data['description'] ?? '',
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function updateDbaProfile(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
        UPDATE olt_dba_profiles
        SET
            olt_id = ?,
            profile_id = ?,
            profile_name = ?,
            dba_type = ?,
            max_bandwidth = ?,
            description = ?
        WHERE id = ?
    ");

        return $stmt->execute([
            $data['olt_id'] ?? null,
            $data['profile_id'],
            $data['profile_name'],
            $data['profile_type'] ?? 'type4',
            $data['max_bandwidth'],
            $data['description'] ?? '',
            $id,
        ]);
    }

    public function deleteDbaProfile(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM olt_dba_profiles WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function dbaProfileIdExists(int $profileId, ?int $oltId = null, ?int $excludeId = null): bool
    {
        if (!$this->tableExists('olt_dba_profiles')) return false;

        $sql = "SELECT COUNT(*) FROM olt_dba_profiles WHERE profile_id = ?";
        $params = [$profileId];

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

    public function dbaProfileNameExists(string $profileName, ?int $oltId = null, ?int $excludeId = null): bool
    {
        if (!$this->tableExists('olt_dba_profiles')) return false;

        $sql = "SELECT COUNT(*) FROM olt_dba_profiles WHERE profile_name = ?";
        $params = [$profileName];

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

    /* =========================================================
     * LINE PROFILES
     * ========================================================= */

    public function getAllLineProfiles(): array
    {
        if (!$this->tableExists('olt_ont_line_profiles')) return [];

        $stmt = $this->db->query("
        SELECT
            p.*,
            d.name AS olt_name,
            d.ip_address AS olt_ip
        FROM olt_ont_line_profiles p
        LEFT JOIN olt_devices d ON d.id = p.olt_id
        ORDER BY p.olt_id ASC, p.profile_id ASC, p.id ASC
    ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getLineProfilesByOltId(int $oltId): array
    {
        if (!$this->tableExists('olt_ont_line_profiles')) return [];

        $stmt = $this->db->prepare("
        SELECT
            p.*,
            d.name AS olt_name,
            d.ip_address AS olt_ip
        FROM olt_ont_line_profiles p
        LEFT JOIN olt_devices d ON d.id = p.olt_id
        WHERE p.olt_id = ?
        ORDER BY p.profile_id ASC, p.id ASC
    ");
        $stmt->execute([$oltId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findLineProfileById(int $id): ?array
    {
        if (!$this->tableExists('olt_ont_line_profiles')) return null;

        $stmt = $this->db->prepare("
        SELECT
            p.*,
            d.name AS olt_name,
            d.ip_address AS olt_ip
        FROM olt_ont_line_profiles p
        LEFT JOIN olt_devices d ON d.id = p.olt_id
        WHERE p.id = ?
        LIMIT 1
    ");
        $stmt->execute([$id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function createLineProfile(array $data): int
    {
        $stmt = $this->db->prepare("
        INSERT INTO olt_ont_line_profiles
        (
            olt_id, profile_id, profile_name, customer_cvlan, management_vlan,
            omcc_encrypt, tr069_management_enable, tr069_ip_index, tcont_id,
            dba_profile_id, gem_subscriber_id, gem_management_id,
            gem_subscriber_encrypt, gem_management_encrypt, description
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

        $stmt->execute([
            $data['olt_id'] ?? null,
            $data['profile_id'],
            $data['profile_name'],
            $data['customer_cvlan'],
            $data['management_vlan'],
            $data['omcc_encrypt'],
            $data['tr069_management_enable'],
            $data['tr069_ip_index'],
            $data['tcont_id'],
            $data['dba_profile_id'],
            $data['gem_subscriber_id'],
            $data['gem_management_id'],
            $data['gem_subscriber_encrypt'],
            $data['gem_management_encrypt'],
            $data['description'] ?? '',
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function updateLineProfile(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
        UPDATE olt_ont_line_profiles
        SET
            olt_id = ?,
            profile_id = ?,
            profile_name = ?,
            customer_cvlan = ?,
            management_vlan = ?,
            omcc_encrypt = ?,
            tr069_management_enable = ?,
            tr069_ip_index = ?,
            tcont_id = ?,
            dba_profile_id = ?,
            gem_subscriber_id = ?,
            gem_management_id = ?,
            gem_subscriber_encrypt = ?,
            gem_management_encrypt = ?,
            description = ?
        WHERE id = ?
    ");

        return $stmt->execute([
            $data['olt_id'] ?? null,
            $data['profile_id'],
            $data['profile_name'],
            $data['customer_cvlan'],
            $data['management_vlan'],
            $data['omcc_encrypt'],
            $data['tr069_management_enable'],
            $data['tr069_ip_index'],
            $data['tcont_id'],
            $data['dba_profile_id'],
            $data['gem_subscriber_id'],
            $data['gem_management_id'],
            $data['gem_subscriber_encrypt'],
            $data['gem_management_encrypt'],
            $data['description'] ?? '',
            $id,
        ]);
    }

    public function deleteLineProfile(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM olt_ont_line_profiles WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function lineProfileIdExists(int $profileId, ?int $oltId = null, ?int $excludeId = null): bool
    {
        if (!$this->tableExists('olt_ont_line_profiles')) {
            return false;
        }

        $sql = "
        SELECT COUNT(*)
        FROM olt_ont_line_profiles
        WHERE profile_id = ?
    ";

        $params = [$profileId];

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

    public function lineProfileNameExists(string $profileName, ?int $oltId = null, ?int $excludeId = null): bool
    {
        if (!$this->tableExists('olt_ont_line_profiles')) {
            return false;
        }

        $sql = "
        SELECT COUNT(*)
        FROM olt_ont_line_profiles
        WHERE profile_name = ?
    ";

        $params = [$profileName];

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

    /* =========================================================
     * WAN PROFILES
     * ========================================================= */

    public function getAllWanProfiles(): array
    {
        if (!$this->tableExists('olt_wan_profiles')) return [];

        $stmt = $this->db->query("
        SELECT
            p.*,
            p.connection_type AS wan_mode,
            d.name AS olt_name,
            d.ip_address AS olt_ip
        FROM olt_wan_profiles p
        LEFT JOIN olt_devices d ON d.id = p.olt_id
        ORDER BY p.olt_id ASC, p.profile_id ASC, p.id ASC
    ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getWanProfilesByOltId(int $oltId): array
    {
        if (!$this->tableExists('olt_wan_profiles')) return [];

        $stmt = $this->db->prepare("
        SELECT
            p.*,
            p.connection_type AS wan_mode,
            d.name AS olt_name,
            d.ip_address AS olt_ip
        FROM olt_wan_profiles p
        LEFT JOIN olt_devices d ON d.id = p.olt_id
        WHERE p.olt_id = ?
        ORDER BY p.profile_id ASC, p.id ASC
    ");
        $stmt->execute([$oltId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findWanProfileById(int $id): ?array
    {
        if (!$this->tableExists('olt_wan_profiles')) return null;

        $stmt = $this->db->prepare("
        SELECT
            p.*,
            p.connection_type AS wan_mode,
            d.name AS olt_name,
            d.ip_address AS olt_ip
        FROM olt_wan_profiles p
        LEFT JOIN olt_devices d ON d.id = p.olt_id
        WHERE p.id = ?
        LIMIT 1
    ");
        $stmt->execute([$id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function createWanProfile(array $data): int
    {
        $stmt = $this->db->prepare("
        INSERT INTO olt_wan_profiles
        (olt_id, profile_id, profile_name, connection_type, nat_enable, vlan_mode, service_type, description)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

        $stmt->execute([
            $data['olt_id'] ?? null,
            $data['profile_id'],
            $data['profile_name'],
            $data['connection_type'],
            $data['nat_enable'],
            $data['vlan_mode'],
            $data['service_type'],
            $data['description'] ?? '',
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function updateWanProfile(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
        UPDATE olt_wan_profiles
        SET
            olt_id = ?,
            profile_id = ?,
            profile_name = ?,
            connection_type = ?,
            nat_enable = ?,
            vlan_mode = ?,
            service_type = ?,
            description = ?
        WHERE id = ?
    ");

        return $stmt->execute([
            $data['olt_id'] ?? null,
            $data['profile_id'],
            $data['profile_name'],
            $data['connection_type'],
            $data['nat_enable'],
            $data['vlan_mode'],
            $data['service_type'],
            $data['description'] ?? '',
            $id,
        ]);
    }

    public function deleteWanProfile(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM olt_wan_profiles WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /* =========================================================
     * TR069 PROFILES
     * ========================================================= */

    public function getAllTr069Profiles(): array
    {
        if (!$this->tableExists('olt_tr069_profiles')) return [];

        $stmt = $this->db->query("
        SELECT
            p.*,
            d.name AS olt_name,
            d.ip_address AS olt_ip
        FROM olt_tr069_profiles p
        LEFT JOIN olt_devices d ON d.id = p.olt_id
        ORDER BY p.olt_id ASC, p.profile_id ASC, p.id ASC
    ");

        return $this->decryptRows($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'acs_password');
    }

    public function getTr069ProfilesByOltId(int $oltId): array
    {
        if (!$this->tableExists('olt_tr069_profiles')) return [];

        $stmt = $this->db->prepare("
        SELECT
            p.*,
            d.name AS olt_name,
            d.ip_address AS olt_ip
        FROM olt_tr069_profiles p
        LEFT JOIN olt_devices d ON d.id = p.olt_id
        WHERE p.olt_id = ?
        ORDER BY p.profile_id ASC, p.id ASC
    ");
        $stmt->execute([$oltId]);

        return $this->decryptRows($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'acs_password');
    }

    public function findTr069ProfileById(int $id): ?array
    {
        if (!$this->tableExists('olt_tr069_profiles')) return null;

        $stmt = $this->db->prepare("
        SELECT
            p.*,
            d.name AS olt_name,
            d.ip_address AS olt_ip
        FROM olt_tr069_profiles p
        LEFT JOIN olt_devices d ON d.id = p.olt_id
        WHERE p.id = ?
        LIMIT 1
    ");
        $stmt->execute([$id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->decryptRow($row, 'acs_password') : null;
    }

    public function createTr069Profile(array $data): int
    {
        $stmt = $this->db->prepare("
        INSERT INTO olt_tr069_profiles
        (
            olt_id,
            profile_id,
            profile_name,
            acs_url,
            acs_username,
            acs_password,
            periodic_inform_enable,
            periodic_inform_interval,
            description
        )
        VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

        $stmt->execute([
            $data['olt_id'] ?? null,
            $data['profile_id'],
            $data['profile_name'],
            $data['acs_url'],
            $data['acs_username'] ?? '',
            $this->secrets->encrypt((string)($data['acs_password'] ?? '')),
            $data['periodic_inform_enable'],
            $data['periodic_inform_interval'],
            $data['description'] ?? '',
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function updateTr069Profile(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
        UPDATE olt_tr069_profiles
        SET
            olt_id = ?,
            profile_id = ?,
            profile_name = ?,
            acs_url = ?,
            acs_username = ?,
            acs_password = ?,
            periodic_inform_enable = ?,
            periodic_inform_interval = ?,
            description = ?
        WHERE id = ?
    ");

        return $stmt->execute([
            $data['olt_id'] ?? null,
            $data['profile_id'],
            $data['profile_name'],
            $data['acs_url'],
            $data['acs_username'] ?? '',
            $this->secrets->encrypt((string)($data['acs_password'] ?? '')),
            $data['periodic_inform_enable'],
            $data['periodic_inform_interval'],
            $data['description'] ?? '',
            $id,
        ]);
    }

    public function deleteTr069Profile(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM olt_tr069_profiles WHERE id = ?");
        return $stmt->execute([$id]);
    }

    private function decryptRows(array $rows, string $field): array
    {
        return array_map(fn(array $row): array => $this->decryptRow($row, $field), $rows);
    }

    private function decryptRow(array $row, string $field): array
    {
        if (array_key_exists($field, $row)) $row[$field] = $this->secrets->decrypt($row[$field]);
        return $row;
    }

    /* =========================================================
     * SRV PROFILES
     * ========================================================= */

    public function getAllSrvProfiles(): array
    {
        if (!$this->tableExists('olt_srv_profiles')) return [];

        $stmt = $this->db->query("
        SELECT
            p.*,
            p.eth_port_count AS eth_ports,
            d.name AS olt_name,
            d.ip_address AS olt_ip
        FROM olt_srv_profiles p
        LEFT JOIN olt_devices d ON d.id = p.olt_id
        ORDER BY p.olt_id ASC, p.profile_id ASC, p.id ASC
    ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getSrvProfilesByOltId(int $oltId): array
    {
        if (!$this->tableExists('olt_srv_profiles')) return [];

        $stmt = $this->db->prepare("
        SELECT
            p.*,
            p.eth_port_count AS eth_ports,
            d.name AS olt_name,
            d.ip_address AS olt_ip
        FROM olt_srv_profiles p
        LEFT JOIN olt_devices d ON d.id = p.olt_id
        WHERE p.olt_id = ?
        ORDER BY p.profile_id ASC, p.id ASC
    ");
        $stmt->execute([$oltId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findSrvProfileById(int $id): ?array
    {
        if (!$this->tableExists('olt_srv_profiles')) return null;

        $stmt = $this->db->prepare("
        SELECT
            p.*,
            p.eth_port_count AS eth_ports,
            d.name AS olt_name,
            d.ip_address AS olt_ip
        FROM olt_srv_profiles p
        LEFT JOIN olt_devices d ON d.id = p.olt_id
        WHERE p.id = ?
        LIMIT 1
    ");
        $stmt->execute([$id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function createSrvProfile(array $data): int
    {
        $stmt = $this->db->prepare("
        INSERT INTO olt_srv_profiles
        (olt_id, profile_id, profile_name, eth_port_count, service_mode, description)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

        $stmt->execute([
            $data['olt_id'] ?? null,
            $data['profile_id'],
            $data['profile_name'],
            $data['eth_port_count'],
            $data['service_mode'],
            $data['description'] ?? '',
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function updateSrvProfile(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
        UPDATE olt_srv_profiles
        SET
            olt_id = ?,
            profile_id = ?,
            profile_name = ?,
            eth_port_count = ?,
            service_mode = ?,
            description = ?
        WHERE id = ?
    ");

        return $stmt->execute([
            $data['olt_id'] ?? null,
            $data['profile_id'],
            $data['profile_name'],
            $data['eth_port_count'],
            $data['service_mode'],
            $data['description'] ?? '',
            $id,
        ]);
    }

    public function deleteSrvProfile(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM olt_srv_profiles WHERE id = ?");
        return $stmt->execute([$id]);
    }


    /* =========================================================
 * CONTROL BOARD VLAN BINDINGS
 * ========================================================= */

public function getControlBoardPortsByOltId(int $oltId): array
{
    $stmt = $this->db->prepare("
        SELECT
            p.id,
            p.olt_id,
            d.name AS olt_name,
            d.ip_address AS olt_ip,
            p.frame,
            p.slot,
            p.port,
            CONCAT(COALESCE(p.frame, 0), '/', COALESCE(p.slot, 0), '/', COALESCE(p.port, 0)) AS port_path,
            p.board_name,
            p.board_status,
            p.board_type,
            p.port_type,
            p.description,
            p.created_at
        FROM olt_ports p
        JOIN olt_devices d ON d.id = p.olt_id
        WHERE p.olt_id = ?
          AND (
              UPPER(COALESCE(p.board_type, '')) = 'CONTROL'
              OR p.slot IN (3, 4)
          )
        ORDER BY p.slot ASC, p.port ASC
    ");
    $stmt->execute([$oltId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

public function getVlanBindingDropdownOptions(int $oltId, string $vlanType, int $portId = 0): array
{
    $vlanType = strtoupper(trim($vlanType));

    if ($vlanType === 'MGMT') {
        $stmt = $this->db->prepare("
            SELECT
                m.id,
                m.olt_id,
                m.mgmt_vlan AS vlan_id,
                'MGMT' AS vlan_type,
                CONCAT('MGMT VLAN ', m.mgmt_vlan) AS name,
                m.description
            FROM olt_mgmt_vlans m
            WHERE m.olt_id = ?
              AND NOT EXISTS (
                  SELECT 1
                  FROM olt_port_vlan_bindings b
                  WHERE b.olt_port_id = ?
                    AND b.vlan_type = 'MGMT'
                    AND b.vlan_id = m.mgmt_vlan
              )
            ORDER BY m.mgmt_vlan ASC
        ");
        $stmt->execute([$oltId, $portId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    $stmt = $this->db->prepare("
        SELECT
            v.id,
            v.olt_id,
            v.vlan_id,
            v.vlan_type,
            v.name,
            v.description
        FROM network_vlans v
        WHERE v.olt_id = ?
          AND v.vlan_type = 'S_VLAN'
          AND NOT EXISTS (
              SELECT 1
              FROM olt_port_vlan_bindings b
              WHERE b.olt_port_id = ?
                AND b.vlan_type = 'SERVICE'
                AND b.vlan_id = v.vlan_id
          )
        ORDER BY v.vlan_id ASC
    ");
    $stmt->execute([$oltId, $portId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

public function getPortVlanBindingsByOltId(int $oltId): array
{
    if (!$this->tableExists('olt_port_vlan_bindings')) {
        return [];
    }

    $stmt = $this->db->prepare("
        SELECT
            b.*,
            p.board_name,
            p.board_type,
            p.port_type,
            CONCAT(COALESCE(p.frame, 0), '/', COALESCE(p.slot, 0), '/', COALESCE(p.port, 0)) AS port_path,
            CASE
                WHEN b.vlan_type = 'MGMT' THEN CONCAT('MGMT VLAN ', m.mgmt_vlan)
                ELSE v.name
            END AS vlan_name,
            CASE
                WHEN b.vlan_type = 'MGMT' THEN m.description
                ELSE v.description
            END AS vlan_description,
            CASE
                WHEN b.vlan_type = 'MGMT' THEN 'MGMT'
                ELSE v.vlan_type
            END AS source_vlan_type
        FROM olt_port_vlan_bindings b
        JOIN olt_ports p ON p.id = b.olt_port_id
        LEFT JOIN network_vlans v
            ON v.id = b.network_vlan_id
           AND b.vlan_type = 'SERVICE'
        LEFT JOIN olt_mgmt_vlans m
            ON m.id = b.network_vlan_id
           AND b.vlan_type = 'MGMT'
        WHERE b.olt_id = ?
        ORDER BY p.slot ASC, p.port ASC, b.vlan_type ASC, b.vlan_id ASC
    ");
    $stmt->execute([$oltId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

public function getPortVlanBindingsByPortId(int $portId): array
{
    if (!$this->tableExists('olt_port_vlan_bindings')) {
        return [];
    }

    $stmt = $this->db->prepare("
        SELECT
            b.*,
            p.board_name,
            p.board_type,
            p.port_type,
            CONCAT(COALESCE(p.frame, 0), '/', COALESCE(p.slot, 0), '/', COALESCE(p.port, 0)) AS port_path,
            CASE
                WHEN b.vlan_type = 'MGMT' THEN CONCAT('MGMT VLAN ', m.mgmt_vlan)
                ELSE v.name
            END AS vlan_name,
            CASE
                WHEN b.vlan_type = 'MGMT' THEN m.description
                ELSE v.description
            END AS vlan_description,
            CASE
                WHEN b.vlan_type = 'MGMT' THEN 'MGMT'
                ELSE v.vlan_type
            END AS source_vlan_type
        FROM olt_port_vlan_bindings b
        JOIN olt_ports p ON p.id = b.olt_port_id
        LEFT JOIN network_vlans v
            ON v.id = b.network_vlan_id
           AND b.vlan_type = 'SERVICE'
        LEFT JOIN olt_mgmt_vlans m
            ON m.id = b.network_vlan_id
           AND b.vlan_type = 'MGMT'
        WHERE b.olt_port_id = ?
        ORDER BY b.vlan_type ASC, b.vlan_id ASC
    ");
    $stmt->execute([$portId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

public function findMgmtVlanById(int $id): ?array
{
    $stmt = $this->db->prepare("
        SELECT
            id,
            olt_id,
            mgmt_vlan,
            mgmt_vlan AS vlan_id,
            'MGMT' AS vlan_type,
            CONCAT('MGMT VLAN ', mgmt_vlan) AS name,
            description,
            created_at
        FROM olt_mgmt_vlans
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$id]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

public function findNetworkVlanById(int $id): ?array
{
    $stmt = $this->db->prepare("
        SELECT *
        FROM network_vlans
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$id]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

public function findPortVlanBindingById(int $id): ?array
{
    if (!$this->tableExists('olt_port_vlan_bindings')) {
        return null;
    }

    $stmt = $this->db->prepare("
        SELECT *
        FROM olt_port_vlan_bindings
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$id]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

public function portVlanBindingExists(int $oltPortId, string $vlanType, int $vlanId): bool
{
    if (!$this->tableExists('olt_port_vlan_bindings')) {
        return false;
    }

    $stmt = $this->db->prepare("
        SELECT COUNT(*)
        FROM olt_port_vlan_bindings
        WHERE olt_port_id = ?
          AND vlan_type = ?
          AND vlan_id = ?
    ");
    $stmt->execute([$oltPortId, $vlanType, $vlanId]);

    return (int)$stmt->fetchColumn() > 0;
}

public function createPortVlanBinding(array $data): int
{
    $stmt = $this->db->prepare("
        INSERT INTO olt_port_vlan_bindings
        (
            olt_id,
            olt_port_id,
            network_vlan_id,
            vlan_id,
            vlan_type,
            frame,
            slot,
            control_board_port
        )
        VALUES
        (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $data['olt_id'],
        $data['olt_port_id'],
        $data['network_vlan_id'],
        $data['vlan_id'],
        $data['vlan_type'],
        $data['frame'],
        $data['slot'],
        $data['control_board_port'],
    ]);

    return (int)$this->db->lastInsertId();
}

public function deletePortVlanBinding(int $id): bool
{
    $stmt = $this->db->prepare("
        DELETE FROM olt_port_vlan_bindings
        WHERE id = ?
    ");

    return $stmt->execute([$id]);
}

public function getPonSvlanOptions(int $oltId, int $portId = 0): array
{
    $sql = "
        SELECT
            nv.id,
            nv.vlan_id,
            nv.name,
            nv.description,
            nv.vlan_type
        FROM network_vlans nv
        WHERE nv.olt_id = :olt_id
          AND nv.vlan_type = 'S_VLAN'
          AND (
              NOT EXISTS (
                  SELECT 1
                  FROM olt_ports op
                  WHERE op.olt_id = nv.olt_id
                    AND op.svlan = nv.vlan_id
                    AND op.id <> :port_id
              )
          )
        ORDER BY nv.vlan_id ASC
    ";

    $stmt = $this->db->prepare($sql);
    $stmt->execute([
        ':olt_id' => $oltId,
        ':port_id' => $portId,
    ]);

    return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
}

public function assignPonPortSvlan(int $portId, int $svlan): void
{
    $sql = "
        UPDATE olt_ports
        SET svlan = :svlan
        WHERE id = :id
        LIMIT 1
    ";

    $stmt = $this->db->prepare($sql);
    $stmt->execute([
        ':svlan' => $svlan,
        ':id' => $portId,
    ]);
}

public function ponSvlanAssignedToAnotherPort(int $oltId, int $svlan, int $excludePortId = 0): bool
{
    $sql = "
        SELECT COUNT(*)
        FROM olt_ports
        WHERE olt_id = :olt_id
          AND svlan = :svlan
          AND id <> :exclude_port_id
        LIMIT 1
    ";

    $stmt = $this->db->prepare($sql);
    $stmt->execute([
        ':olt_id' => $oltId,
        ':svlan' => $svlan,
        ':exclude_port_id' => $excludePortId,
    ]);

    return (int)$stmt->fetchColumn() > 0;
}

public function clearPonPortSvlan(int $portId): void
{
    $sql = "
        UPDATE olt_ports
        SET svlan = NULL
        WHERE id = :id
        LIMIT 1
    ";

    $stmt = $this->db->prepare($sql);
    $stmt->execute([
        ':id' => $portId,
    ]);
}

    /* =========================================================
     * HELPERS
     * ========================================================= */

    private function tableExists(string $tableName): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = ?
        ");
        $stmt->execute([$tableName]);

        return (int)$stmt->fetchColumn() > 0;
    }
}
