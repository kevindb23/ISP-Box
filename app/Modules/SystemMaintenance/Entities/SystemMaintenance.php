<?php

namespace App\Modules\SystemMaintenance\Entities;

class SystemMaintenance
{
    private array $attributes;

    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    public function toArray(): array
    {
        return [
            'id' => (int)($this->attributes['id'] ?? 1),
            'enabled' => (int)($this->attributes['enabled'] ?? 0) === 1,
            'message' => (string)($this->attributes['message'] ?? ''),
            'starts_at' => $this->attributes['starts_at'] ?? null,
            'ends_at' => $this->attributes['ends_at'] ?? null,
            'created_at' => $this->attributes['created_at'] ?? null,
            'updated_at' => $this->attributes['updated_at'] ?? null,
        ];
    }
}
