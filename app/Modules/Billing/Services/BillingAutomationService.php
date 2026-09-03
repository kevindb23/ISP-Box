<?php

namespace App\Modules\Billing\Services;

use Throwable;

class BillingAutomationService
{
    private InvoiceService $invoiceService;

    public function __construct(InvoiceService $invoiceService)
    {
        $this->invoiceService = $invoiceService;
    }

    public function createInvoiceAfterProvisioning(int $serviceId, ?string $activatedAt = null): array
    {
        try {
            return $this->invoiceService->createFirstInvoiceAfterProvisioning(
                $serviceId,
                $activatedAt
            );
        } catch (Throwable $e) {
            return [
                'created' => false,
                'service_id' => $serviceId,
                'activated_at' => $activatedAt,
                'error' => $e->getMessage(),
            ];
        }
    }
}
