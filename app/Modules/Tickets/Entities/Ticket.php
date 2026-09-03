<?php

namespace App\Modules\Tickets\Entities;

final class Ticket
{
    public function __construct(private array $attributes)
    {
        foreach (['id', 'subscriber_id', 'service_id', 'assigned_user_id', 'work_order_id'] as $field) {
            if (isset($this->attributes[$field])) $this->attributes[$field] = (int)$this->attributes[$field];
        }
    }

    public function toArray(): array { return $this->attributes; }
}
