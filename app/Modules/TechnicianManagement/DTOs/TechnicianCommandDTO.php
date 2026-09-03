<?php

namespace App\Modules\TechnicianManagement\DTOs;

final class TechnicianCommandDTO
{
    public function __construct(private array $data)
    {
        foreach (['user_id', 'work_order_id', 'technician_id'] as $field) {
            if (isset($this->data[$field])) $this->data[$field] = (int)$this->data[$field];
        }
        foreach (['status', 'skill_level'] as $field) {
            if (isset($this->data[$field])) $this->data[$field] = strtoupper(trim((string)$this->data[$field]));
        }
        foreach ($this->data as $key => $value) {
            if (is_string($value) && !in_array($key, ['status', 'skill_level'], true)) $this->data[$key] = trim($value);
        }
    }

    public function toArray(): array { return $this->data; }
}
