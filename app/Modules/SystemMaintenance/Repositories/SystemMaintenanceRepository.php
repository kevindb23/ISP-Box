<?php

namespace App\Modules\SystemMaintenance\Repositories;

use Framework\DatabaseConnection;
use PDO;

class SystemMaintenanceRepository
{
    private PDO $db;

    public function __construct(DatabaseConnection $connection)
    {
        $this->db = $connection->get();
    }

    public function get(): array
    {
        $stmt = $this->db->query(
            'SELECT id, enabled, message, starts_at, ends_at, created_at, updated_at
             FROM system_maintenance
             WHERE id = 1
             LIMIT 1'
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: [
            'id' => 1,
            'enabled' => 0,
            'message' => '',
            'starts_at' => null,
            'ends_at' => null,
            'created_at' => null,
            'updated_at' => null,
        ];
    }

    public function save(array $data): array
    {
        $stmt = $this->db->prepare(
            'INSERT INTO system_maintenance (id, enabled, message, starts_at, ends_at)
             VALUES (1, :enabled, :message, :starts_at, :ends_at)
             ON DUPLICATE KEY UPDATE
                 enabled = VALUES(enabled),
                 message = VALUES(message),
                 starts_at = VALUES(starts_at),
                 ends_at = VALUES(ends_at),
                 updated_at = CURRENT_TIMESTAMP'
        );

        $stmt->execute([
            'enabled' => $data['enabled'] ? 1 : 0,
            'message' => $data['message'],
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
        ]);

        return $this->get();
    }
}
