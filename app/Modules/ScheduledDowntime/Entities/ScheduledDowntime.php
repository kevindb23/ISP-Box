<?php

namespace App\Modules\ScheduledDowntime\Entities;

class ScheduledDowntime
{
    private array $attributes;

    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    public function toArray(): array
    {
        return [
            'id' => (int)($this->attributes['id'] ?? 0),
            'title' => (string)($this->attributes['title'] ?? ''),
            'message' => (string)($this->attributes['message'] ?? ''),
            'starts_at' => (string)($this->attributes['starts_at'] ?? ''),
            'ends_at' => (string)($this->attributes['ends_at'] ?? ''),
            'enabled' => (int)($this->attributes['enabled'] ?? 0),
            'created_by' => isset($this->attributes['created_by']) ? (int)$this->attributes['created_by'] : null,
            'created_at' => (string)($this->attributes['created_at'] ?? ''),
            'updated_at' => (string)($this->attributes['updated_at'] ?? ''),
        ];
    }
}
