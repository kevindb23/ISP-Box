<?php

namespace App\Modules\PaymentGateway\Validators;

use App\Modules\PaymentGateway\DTOs\GatewaySettingsDTO;
use App\Modules\PaymentGateway\DTOs\PaymentGatewayCommandDTO;
use App\Modules\PaymentGateway\DTOs\PayMongoWebhookDTO;

final class PaymentGatewayValidator
{
    public function settings(GatewaySettingsDTO $dto): array
    {
        $errors = [];
        if ($dto->mode !== null && !in_array($dto->mode, ['test', 'live'], true)) {
            $errors['paymongo_mode'] = 'Payment gateway mode must be test or live.';
        }
        return $errors;
    }

    public function checkout(PaymentGatewayCommandDTO $dto): array
    {
        return $dto->invoiceId > 0 ? [] : ['invoice_id' => 'Invoice is required.'];
    }

    public function webhook(PayMongoWebhookDTO $dto): array
    {
        $errors = [];
        if ($dto->eventType === '') $errors['event_type'] = 'Webhook event type is missing.';
        if ($dto->checkoutId === '') $errors['reference'] = 'Webhook reference is missing.';
        return $errors;
    }
}
