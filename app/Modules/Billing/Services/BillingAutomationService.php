<?php

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Repositories\BillingSettingsRepository;
use App\Modules\Billing\Repositories\InvoiceRepository;
use App\Modules\Billing\Repositories\PaymentRepository;
use PDO;
use Throwable;

class BillingAutomationService
{
    private InvoiceService $invoiceService;

    public function __construct(PDO $db)
    {
        $invoiceRepo = new InvoiceRepository($db);
        $paymentRepo = new PaymentRepository($db);
        $settingsRepo = new BillingSettingsRepository($db);

        $this->invoiceService = new InvoiceService(
            $db,
            $invoiceRepo,
            $paymentRepo,
            $settingsRepo
        );
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