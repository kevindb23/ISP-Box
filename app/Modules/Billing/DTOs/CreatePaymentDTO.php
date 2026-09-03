<?php

namespace App\Modules\Billing\DTOs;

final class CreatePaymentDTO
{
    public function __construct(public array $data)
    {
        $this->data['invoice_id'] = (int)($data['invoice_id'] ?? 0);
        $this->data['amount'] = (float)($data['amount'] ?? 0);
        $this->data['method'] = strtoupper(trim((string)($data['method'] ?? 'CASH')));
        $this->data['payment_status'] = strtoupper(trim((string)($data['payment_status'] ?? 'POSTED')));
    }

    public function toArray(): array { return $this->data; }
}
