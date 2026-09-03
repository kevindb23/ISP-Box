<?php

namespace App\Modules\PaymentGateway\DTOs;

final class PaymentGatewayCommandDTO
{
    public function __construct(
        public int $invoiceId = 0,
        public string $reference = ''
    ) {
        $this->reference = trim($this->reference);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            invoiceId: (int)($data['invoice_id'] ?? 0),
            reference: (string)($data['reference'] ?? $data['gateway_reference'] ?? '')
        );
    }
}
