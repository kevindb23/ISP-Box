<?php

namespace App\Modules\Billing\Repositories;

use PDO;

class BillingSettingsRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function all(): array
    {
        $stmt = $this->db->query("
            SELECT setting_key, setting_value, description
            FROM billing_settings
            ORDER BY setting_key ASC
        ");

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $settings = [];

        foreach ($rows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        return $settings;
    }

    public function listRows(): array
    {
        $stmt = $this->db->query("
            SELECT *
            FROM billing_settings
            ORDER BY setting_key ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $stmt = $this->db->prepare("
            SELECT setting_value
            FROM billing_settings
            WHERE setting_key = :setting_key
            LIMIT 1
        ");

        $stmt->execute([':setting_key' => $key]);

        $value = $stmt->fetchColumn();

        return $value !== false ? $value : $default;
    }

    public function save(string $key, mixed $value, ?string $description = null): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO billing_settings (
                setting_key,
                setting_value,
                description
            ) VALUES (
                :setting_key,
                :setting_value,
                :description
            )
            ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                description = COALESCE(VALUES(description), description)
        ");

        $stmt->execute([
            ':setting_key' => $key,
            ':setting_value' => is_scalar($value) ? (string) $value : json_encode($value),
            ':description' => $description,
        ]);
    }

    public function saveMany(array $settings): void
    {
        foreach ($settings as $key => $value) {
            $this->save((string) $key, $value);
        }
    }
}