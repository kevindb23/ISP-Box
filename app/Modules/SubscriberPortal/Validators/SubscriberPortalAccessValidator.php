<?php

namespace App\Modules\SubscriberPortal\Validators;

use App\Modules\SubscriberPortal\DTOs\SubscriberPortalSessionDTO;
use Exception;

class SubscriberPortalAccessValidator
{
    public function validateSession(SubscriberPortalSessionDTO $session): void
    {
        if (!$session->isValid()) {
            throw new Exception('You must be logged in to access the subscriber portal.');
        }

        if (!$session->isSubscriber()) {
            throw new Exception('Subscriber portal access is only allowed for subscriber accounts.');
        }
    }

    public function validateSubscriberId(int $subscriberId): void
    {
        if ($subscriberId <= 0) {
            throw new Exception('Subscriber account is not linked to this user.');
        }
    }

    public function validateInvoiceOwnership(array $invoice, int $subscriberId): void
    {
        if (empty($invoice)) {
            throw new Exception('Invoice not found.');
        }

        $invoiceSubscriberId = (int)($invoice['subscriber_id'] ?? 0);

        if ($invoiceSubscriberId <= 0 || $invoiceSubscriberId !== $subscriberId) {
            throw new Exception('You are not allowed to view this invoice.');
        }
    }

    public function validateServiceOwnership(array $service, int $subscriberId): void
    {
        if (empty($service)) {
            throw new Exception('Service not found.');
        }

        $serviceSubscriberId = (int)($service['subscriber_id'] ?? 0);

        if ($serviceSubscriberId <= 0 || $serviceSubscriberId !== $subscriberId) {
            throw new Exception('You are not allowed to view this service.');
        }
    }
}
