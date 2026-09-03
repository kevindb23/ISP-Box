<?php

namespace App\Modules\Billing\Repositories;

use App\Infrastructure\Security\SecretCipher;
use PDO;

class BillingSettingsRepository
{
    private PDO $db;

    public function __construct(PDO $db, private SecretCipher $secrets)
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
            $key = (string)$row['setting_key'];
            $settings[$key] = $this->isSensitiveSetting($key)
                ? $this->secrets->decrypt($row['setting_value'])
                : $row['setting_value'];
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

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$row) {
            if ($this->isSensitiveSetting((string)$row['setting_key'])) {
                $row['setting_value'] = $this->secrets->decrypt($row['setting_value']);
            }
        }
        unset($row);
        return $rows;
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

        if ($value === false) return $default;
        return $this->isSensitiveSetting($key) ? $this->secrets->decrypt((string)$value) : $value;
    }

    public function save(string $key, mixed $value, ?string $description = null): void
    {
        $storedValue = is_scalar($value) ? (string)$value : json_encode($value);
        if ($this->isSensitiveSetting($key)) $storedValue = $this->secrets->encrypt($storedValue);
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
            ':setting_value' => $storedValue,
            ':description' => $description,
        ]);
    }

    public function saveMany(array $settings): void
    {
        foreach ($settings as $key => $value) {
            $this->save((string) $key, $value);
        }
    }

    private function isSensitiveSetting(string $key): bool
    {
        return in_array($key, [
            'xendit_secret_key_live', 'xendit_secret_key_test',
            'xendit_webhook_token_live', 'xendit_webhook_token_test',
        ], true);
    }
}
