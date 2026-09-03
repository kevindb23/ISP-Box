<?php

namespace App\Modules\WorkOrders\Entities;

final class WorkOrder
{
    public function __construct(private array $attributes)
    {
        foreach (['id', 'ticket_id', 'subscriber_id', 'service_id', 'assigned_user_id'] as $field) {
            if (isset($this->attributes[$field])) $this->attributes[$field] = (int)$this->attributes[$field];
        }
    }

    public function toArray(): array { return $this->attributes; }
}
