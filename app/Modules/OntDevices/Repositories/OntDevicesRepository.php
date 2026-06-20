<?php

namespace App\Modules\OntDevices\Repositories;

use Framework\DatabaseConnection;
use PDO;

class OntDevicesRepository
{
    private PDO $db;

    public function __construct(DatabaseConnection $database)
    {
        $this->db = $database->get();
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

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
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
        return $row ?: null;
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
                mac_address,
                status,
                equipment_id,
                subscriber_id
            )
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $data['serial_number'],
            $data['model'] ?? null,
            $data['vendor'] ?? null,
            $data['mac_address'] ?? null,
            $data['status'] ?? 'UNASSIGNED',
            $data['equipment_id'] ?? null,
            $data['subscriber_id'] ?? null,
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
                mac_address = ?,
                status = ?,
                equipment_id = ?,
                subscriber_id = ?
            WHERE id = ?
        ");

        return $stmt->execute([
            $data['serial_number'],
            $data['model'] ?? null,
            $data['vendor'] ?? null,
            $data['mac_address'] ?? null,
            $data['status'] ?? 'UNASSIGNED',
            $data['equipment_id'] ?? null,
            $data['subscriber_id'] ?? null,
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

        return $stmt->execute([$id]);
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
        return $row ?: null;
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
        return $row ?: null;
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
}