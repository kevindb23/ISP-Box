<?php

namespace App\Modules\Billing\DTOs;

final class CreateAdjustmentDTO
{
    public function __construct(public array $data)
    {
        $this->data['invoice_id'] = (int)($data['invoice_id'] ?? 0);
        $this->data['amount'] = (float)($data['amount'] ?? 0);
        $this->data['adjustment_type'] = strtoupper(trim((string)($data['adjustment_type'] ?? 'CREDIT')));
        $this->data['reason'] = trim((string)($data['reason'] ?? ''));
        $this->data['status'] = strtoupper(trim((string)($data['status'] ?? 'POSTED')));
    }

    public function toArray(): array { return $this->data; }
}
