<?php

namespace App\Modules\TechnicianPortal\DTOs;

final class TechnicianPortalCommandDTO
{
    public function __construct(private array $data)
    {
        foreach (['work_order_id', 'task_id', 'attachment_id', 'photo_id'] as $field) {
            if (isset($this->data[$field])) $this->data[$field] = (int)$this->data[$field];
        }
        if (isset($this->data['status'])) $this->data['status'] = strtoupper(trim((string)$this->data['status']));
        foreach (['note', 'completion_notes', 'latitude', 'longitude'] as $field) {
            if (isset($this->data[$field])) $this->data[$field] = trim((string)$this->data[$field]);
        }
    }

    public function toArray(): array { return $this->data; }
}
