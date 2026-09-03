<?php

namespace App\Modules\PaymentGateway\DTOs;

final class PayMongoWebhookDTO
{
    public string $eventType;
    public string $checkoutId;

    public function __construct(
        public array $payload,
        public string $rawPayload = '',
        public string $signature = ''
    )
    {
        $this->signature = trim($this->signature);
        $this->eventType = trim((string)($payload['data']['attributes']['type'] ?? ''));
        $resource = $payload['data']['attributes']['data'] ?? [];
        $this->checkoutId = trim((string)($resource['id'] ?? ''));
    }
}
