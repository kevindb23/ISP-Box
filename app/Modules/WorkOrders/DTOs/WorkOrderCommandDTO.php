<?php

namespace App\Modules\WorkOrders\DTOs;

final class WorkOrderCommandDTO
{
    public function __construct(private array $data)
    {
        foreach (['ticket_id', 'work_order_id', 'assigned_user_id', 'task_id'] as $field) {
            if (isset($this->data[$field])) $this->data[$field] = (int)$this->data[$field];
        }
        if (isset($this->data['status'])) $this->data['status'] = strtoupper(trim((string)$this->data['status']));
        if (isset($this->data['note'])) $this->data['note'] = trim((string)$this->data['note']);
    }

    public function toArray(): array { return $this->data; }
}
