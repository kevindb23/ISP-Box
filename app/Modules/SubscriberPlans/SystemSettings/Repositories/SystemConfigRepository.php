<?php

namespace App\Modules\SubscriberPlans\SystemSettings\Repositories;

use PDO;

class SystemConfigRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getAll(): array
    {
        $stmt = $this->db->query("SELECT config_key, config_value FROM system_config");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $data = [];
        foreach ($rows as $row) {
            $data[$row['config_key']] = $row['config_value'];
        }

        return $data;
    }

    public function set(string $key, string $value): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO system_config (config_key, config_value)
            VALUES (:k, :v)
            ON DUPLICATE KEY UPDATE config_value = :v
        ");

        $stmt->execute([
            'k' => $key,
            'v' => $value
        ]);
    }
}