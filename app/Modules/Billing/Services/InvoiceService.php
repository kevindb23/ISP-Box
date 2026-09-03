<?php

namespace App\Modules\Billing\Services;

use App\Modules\Audit\Services\AuditService;
use App\Modules\Billing\Repositories\BillingSettingsRepository;
use App\Modules\Billing\Repositories\InvoiceRepository;
use App\Modules\Billing\Repositories\PaymentRepository;
use App\Modules\Subscribers\Services\SubscriberService;
use Exception;
use PDO;

class InvoiceService
{
    private PDO $db;
    private InvoiceRepository $invoices;
    private PaymentRepository $payments;
    private BillingSettingsRepository $settings;
    private ?AuditService $audit;
    private ?SubscriberService $subscribers;

    public function __construct(
        PDO $db,
        InvoiceRepository $invoices,
        PaymentRepository $payments,
        BillingSettingsRepository $settings,
        ?AuditService $audit = null,
        ?SubscriberService $subscribers = null
    ) {
        $this->db = $db;
        $this->invoices = $invoices;
        $this->payments = $payments;
        $this->settings = $settings;
        $this->audit = $audit;
        $this->subscribers = $subscribers;
    }

    public function list(array $filters = []): array
    {
        return [
            'items' => $this->invoices->list($filters),
            'total' => $this->invoices->count($filters),
        ];
    }

    public function show(int $id): array
    {
        $invoice = $this->invoices->find($id);

        if (!$invoice) {
            throw new Exception('Invoice not found.');
        }

        $adjustments = [];

        if (method_exists($this->invoices, 'getAdjustmentsByInvoice')) {
            $adjustments = $this->invoices->getAdjustmentsByInvoice($id);
        }

        return [
            'invoice' => $invoice,
            'items' => $this->invoices->getItems($id),
            'payments' => $this->payments->getPaymentsByInvoice($id),
            'adjustments' => $adjustments,
            'invoice_adjustments' => $adjustments,
        ];
    }

    public function create(array $payload): array
    {
        $serviceId = isset($payload['service_id']) ? (int)$payload['service_id'] : 0;

        if ($serviceId <= 0) {
            throw new Exception('Service is required.');
        }

        $service = $this->invoices->getServiceContext($serviceId);

        if (!$service) {
            throw new Exception('Subscriber service not found.');
        }

        $items = $payload['items'] ?? [];

        if (!is_array($items) || count($items) === 0) {
            $items = [[
                'item_type' => 'PLAN',
                'description' => $service['plan_name'] ?: 'Internet service billing',
                'quantity' => 1,
                'unit_price' => (float)($service['price'] ?? 0),
            ]];
        }

        $subtotal = 0;

        foreach ($items as &$item) {
            $quantity = (float)($item['quantity'] ?? 1);
            $unitPrice = (float)($item['unit_price'] ?? 0);

            $item['line_total'] = round($quantity * $unitPrice, 2);
            $subtotal += $item['line_total'];
        }

        unset($item);

        $discount = (float)($payload['discount_amount'] ?? 0);
        $tax = (float)($payload['tax_amount'] ?? 0);
        $total = max(0, round($subtotal - $discount + $tax, 2));

        $defaultDueDays = (int)$this->settings->get('default_due_days', 10);
        $issueDate = $this->normalizeDate($payload['issue_date'] ?? null) ?: date('Y-m-d');
        $dueDate = $this->normalizeDate($payload['due_date'] ?? null)
            ?: date('Y-m-d', strtotime($issueDate . ' +' . $defaultDueDays . ' days'));

        $prefix = (string)$this->settings->get('invoice_prefix', 'INV');
        $invoiceNo = isset($payload['invoice_no']) && trim((string)$payload['invoice_no']) !== ''
            ? trim((string)$payload['invoice_no'])
            : null;

        $this->db->beginTransaction();

        try {
            $invoiceId = $this->invoices->create([
                'invoice_no' => $invoiceNo,
                'service_id' => $serviceId,
                'subscriber_id' => (int)$service['subscriber_id'],
                'plan_id' => $service['plan_id'] ? (int)$service['plan_id'] : null,
                'billing_period_start' => $this->normalizeDate($payload['billing_period_start'] ?? null),
                'billing_period_end' => $this->normalizeDate($payload['billing_period_end'] ?? null),
                'issue_date' => $issueDate,
                'amount' => $total,
                'subtotal' => $subtotal,
                'discount_amount' => $discount,
                'tax_amount' => $tax,
                'total_amount' => $total,
                'paid_amount' => 0,
                'balance_amount' => $total,
                'due_date' => $dueDate,
                'status' => 'UNPAID',
                'notes' => $payload['notes'] ?? null,
            ]);

            if ($invoiceNo === null) {
                $invoiceNo = sprintf('%s-%s-%06d', $prefix, date('Y'), $invoiceId);
                $this->invoices->assignInvoiceNo($invoiceId, $invoiceNo);
            }

            foreach ($items as $item) {
                $this->invoices->createItem($invoiceId, $item);
            }

            $this->invoices->recalculateTotals($invoiceId);

            $this->db->commit();

            $created = $this->show($invoiceId);
            $invoice = $created['invoice'] ?? [];

            $this->auditLog(
                'CREATE_INVOICE',
                sprintf(
                    'Created invoice %s for subscriber %s amount %.2f due %s',
                    $invoice['invoice_no'] ?? $invoiceNo,
                    $service['subscriber_name'] ?? $service['full_name'] ?? ('Subscriber #' . (int)$service['subscriber_id']),
                    (float)($invoice['total_amount'] ?? $total),
                    $invoice['due_date'] ?? $dueDate
                )
            );

            return $created;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function cancel(int $id, ?int $userId = null): array
    {
        $this->db->beginTransaction();
        try {
        $invoice = $this->invoices->findForUpdate($id);

        if (!$invoice) {
            throw new Exception('Invoice not found.');
        }

        if ($invoice['status'] === 'PAID') {
            throw new Exception('Paid invoices cannot be cancelled.');
        }

        $ok = $this->invoices->cancel($id, $userId);

        if (!$ok) {
            throw new Exception('Unable to cancel invoice.');
        }
        $this->invoices->cancelPendingGatewayTransactions($id);
        $this->db->commit();

        $this->auditLog(
            'CANCEL_INVOICE',
            sprintf(
                'Cancelled invoice %s amount %.2f',
                $invoice['invoice_no'] ?? ('#' . $id),
                (float)($invoice['total_amount'] ?? 0)
            )
        );

        return $this->show($id);
        } catch (\Throwable $e) { if($this->db->inTransaction())$this->db->rollBack(); throw $e; }
    }

    public function recalculate(int $id): array
    {
        $this->invoices->recalculateTotals($id);
        $this->invoices->updatePaymentTotals($id);

        $result = $this->show($id);
        $invoice = $result['invoice'] ?? [];

        $this->auditLog(
            'RECALCULATE_INVOICE',
            sprintf(
                'Recalculated invoice %s. Total %.2f, Paid %.2f, Balance %.2f, Status %s',
                $invoice['invoice_no'] ?? ('#' . $id),
                (float)($invoice['total_amount'] ?? 0),
                (float)($invoice['paid_amount'] ?? 0),
                (float)($invoice['balance_amount'] ?? 0),
                $invoice['status'] ?? 'UNKNOWN'
            )
        );

        return $result;
    }

    public function createFirstInvoiceAfterProvisioning(int $serviceId, ?string $activatedAt = null): array
    {
        $service = $this->invoices->getServiceContext($serviceId);

        if (!$service) {
            throw new Exception('Subscriber service not found for auto invoicing.');
        }

        $accountType = strtoupper((string)($service['account_type'] ?? 'POSTPAID'));

        if ($accountType !== 'POSTPAID') {
            return [
                'created' => false,
                'reason' => 'Auto invoice skipped because service is not POSTPAID.',
                'service_id' => $serviceId,
                'account_type' => $accountType,
            ];
        }

        $price = (float)($service['price'] ?? 0);

        if ($price <= 0) {
            throw new Exception('Plan price is missing or zero. Cannot auto-create invoice.');
        }

        $validityDays = (int)($service['validity_days'] ?? 30);
        $validityDays = $validityDays > 0 ? $validityDays : 30;

        $activationDate = $this->normalizeDate($activatedAt) ?: date('Y-m-d');

        $periodStart = $activationDate;
        $periodEnd = date('Y-m-d', strtotime($periodStart . ' +' . ($validityDays - 1) . ' days'));

        $existing = $this->invoices->findByServiceAndPeriod($serviceId, $periodStart, $periodEnd);

        if ($existing) {
            return [
                'created' => false,
                'reason' => 'Invoice already exists for this service billing period.',
                'service_id' => $serviceId,
                'billing_period_start' => $periodStart,
                'billing_period_end' => $periodEnd,
                'invoice' => $existing,
            ];
        }

        $created = $this->create([
            'service_id' => $serviceId,
            'billing_period_start' => $periodStart,
            'billing_period_end' => $periodEnd,
            'issue_date' => $activationDate,
            'notes' => 'Auto-generated after successful service provisioning.',
            'items' => [
                [
                    'item_type' => 'PLAN',
                    'description' => $service['plan_name'] ?: 'Monthly internet service',
                    'quantity' => 1,
                    'unit_price' => $price,
                ],
            ],
        ]);

        $nextDueDate = date('Y-m-d', strtotime($periodStart . ' +' . $validityDays . ' days'));

        $this->invoices->updateServiceNextDueDate($serviceId, $nextDueDate);

        $invoice = $created['invoice'] ?? [];

        $this->auditLog(
            'AUTO_CREATE_FIRST_INVOICE',
            sprintf(
                'Auto-created first invoice %s after provisioning for service %d. Period %s to %s. Next due %s',
                $invoice['invoice_no'] ?? 'N/A',
                $serviceId,
                $periodStart,
                $periodEnd,
                $nextDueDate
            )
        );

        return [
            'created' => true,
            'service_id' => $serviceId,
            'invoice' => $created['invoice'] ?? null,
            'billing_period_start' => $periodStart,
            'billing_period_end' => $periodEnd,
            'next_due_date' => $nextDueDate,
        ];
    }

    public function createRecurringInvoiceForService(int $serviceId, ?string $asOfDate = null): array
    {
        $asOfDate = $this->normalizeDate($asOfDate) ?: date('Y-m-d');

        $service = $this->invoices->getServiceContext($serviceId);

        if (!$service) {
            throw new Exception('Subscriber service not found for recurring invoicing.');
        }

        $accountType = strtoupper((string)($service['account_type'] ?? 'POSTPAID'));
        $serviceStatus = strtoupper((string)($service['service_status'] ?? ''));

        if ($serviceStatus !== 'ACTIVE') {
            return [
                'created' => false,
                'reason' => 'Service is not ACTIVE.',
                'service_id' => $serviceId,
                'service_status' => $serviceStatus,
            ];
        }

        if ($accountType !== 'POSTPAID') {
            return [
                'created' => false,
                'reason' => 'Recurring invoice skipped because service is not POSTPAID.',
                'service_id' => $serviceId,
                'account_type' => $accountType,
            ];
        }

        $nextDueDate = trim((string)($service['next_due_date'] ?? ''));

        if ($nextDueDate === '' || $nextDueDate === '0000-00-00') {
            return [
                'created' => false,
                'reason' => 'Service has no next_due_date.',
                'service_id' => $serviceId,
            ];
        }

        $nextDueDate = $this->normalizeDate($nextDueDate);

        if (!$nextDueDate) {
            return [
                'created' => false,
                'reason' => 'Service has an invalid next_due_date.',
                'service_id' => $serviceId,
            ];
        }

        if ($nextDueDate > $asOfDate) {
            return [
                'created' => false,
                'reason' => 'Service is not yet due for billing.',
                'service_id' => $serviceId,
                'next_due_date' => $nextDueDate,
                'as_of_date' => $asOfDate,
            ];
        }

        $price = (float)($service['price'] ?? 0);

        if ($price <= 0) {
            throw new Exception('Plan price is missing or zero. Cannot auto-create recurring invoice.');
        }

        $validityDays = (int)($service['validity_days'] ?? 30);
        $validityDays = $validityDays > 0 ? $validityDays : 30;

        $periodStart = $nextDueDate;
        $periodEnd = date('Y-m-d', strtotime($periodStart . ' +' . ($validityDays - 1) . ' days'));

        $existing = $this->invoices->findByServiceAndPeriod($serviceId, $periodStart, $periodEnd);

        if ($existing) {
            $newNextDueDate = date('Y-m-d', strtotime($periodStart . ' +' . $validityDays . ' days'));

            $this->invoices->updateServiceNextDueDate($serviceId, $newNextDueDate);

            return [
                'created' => false,
                'reason' => 'Invoice already exists for this billing period. next_due_date advanced.',
                'service_id' => $serviceId,
                'invoice' => $existing,
                'next_due_date' => $newNextDueDate,
            ];
        }

        $created = $this->create([
            'service_id' => $serviceId,
            'billing_period_start' => $periodStart,
            'billing_period_end' => $periodEnd,
            'issue_date' => $asOfDate,
            'notes' => 'Auto-generated recurring billing invoice.',
            'items' => [
                [
                    'item_type' => 'PLAN',
                    'description' => $service['plan_name'] ?: 'Monthly internet service',
                    'quantity' => 1,
                    'unit_price' => $price,
                ],
            ],
        ]);

        $newNextDueDate = date('Y-m-d', strtotime($periodStart . ' +' . $validityDays . ' days'));

        $this->invoices->updateServiceNextDueDate($serviceId, $newNextDueDate);

        $invoice = $created['invoice'] ?? [];

        $this->auditLog(
            'AUTO_CREATE_RECURRING_INVOICE',
            sprintf(
                'Auto-created recurring invoice %s for service %d. Period %s to %s. Next due %s',
                $invoice['invoice_no'] ?? 'N/A',
                $serviceId,
                $periodStart,
                $periodEnd,
                $newNextDueDate
            )
        );

        return [
            'created' => true,
            'service_id' => $serviceId,
            'invoice' => $created['invoice'] ?? null,
            'billing_period_start' => $periodStart,
            'billing_period_end' => $periodEnd,
            'next_due_date' => $newNextDueDate,
        ];
    }

    public function markOverdueInvoices(?string $asOfDate = null, int $limit = 500): array
    {
        $asOfDate = $this->normalizeDate($asOfDate) ?: date('Y-m-d');
        $limit = max(1, min(1000, $limit));

        $result = $this->invoices->markOverdueInvoices($asOfDate, $limit);

        $result['suspensions'] = [];
        if ((int)$this->settings->get('auto_suspend_enabled', 0) === 1 && $this->subscribers) {
            $graceDays = max(0, (int)$this->settings->get('grace_period_days', 3));
            $subscriberIds = [];
            foreach ($result['items'] ?? [] as $item) {
                if ((int)($item['days_overdue'] ?? 0) > $graceDays && (int)($item['subscriber_id'] ?? 0) > 0) $subscriberIds[(int)$item['subscriber_id']] = true;
            }
            foreach (array_keys($subscriberIds) as $subscriberId) {
                $result['suspensions'][] = ['subscriber_id'=>$subscriberId,'result'=>$this->subscribers->suspend($subscriberId)];
            }
        }

        $this->auditLog(
            'MARK_OVERDUE_INVOICES',
            sprintf(
                'Marked overdue invoices as of %s with limit %d',
                $asOfDate,
                $limit
            )
        );

        return $result;
    }

    private function normalizeDate(?string $value): ?string
    {
        $value = trim((string)$value);

        if ($value === '' || $value === '0000-00-00') {
            return null;
        }

        $timestamp = strtotime($value);

        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d', $timestamp);
    }

    private function auditLog(string $action, string $description): void
    {
        if (!$this->audit) {
            return;
        }

        $this->audit->log(
            'BILLING',
            $action,
            $description
        );
    }
}
