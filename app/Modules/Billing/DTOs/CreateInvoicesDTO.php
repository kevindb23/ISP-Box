<?php

namespace App\Modules\Billing\DTOs;

final class CreateInvoicesDTO
{
    public function __construct(
        public int $serviceId,
        public array $items = [],
        public float $discountAmount = 0,
        public float $taxAmount = 0,
        public ?string $billingPeriodStart = null,
        public ?string $billingPeriodEnd = null,
        public ?string $issueDate = null,
        public ?string $dueDate = null,
        public ?string $invoiceNumber = null,
        public ?string $notes = null
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            serviceId: (int)($data['service_id'] ?? 0),
            items: is_array($data['items'] ?? null) ? $data['items'] : [],
            discountAmount: (float)($data['discount_amount'] ?? 0),
            taxAmount: (float)($data['tax_amount'] ?? 0),
            billingPeriodStart: self::nullable($data['billing_period_start'] ?? null),
            billingPeriodEnd: self::nullable($data['billing_period_end'] ?? null),
            issueDate: self::nullable($data['issue_date'] ?? null),
            dueDate: self::nullable($data['due_date'] ?? null),
            invoiceNumber: self::nullable($data['invoice_no'] ?? null),
            notes: self::nullable($data['notes'] ?? null)
        );
    }

    public function toArray(): array
    {
        return [
            'service_id' => $this->serviceId,
            'items' => $this->items,
            'discount_amount' => $this->discountAmount,
            'tax_amount' => $this->taxAmount,
            'billing_period_start' => $this->billingPeriodStart,
            'billing_period_end' => $this->billingPeriodEnd,
            'issue_date' => $this->issueDate,
            'due_date' => $this->dueDate,
            'invoice_no' => $this->invoiceNumber,
            'notes' => $this->notes,
        ];
    }

    private static function nullable($value): ?string
    {
        $value = trim((string)$value);
        return $value !== '' ? $value : null;
    }
}
