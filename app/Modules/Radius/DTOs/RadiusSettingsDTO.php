<?php

namespace App\Modules\Radius\DTOs;

final class RadiusSettingsDTO
{
    public string $host;
    public string $databaseUser;
    public string $databasePassword;
    public string $databaseName;
    public bool $active;

    public function __construct(array $data = [], ?array $existing = null)
    {
        $this->host = trim((string)($data['host'] ?? ''));
        $this->databaseUser = trim((string)($data['db_user'] ?? $data['user'] ?? ''));
        $this->databasePassword = (string)($data['db_password'] ?? $data['password'] ?? '');
        if ($this->databasePassword === '' && $existing !== null) {
            $this->databasePassword = (string)($existing['db_password'] ?? '');
        }
        $this->databaseName = trim((string)($data['db_name'] ?? $data['name'] ?? ''));
        $this->active = filter_var($data['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    public function toPersistenceArray(): array
    {
        return [
            'host' => $this->host,
            'db_user' => $this->databaseUser,
            'db_password' => $this->databasePassword,
            'db_name' => $this->databaseName,
            'is_active' => $this->active ? 1 : 0,
        ];
    }

    public function toAuditArray(): array
    {
        $data = $this->toPersistenceArray();
        $data['db_password'] = '[REDACTED]';
        return $data;
    }
}
