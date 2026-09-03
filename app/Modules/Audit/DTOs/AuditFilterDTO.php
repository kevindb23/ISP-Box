<?php

namespace App\Modules\Audit\DTOs;

class AuditFilterDTO
{
    public ?string $module = null;
    public ?string $action = null;
    public ?string $username = null;
    public ?string $dateFrom = null;
    public ?string $dateTo = null;
    public int $limit = 500;
    public int $offset = 0;

    public function __construct(array $data = [])
    {
        $this->module = $this->nullable($data['module'] ?? null, true);
        $this->action = $this->nullable($data['action'] ?? null, true);
        $this->username = $this->nullable($data['username'] ?? $data['search'] ?? null);
        $this->dateFrom = $this->nullable($data['date_from'] ?? null);
        $this->dateTo = $this->nullable($data['date_to'] ?? null);
        $this->limit = max(1, min(500, (int)($data['limit'] ?? 500)));
        $this->offset = max(0, (int)($data['offset'] ?? 0));
    }

    public function toArray(): array
    {
        return [
            'module' => $this->module,
            'action' => $this->action,
            'username' => $this->username,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'limit' => $this->limit,
            'offset' => $this->offset,
        ];
    }

    private function nullable($value, bool $upper = false): ?string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }

        return $upper ? strtoupper($value) : $value;
    }
}
