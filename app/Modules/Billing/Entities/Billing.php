<?php

namespace App\Modules\Billing\Entities;

final class Billing
{
    public function __construct(private array $attributes)
    {
        foreach (['id', 'invoice_id', 'subscriber_id', 'service_id', 'payment_id'] as $field) {
            if (isset($this->attributes[$field])) $this->attributes[$field] = (int)$this->attributes[$field];
        }
    }

    public function toArray(): array { return $this->attributes; }
}
