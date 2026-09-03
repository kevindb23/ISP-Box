<?php

namespace App\Modules\Billing\DTOs;

final class BillingSettingsDTO
{
    private const ALLOWED = [
        'invoice_prefix', 'payment_prefix', 'adjustment_prefix', 'default_due_days',
        'currency', 'tax_enabled', 'tax_rate', 'grace_period_days', 'auto_suspend_enabled',
    ];

    public function __construct(private array $values)
    {
        $this->values = array_intersect_key($values, array_flip(self::ALLOWED));
    }

    public function toArray(): array { return $this->values; }
}
