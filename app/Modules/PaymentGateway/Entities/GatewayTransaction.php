<?php

namespace App\Modules\PaymentGateway\Entities;

final class GatewayTransaction
{
    public function __construct(private array $attributes)
    {
        unset(
            $this->attributes['raw_request'],
            $this->attributes['raw_response'],
            $this->attributes['raw_webhook']
        );
        foreach (['id', 'payment_id', 'invoice_id', 'subscriber_id'] as $field) {
            if (isset($this->attributes[$field])) $this->attributes[$field] = (int)$this->attributes[$field];
        }
    }

    public function toArray(): array
    {
        return $this->attributes;
    }
}
