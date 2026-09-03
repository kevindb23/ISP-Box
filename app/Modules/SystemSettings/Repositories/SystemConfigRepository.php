<?php

namespace App\Modules\SystemSettings\Repositories;

use App\Modules\SystemSettings\Entities\SystemConfig;
use PDO;
use Throwable;

class SystemConfigRepository
{
    public function __construct(private PDO $db)
    {
    }

    /** @return list<SystemConfig> */
    public function all(): array
    {
        $stmt = $this->db->query('SELECT config_key, config_value, description FROM system_config ORDER BY config_key');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_map(static fn(array $row): SystemConfig => new SystemConfig(
            (string)$row['config_key'],
            (string)($row['config_value'] ?? ''),
            isset($row['description']) ? (string)$row['description'] : null
        ), $rows);
    }

    public function saveMany(array $values): void
    {
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare(
                'INSERT INTO system_config (config_key, config_value) VALUES (:config_key, :config_value) '
                . 'ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)'
            );
            foreach ($values as $key => $value) {
                $stmt->execute([':config_key' => (string)$key, ':config_value' => (string)$value]);
            }
            if ($ownsTransaction) $this->db->commit();
        } catch (Throwable $e) {
            if ($ownsTransaction && $this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function value(string $key, string $default = ''): string
    {
        $stmt = $this->db->prepare('SELECT config_value FROM system_config WHERE config_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        return $value === false ? $default : (string)$value;
    }
}
