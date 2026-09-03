<?php

namespace App\Modules\Billing\Services;

use App\Modules\Audit\Services\AuditService;
use App\Modules\Billing\Repositories\BillingSettingsRepository;
use App\Modules\Billing\Repositories\BillingRepository;
use App\Modules\Billing\Entities\Billing;
use Throwable;

class BillingService
{
    private BillingRepository $billing;
    private BillingSettingsRepository $settings;
    private ?AuditService $audit;

    public function __construct(
        BillingRepository $billing,
        BillingSettingsRepository $settings,
        ?AuditService $audit = null
    ) {
        $this->billing = $billing;
        $this->settings = $settings;
        $this->audit = $audit;
    }

    public function overview(): array
    {
        return (new Billing([
            'stats' => $this->billing->overviewStats(),
            'recent_invoices' => $this->billing->recentInvoices(),
            'recent_payments' => $this->billing->recentPayments(),
        ]))->toArray();
    }

    public function settings(): array
    {
        return [
            'rows' => $this->settings->listRows(),
            'map' => $this->settings->all(),
        ];
    }

    public function saveSettings(array $payload): array
    {
        $allowed = [
            'invoice_prefix',
            'payment_prefix',
            'adjustment_prefix',
            'default_due_days',
            'currency',
            'tax_enabled',
            'tax_rate',
            'grace_period_days',
            'auto_suspend_enabled',
        ];

        $changed = [];

        foreach ($allowed as $key) {
            if (array_key_exists($key, $payload)) {
                $this->settings->save($key, $payload[$key]);
                $changed[] = $key;
            }
        }

        if (!empty($changed)) {
            $this->safeAudit(
                'BILLING',
                'UPDATE_SETTINGS',
                'Updated billing settings: ' . implode(', ', $changed)
            );
        }

        return $this->settings();
    }

    public function supportSubscribers(): array
    {
        return $this->billing->supportSubscribers();
    }

    public function supportServices(?int $subscriberId = null): array
    {
        return $this->billing->supportServices($subscriberId);
    }

    public function supportPlans(): array
    {
        return $this->billing->supportPlans();
    }

    private function safeAudit(string $module, string $action, string $description): void
    {
        if (!$this->audit) {
            return;
        }

        try {
            $this->audit->log($module, $action, $description);
        } catch (Throwable $e) {
            error_log('[Audit][' . $module . '] ' . $e->getMessage());
        }
    }
}
