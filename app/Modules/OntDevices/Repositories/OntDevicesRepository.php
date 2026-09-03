<?php

namespace App\Modules\OntDevices\Repositories;

use App\Infrastructure\Security\SecretCipher;
use App\Modules\Radius\Repositories\RadiusSettingsRepository;
use Framework\DatabaseConnection;
use PDO;
use Throwable;

class OntDevicesRepository
{
    private PDO $db;
    private ?array $activeRadiusIps = null;

    public function __construct(
        DatabaseConnection $database,
        private SecretCipher $secrets,
        private RadiusSettingsRepository $radiusSettings
    )
    {
        $this->db = $database->get();
    }

    public function findActiveRadiusIpByUsername(string $username): ?string
    {
        if ($this->activeRadiusIps === null) {
            $this->activeRadiusIps = [];
            try {
                $radius = $this->radiusSettings->getConnectionConfig();
                $radiusDb = new PDO(
                    "mysql:host={$radius['host']};dbname={$radius['name']};charset=utf8mb4",
                    $radius['user'],
                    $radius['pass'],
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
                );
                $rows = $radiusDb->query("
                    SELECT username, framedipaddress
                    FROM radacct
                    WHERE acctstoptime IS NULL
                      AND framedipaddress IS NOT NULL
                      AND framedipaddress <> ''
                    ORDER BY radacctid DESC
                ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
                foreach ($rows as $row) {
                    $key = trim((string)($row['username'] ?? ''));
                    if ($key !== '' && !isset($this->activeRadiusIps[$key])) {
                        $this->activeRadiusIps[$key] = trim((string)$row['framedipaddress']);
                    }
                }
            } catch (Throwable $e) {
                error_log('[OntDevicesRepository] Active RADIUS WAN lookup failed: ' . $e->getMessage());
            }
        }

        $ip = $this->activeRadiusIps[trim($username)] ?? null;
        return is_string($ip) && $ip !== '' ? $ip : null;
    }

    /*
    |--------------------------------------------------------------------------
    | INVENTORY
    |--------------------------------------------------------------------------
    */

    public function allInventory(): array
    {
        $stmt = $this->db->query("
            SELECT
                od.*,
                s.full_name AS subscriber_name
            FROM ont_devices od
            LEFT JOIN subscribers s
                ON s.id = od.subscriber_id
            ORDER BY od.id DESC
        ");

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_map([$this, 'withoutDeprecatedFields'], $rows);
    }

    public function getAll(): array
    {
        return $this->allInventory();
    }

    public function findInventoryById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                od.*,
                s.full_name AS subscriber_name
            FROM ont_devices od
            LEFT JOIN subscribers s
                ON s.id = od.subscriber_id
            WHERE od.id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->withoutDeprecatedFields($row) : null;
    }

    public function findById(int $id): ?array
    {
        return $this->findInventoryById($id);
    }

    public function createInventory(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO ont_devices
            (
                serial_number,
                model,
                vendor,
                status,
                subscriber_id,
                olt_id,
                frame,
                slot,
                port,
                ont_id
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $data['serial_number'],
            $data['model'] ?? null,
            $data['vendor'] ?? null,
            $data['status'] ?? 'UNASSIGNED',
            $data['subscriber_id'] ?? null,
            $data['olt_id'] ?? null,
            $data['frame'] ?? null,
            $data['slot'] ?? null,
            $data['port'] ?? null,
            $data['ont_id'] ?? null,
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function create(array $data): int
    {
        return $this->createInventory($data);
    }

    public function addToInventory(array $data): int
    {
        return $this->createInventory($data);
    }

    public function updateInventory(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE ont_devices
            SET
                serial_number = ?,
                model = ?,
                vendor = ?,
                status = ?,
                subscriber_id = ?,
                olt_id = COALESCE(?, olt_id),
                frame = COALESCE(?, frame),
                slot = COALESCE(?, slot),
                port = COALESCE(?, port),
                ont_id = COALESCE(?, ont_id)
            WHERE id = ?
        ");

        return $stmt->execute([
            $data['serial_number'],
            $data['model'] ?? null,
            $data['vendor'] ?? null,
            $data['status'] ?? 'UNASSIGNED',
            $data['subscriber_id'] ?? null,
            $data['olt_id'] ?? null,
            $data['frame'] ?? null,
            $data['slot'] ?? null,
            $data['port'] ?? null,
            $data['ont_id'] ?? null,
            $id,
        ]);
    }

    public function update(array $data): bool
    {
        $id = (int)($data['id'] ?? 0);

        if ($id <= 0) {
            return false;
        }

        return $this->updateInventory($id, $data);
    }

    public function deleteInventory(int $id): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM ont_devices
            WHERE id = ?
        ");

        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        return $this->deleteInventory($id);
    }

    public function existsBySerial(string $serial, ?int $ignoreId = null): bool
    {
        $sql = "
            SELECT COUNT(*)
            FROM ont_devices
            WHERE UPPER(TRIM(serial_number)) = UPPER(TRIM(?))
        ";
        $params = [$serial];

        if ($ignoreId) {
            $sql .= " AND id <> ? ";
            $params[] = $ignoreId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn() > 0;
    }

    public function subscriberExists(int $subscriberId): bool
    {
        if ($subscriberId <= 0) return false;
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM subscribers WHERE id = ?');
        $stmt->execute([$subscriberId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function getSubscriberOptions(): array
    {
        $stmt = $this->db->query("
            SELECT id, full_name, account_number, status
            FROM subscribers
            ORDER BY full_name ASC
            LIMIT 500
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findBySerial(string $serial): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                od.*,
                s.full_name AS subscriber_name
            FROM ont_devices od
            LEFT JOIN subscribers s
                ON s.id = od.subscriber_id
            WHERE UPPER(TRIM(od.serial_number)) = UPPER(TRIM(?))
            LIMIT 1
        ");
        $stmt->execute([$serial]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->withoutDeprecatedFields($row) : null;
    }

    public function upsertAcsSnapshot(array $device): void
    {
        $serial = strtoupper(trim((string)($device['serial_number'] ?? '')));
        if ($serial === '' || $serial === '-') return;

        $payload = [
            $device['wan_ip'] === '-' ? null : ($device['wan_ip'] ?? null),
            $device['acs_status'] ?? null,
            $device['last_seen'] ?? null,
            $device['firmware_version'] === '-' ? null : ($device['firmware_version'] ?? null),
            $device['uptime'] ?? null,
            $serial,
        ];

        $stmt = $this->db->prepare("
            UPDATE ont_acs
            SET wan_ip = ?, status = ?, last_seen = ?, firmware_version = ?, uptime = ?
            WHERE UPPER(TRIM(serial_number)) = ?
        ");
        $stmt->execute($payload);

        if ($stmt->rowCount() > 0) return;

        $exists = $this->db->prepare('SELECT id FROM ont_acs WHERE UPPER(TRIM(serial_number)) = ? LIMIT 1');
        $exists->execute([$serial]);
        if ($exists->fetchColumn()) return;

        $insert = $this->db->prepare("
            INSERT INTO ont_acs (serial_number, wan_ip, status, last_seen, firmware_version, uptime)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $insert->execute([
            $serial,
            $device['wan_ip'] === '-' ? null : ($device['wan_ip'] ?? null),
            $device['acs_status'] ?? null,
            $device['last_seen'] ?? null,
            $device['firmware_version'] === '-' ? null : ($device['firmware_version'] ?? null),
            $device['uptime'] ?? null,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DISCOVERY / AUTOFIND
    |--------------------------------------------------------------------------
    */

    public function allAutofind(): array
    {
        $stmt = $this->db->query("
            SELECT *
            FROM ont_autofind
            ORDER BY COALESCE(last_seen, autofind_time, created_at) DESC, id DESC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getDiscovery(): array
    {
        return $this->allAutofind();
    }

    public function findDiscoveryOlt(?int $oltId = null): ?array
    {
        $sql = "
            SELECT id, name, ip_address, username, password
            FROM olt_devices
            WHERE ip_address IS NOT NULL
              AND username IS NOT NULL
              AND password IS NOT NULL
        ";
        $params = [];
        if ($oltId !== null && $oltId > 0) {
            $sql .= " AND id = ? ";
            $params[] = $oltId;
        }
        $sql .= "
            ORDER BY id ASC
            LIMIT 1
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        $row['password'] = $this->secrets->decrypt($row['password'] ?? null);
        return $row;
    }

    public function findDiscoveryBySerial(string $serial): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM ont_autofind
            WHERE UPPER(TRIM(serial_number)) = UPPER(TRIM(?))
            LIMIT 1
        ");
        $stmt->execute([$serial]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findOpticalMappingBySerial(string $serial): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                od.id AS inventory_id,
                od.serial_number,
                COALESCE(od.olt_id, spb.olt_id) AS olt_id,
                COALESCE(od.frame, op.frame, spj.frame) AS frame,
                COALESCE(od.slot, op.slot, spj.slot) AS slot,
                COALESCE(od.port, op.port, spj.port) AS port,
                COALESCE(od.ont_id, spb.ont_assigned_id, spj.ont_assigned_id) AS ont_id
            FROM ont_devices od
            LEFT JOIN service_provisioning_bindings spb ON spb.ont_id = od.id
            LEFT JOIN olt_ports op ON op.id = spb.olt_port_id
            LEFT JOIN service_provisioning_jobs spj ON spj.ont_id = od.id
            WHERE UPPER(TRIM(od.serial_number)) = UPPER(TRIM(?))
            ORDER BY spb.id DESC, spj.id DESC
            LIMIT 1
        ");
        $stmt->execute([$serial]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function saveAutofind(array $data): bool
    {
        $stmt = $this->db->prepare("
            INSERT INTO ont_autofind
            (
                serial_number,
                vendor,
                vendor_code,
                model,
                fsp,
                frame,
                slot,
                port,
                autofind_time,
                status,
                last_seen
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                vendor = VALUES(vendor),
                vendor_code = VALUES(vendor_code),
                model = VALUES(model),
                fsp = VALUES(fsp),
                frame = VALUES(frame),
                slot = VALUES(slot),
                port = VALUES(port),
                autofind_time = VALUES(autofind_time),
                status = VALUES(status),
                last_seen = VALUES(last_seen)
        ");

        return $stmt->execute([
            $data['serial_number'] ?? null,
            $data['vendor'] ?? null,
            $data['vendor_code'] ?? null,
            $data['model'] ?? null,
            $data['fsp'] ?? null,
            $data['frame'] ?? null,
            $data['slot'] ?? null,
            $data['port'] ?? null,
            $data['autofind_time'] ?? null,
            $data['status'] ?? 'NEW',
            $data['last_seen'] ?? null,
        ]);
    }

    public function saveDiscovery(array $data): bool
    {
        $fsp = trim((string)($data['fsp'] ?? ''));
        $frame = $data['frame'] ?? null;
        $slot = $data['slot'] ?? null;
        $port = $data['port'] ?? null;

        if (($frame === null || $slot === null || $port === null) && $fsp !== '') {
            if (preg_match('/^(\d+)\/(\d+)\/(\d+)$/', $fsp, $m)) {
                $frame = (int)$m[1];
                $slot = (int)$m[2];
                $port = (int)$m[3];
            }
        }

        return $this->saveAutofind([
            'serial_number' => $data['serial_number'] ?? null,
            'vendor' => $data['vendor'] ?? null,
            'vendor_code' => $data['vendor_code'] ?? null,
            'model' => $data['model'] ?? null,
            'fsp' => $fsp !== '' ? $fsp : null,
            'frame' => $frame,
            'slot' => $slot,
            'port' => $port,
            'autofind_time' => $data['autofind_time'] ?? ($data['last_seen'] ?? null),
            'status' => $data['status'] ?? 'NEW',
            'last_seen' => $data['last_seen'] ?? null,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | OLT / OPTICAL
    |--------------------------------------------------------------------------
    */

    public function findOltById(int $oltId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM olt_devices
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$oltId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $row['password'] = $this->secrets->decrypt($row['password'] ?? null);
        }
        return $row ?: null;
    }

    public function getOpticalCandidateOlts(?int $preferredOltId = null): array
    {
        $stmt = $this->db->query("\n            SELECT *\n            FROM olt_devices\n            WHERE ip_address IS NOT NULL\n              AND username IS NOT NULL\n              AND password IS NOT NULL\n            ORDER BY id ASC\n        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$row) {
            $row['password'] = $this->secrets->decrypt($row['password'] ?? null);
        }
        unset($row);

        if ($preferredOltId !== null && $preferredOltId > 0) {
            usort($rows, static fn(array $a, array $b): int =>
                ((int)$b['id'] === $preferredOltId ? 1 : 0)
                <=> ((int)$a['id'] === $preferredOltId ? 1 : 0)
            );
        }
        return $rows;
    }

    public function updateOpticalMappingById(
        int $id,
        int $oltId,
        int $frame,
        int $slot,
        int $port,
        int $ontId
    ): bool {
        $stmt = $this->db->prepare("\n            UPDATE ont_devices\n            SET olt_id = ?, frame = ?, slot = ?, port = ?, ont_id = ?\n            WHERE id = ?\n        ");
        return $stmt->execute([$oltId, $frame, $slot, $port, $ontId, $id]);
    }

    public function updateLastOpticalById(int $id, array $optical): bool
    {
        $stmt = $this->db->prepare("
            UPDATE ont_devices
            SET
                last_rx_power_dbm = ?,
                last_tx_power_dbm = ?,
                last_olt_rx_ont_power_dbm = ?,
                last_temperature_c = ?,
                last_voltage_v = ?,
                last_laser_bias_current_ma = ?,
                last_distance_m = ?,
                last_optical_polled_at = NOW()
            WHERE id = ?
        ");

        return $stmt->execute([
            $optical['rx_power_dbm'] ?? null,
            $optical['tx_power_dbm'] ?? null,
            $optical['olt_rx_ont_power_dbm'] ?? null,
            $optical['temperature_c'] ?? null,
            $optical['voltage_v'] ?? null,
            $optical['laser_bias_current_ma'] ?? null,
            $optical['distance_m'] ?? null,
            $id
        ]);
    }

    public function getCachedOpticalBySerial(string $serial): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                id,
                serial_number,
                last_rx_power_dbm,
                last_tx_power_dbm,
                last_olt_rx_ont_power_dbm,
                last_temperature_c,
                last_voltage_v,
                last_laser_bias_current_ma,
                last_distance_m,
                last_optical_polled_at
            FROM ont_devices
            WHERE UPPER(TRIM(serial_number)) = UPPER(TRIM(?))
            LIMIT 1
        ");
        $stmt->execute([$serial]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function withoutDeprecatedFields(array $row): array
    {
        unset($row['equipment_id'], $row['mac_address']);
        return $row;
    }
}
