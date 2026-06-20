<?php

namespace App\Modules\Billing\Services;

use App\Modules\Audit\Services\AuditService;
use App\Modules\Billing\Repositories\BillingActivityLogRepository;
use App\Modules\Billing\Repositories\BillingSettingsRepository;
use App\Modules\Billing\Repositories\InvoiceRepository;
use App\Modules\Billing\Repositories\PaymentRepository;
use Exception;
use PDO;
use Throwable;

class PaymentService
{
    private PDO $db;
    private PaymentRepository $payments;
    private InvoiceRepository $invoices;
    private BillingSettingsRepository $settings;
    private BillingActivityLogRepository $activityLogs;
    private ?AuditService $audit;

    public function __construct(
        PDO $db,
        PaymentRepository $payments,
        InvoiceRepository $invoices,
        BillingSettingsRepository $settings,
        ?BillingActivityLogRepository $activityLogs = null,
        ?AuditService $audit = null
    ) {
        $this->db = $db;
        $this->payments = $payments;
        $this->invoices = $invoices;
        $this->settings = $settings;
        $this->activityLogs = $activityLogs ?: new BillingActivityLogRepository($db);
        $this->audit = $audit;
    }

    public function list(array $filters = []): array
    {
        return [
            'items' => $this->payments->list($filters),
            'total' => $this->payments->count($filters),
        ];
    }

    public function show(int $paymentId): array
    {
        if ($paymentId <= 0) {
            throw new Exception('Invalid payment ID.');
        }

        $payment = $this->payments->find($paymentId);

        if (!$payment) {
            throw new Exception('Payment not found.');
        }

        $allocations = $this->payments->getAllocationsByPayment($paymentId);

        $invoice = null;

        if (!empty($payment['invoice_id'])) {
            $invoice = $this->invoices->find((int)$payment['invoice_id']);
        }

        return [
            'payment' => $payment,
            'invoice' => $invoice,
            'allocations' => $allocations,
            'activity_logs' => $this->activityLogs->listForEntity('PAYMENT', $paymentId),
        ];
    }

    public function create(array $payload): array
    {
        $invoiceId = isset($payload['invoice_id']) ? (int)$payload['invoice_id'] : 0;
        $amount = isset($payload['amount']) ? (float)$payload['amount'] : 0;

        if ($invoiceId <= 0) {
            throw new Exception('Invoice is required.');
        }

        if ($amount <= 0) {
            throw new Exception('Payment amount must be greater than zero.');
        }

        $invoice = $this->invoices->find($invoiceId);

        if (!$invoice) {
            throw new Exception('Invoice not found.');
        }

        $invoiceStatus = strtoupper((string)($invoice['status'] ?? ''));

        if ($invoiceStatus === 'CANCELLED') {
            throw new Exception('Cannot record payment for a cancelled invoice.');
        }

        $balance = (float)($invoice['balance_amount'] ?? 0);

        if ($balance <= 0) {
            throw new Exception('Invoice is already fully paid.');
        }

        if ($amount > $balance) {
            throw new Exception('Payment amount cannot exceed invoice balance.');
        }

        $method = strtoupper(trim((string)($payload['method'] ?? 'CASH')));
        $paymentStatus = strtoupper(trim((string)($payload['payment_status'] ?? 'POSTED')));

        if ($method === '') {
            $method = 'CASH';
        }

        if ($paymentStatus === '') {
            $paymentStatus = 'POSTED';
        }

        $prefix = (string)$this->settings->get('payment_prefix', 'PAY');
        $paymentNo = $payload['payment_no'] ?? $this->payments->generatePaymentNo($prefix);

        $this->db->beginTransaction();

        try {
            $paymentId = $this->payments->create([
                'payment_no' => $paymentNo,
                'invoice_id' => $invoiceId,
                'subscriber_id' => $invoice['subscriber_id'] ?? null,
                'service_id' => $invoice['service_id'] ?? null,
                'amount' => $amount,
                'payment_date' => $payload['payment_date'] ?? date('Y-m-d H:i:s'),
                'method' => $method,
                'reference_no' => $payload['reference_no'] ?? null,
                'payment_status' => $paymentStatus,
                'remarks' => $payload['remarks'] ?? null,
                'received_by' => $payload['received_by'] ?? null,
            ]);

            if ($paymentStatus === 'POSTED') {
                $this->payments->createAllocation($paymentId, $invoiceId, $amount);
                $this->invoices->updatePaymentTotals($invoiceId);
            }

            $updatedInvoice = $this->invoices->find($invoiceId);
            $payment = $this->payments->find($paymentId);
            $allocations = $this->payments->getAllocationsByPayment($paymentId);

            $this->logActivity([
                'entity_type' => 'PAYMENT',
                'entity_id' => $paymentId,
                'action' => 'PAYMENT_RECORDED',
                'status' => 'SUCCESS',
                'title' => 'Payment recorded',
                'message' => sprintf(
                    'Payment %s was recorded for invoice %s.',
                    (string)($payment['payment_no'] ?? $paymentNo),
                    (string)($invoice['invoice_no'] ?? ('#' . $invoiceId))
                ),
                'new_values' => $payment,
                'meta_json' => [
                    'invoice_id' => $invoiceId,
                    'invoice_no' => $invoice['invoice_no'] ?? null,
                    'amount' => $amount,
                    'method' => $method,
                    'payment_status' => $paymentStatus,
                    'reference_no' => $payload['reference_no'] ?? null,
                ],
            ]);

            $this->logActivity([
                'entity_type' => 'INVOICE',
                'entity_id' => $invoiceId,
                'action' => 'PAYMENT_RECORDED',
                'status' => 'SUCCESS',
                'title' => 'Payment applied',
                'message' => sprintf(
                    'Payment %s amounting to %s was applied to this invoice.',
                    (string)($payment['payment_no'] ?? $paymentNo),
                    number_format($amount, 2)
                ),
                'old_values' => [
                    'paid_amount' => $invoice['paid_amount'] ?? null,
                    'balance_amount' => $invoice['balance_amount'] ?? null,
                    'status' => $invoice['status'] ?? null,
                ],
                'new_values' => [
                    'paid_amount' => $updatedInvoice['paid_amount'] ?? null,
                    'balance_amount' => $updatedInvoice['balance_amount'] ?? null,
                    'status' => $updatedInvoice['status'] ?? null,
                ],
                'meta_json' => [
                    'payment_id' => $paymentId,
                    'payment_no' => $payment['payment_no'] ?? $paymentNo,
                    'amount' => $amount,
                    'method' => $method,
                ],
            ]);

            $this->auditLog(
                'POST_PAYMENT',
                sprintf(
                    'Posted payment %s amount %.2f for invoice %s using %s. New invoice balance %.2f',
                    (string)($payment['payment_no'] ?? $paymentNo),
                    $amount,
                    (string)($invoice['invoice_no'] ?? ('#' . $invoiceId)),
                    $method,
                    (float)($updatedInvoice['balance_amount'] ?? 0)
                )
            );

            $this->db->commit();

            return [
                'payment' => $payment,
                'allocations' => $allocations,
                'invoice' => $updatedInvoice,
            ];
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw new Exception($e->getMessage());
        }
    }

    public function getByInvoice(int $invoiceId): array
    {
        $invoice = $this->invoices->find($invoiceId);

        if (!$invoice) {
            throw new Exception('Invoice not found.');
        }

        return $this->payments->getPaymentsByInvoice($invoiceId);
    }

    public function void(int $paymentId, ?int $userId = null, ?string $reason = null): array
    {
        if ($paymentId <= 0) {
            throw new Exception('Invalid payment ID.');
        }

        $payment = $this->payments->find($paymentId);

        if (!$payment) {
            throw new Exception('Payment not found.');
        }

        $paymentStatus = strtoupper((string)($payment['payment_status'] ?? ''));

        if ($paymentStatus !== 'POSTED') {
            throw new Exception('Only posted payments can be voided.');
        }

        $allocationsBeforeVoid = $this->payments->getAllocationsByPayment($paymentId);

        if (!$allocationsBeforeVoid) {
            throw new Exception('Payment has no invoice allocation to reverse.');
        }

        $affectedInvoiceIds = [];

        foreach ($allocationsBeforeVoid as $allocation) {
            $invoiceId = (int)($allocation['invoice_id'] ?? 0);

            if ($invoiceId > 0) {
                $affectedInvoiceIds[$invoiceId] = $invoiceId;
            }
        }

        if (!$affectedInvoiceIds && !empty($payment['invoice_id'])) {
            $affectedInvoiceIds[(int)$payment['invoice_id']] = (int)$payment['invoice_id'];
        }

        if (!$affectedInvoiceIds) {
            throw new Exception('Unable to resolve invoice affected by this payment.');
        }

        $oldInvoices = [];

        foreach ($affectedInvoiceIds as $invoiceId) {
            $oldInvoices[$invoiceId] = $this->invoices->find($invoiceId);
        }

        $this->db->beginTransaction();

        try {
            $voided = $this->payments->void($paymentId, $userId, $reason);

            if (!$voided) {
                throw new Exception('Unable to void payment. It may have already been voided.');
            }

            foreach ($affectedInvoiceIds as $invoiceId) {
                $this->invoices->updatePaymentTotals($invoiceId);
            }

            $freshPayment = $this->payments->find($paymentId);
            $freshAllocations = $this->payments->getAllocationsByPayment($paymentId);

            $freshInvoice = null;
            $freshInvoices = [];

            foreach ($affectedInvoiceIds as $invoiceId) {
                $freshInvoices[$invoiceId] = $this->invoices->find($invoiceId);
            }

            if (!empty($freshPayment['invoice_id'])) {
                $freshInvoice = $this->invoices->find((int)$freshPayment['invoice_id']);
            } else {
                $firstInvoiceId = reset($affectedInvoiceIds);
                $freshInvoice = $firstInvoiceId ? ($freshInvoices[$firstInvoiceId] ?? null) : null;
            }

            $this->logActivity([
                'entity_type' => 'PAYMENT',
                'entity_id' => $paymentId,
                'action' => 'PAYMENT_VOIDED',
                'status' => 'SUCCESS',
                'title' => 'Payment voided',
                'message' => sprintf(
                    'Payment %s was voided.',
                    (string)($payment['payment_no'] ?? ('#' . $paymentId))
                ),
                'old_values' => $payment,
                'new_values' => $freshPayment,
                'meta_json' => [
                    'reason' => $reason,
                    'voided_by' => $userId,
                    'affected_invoice_ids' => array_values($affectedInvoiceIds),
                ],
            ]);

            foreach ($affectedInvoiceIds as $invoiceId) {
                $oldInvoice = $oldInvoices[$invoiceId] ?? null;
                $newInvoice = $freshInvoices[$invoiceId] ?? null;

                $this->logActivity([
                    'entity_type' => 'INVOICE',
                    'entity_id' => $invoiceId,
                    'action' => 'PAYMENT_VOIDED',
                    'status' => 'SUCCESS',
                    'title' => 'Payment reversed',
                    'message' => sprintf(
                        'Payment %s was voided and invoice totals were recalculated.',
                        (string)($payment['payment_no'] ?? ('#' . $paymentId))
                    ),
                    'old_values' => [
                        'paid_amount' => $oldInvoice['paid_amount'] ?? null,
                        'balance_amount' => $oldInvoice['balance_amount'] ?? null,
                        'status' => $oldInvoice['status'] ?? null,
                    ],
                    'new_values' => [
                        'paid_amount' => $newInvoice['paid_amount'] ?? null,
                        'balance_amount' => $newInvoice['balance_amount'] ?? null,
                        'status' => $newInvoice['status'] ?? null,
                    ],
                    'meta_json' => [
                        'payment_id' => $paymentId,
                        'payment_no' => $payment['payment_no'] ?? null,
                        'void_reason' => $reason,
                    ],
                ]);
            }

            $this->auditLog(
                'VOID_PAYMENT',
                sprintf(
                    'Voided payment %s amount %.2f. Reason: %s',
                    (string)($payment['payment_no'] ?? ('#' . $paymentId)),
                    (float)($payment['amount'] ?? 0),
                    trim((string)$reason) !== '' ? $reason : 'No reason provided'
                )
            );

            $this->db->commit();

            return [
                'payment' => $freshPayment,
                'allocations' => $freshAllocations,
                'invoice' => $freshInvoice,
                'affected_invoice_ids' => array_values($affectedInvoiceIds),
                'reason' => $reason,
            ];
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw new Exception($e->getMessage());
        }
    }

    private function logActivity(array $data): void
    {
        try {
            $this->activityLogs->create($data);
        } catch (Throwable $e) {
        }
    }

    private function auditLog(string $action, string $description): void
    {
        if (!$this->audit) {
            return;
        }

        try {
            $this->audit->log(
                'BILLING',
                $action,
                $description
            );
        } catch (Throwable $e) {
        }
    }
}