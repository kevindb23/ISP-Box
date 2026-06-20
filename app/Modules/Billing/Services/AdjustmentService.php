<?php

namespace App\Modules\Billing\Services;

use App\Modules\Audit\Services\AuditService;
use App\Modules\Billing\Repositories\AdjustmentRepository;
use App\Modules\Billing\Repositories\InvoiceRepository;
use Exception;
use PDO;
use Throwable;

class AdjustmentService
{
    private PDO $db;
    private AdjustmentRepository $adjustments;
    private InvoiceRepository $invoices;
    private AuditService $audit;

    public function __construct(
        PDO $db,
        AdjustmentRepository $adjustments,
        InvoiceRepository $invoices,
        AuditService $audit
    ) {
        $this->db = $db;
        $this->adjustments = $adjustments;
        $this->invoices = $invoices;
        $this->audit = $audit;
    }

    public function list(array $filters = []): array
    {
        return [
            'items' => $this->adjustments->list($filters),
            'total' => $this->adjustments->count($filters),
        ];
    }

    public function show(int $id): array
    {
        $adjustment = $this->adjustments->find($id);

        if (!$adjustment) {
            throw new Exception('Billing adjustment not found.');
        }

        $invoice = null;
        $invoiceAdjustments = [];

        if (!empty($adjustment['invoice_id'])) {
            $invoiceId = (int)$adjustment['invoice_id'];
            $invoice = $this->invoices->find($invoiceId);
            $invoiceAdjustments = $this->adjustments->findByInvoice($invoiceId);
        }

        return [
            'adjustment' => $adjustment,
            'invoice' => $invoice,
            'invoice_adjustments' => $invoiceAdjustments,
            'totals' => !empty($adjustment['invoice_id'])
                ? $this->adjustments->getPostedAdjustmentTotalsByInvoice((int)$adjustment['invoice_id'])
                : [
                    'credit_total' => 0,
                    'debit_total' => 0,
                    'net_credit_total' => 0,
                ],
        ];
    }

    public function create(array $payload): array
    {
        $invoiceId = isset($payload['invoice_id']) ? (int)$payload['invoice_id'] : 0;
        $amount = isset($payload['amount']) ? (float)$payload['amount'] : 0;
        $type = strtoupper((string)($payload['adjustment_type'] ?? 'CREDIT'));
        $reason = trim((string)($payload['reason'] ?? ''));

        if ($invoiceId <= 0) {
            throw new Exception('Invoice is required.');
        }

        if ($amount <= 0) {
            throw new Exception('Adjustment amount must be greater than zero.');
        }

        if ($reason === '') {
            throw new Exception('Adjustment reason is required.');
        }

        $allowedTypes = [
            'CREDIT',
            'DEBIT',
            'DISCOUNT',
            'REBATE',
            'WAIVER',
            'CORRECTION',
        ];

        if (!in_array($type, $allowedTypes, true)) {
            throw new Exception('Invalid adjustment type.');
        }

        $invoice = $this->invoices->find($invoiceId);

        if (!$invoice) {
            throw new Exception('Invoice not found.');
        }

        if (strtoupper((string)($invoice['status'] ?? '')) === 'CANCELLED') {
            throw new Exception('Cannot create adjustment for a cancelled invoice.');
        }

        $prefix = strtoupper((string)($payload['adjustment_prefix'] ?? 'ADJ'));
        $adjustmentNo = $payload['adjustment_no'] ?? $this->adjustments->generateAdjustmentNo($prefix);

        $status = strtoupper((string)($payload['status'] ?? 'POSTED'));

        if (!in_array($status, ['POSTED', 'PENDING'], true)) {
            throw new Exception('Invalid adjustment status.');
        }

        $createdBy = $payload['created_by'] ?? null;

        $this->db->beginTransaction();

        try {
            $adjustmentId = $this->adjustments->create([
                'adjustment_no' => $adjustmentNo,
                'invoice_id' => $invoiceId,
                'subscriber_id' => $invoice['subscriber_id'] ?? null,
                'service_id' => $invoice['service_id'] ?? null,
                'adjustment_type' => $type,
                'amount' => $amount,
                'reason' => $reason,
                'status' => $status,
                'created_by' => $createdBy,
                'approved_by' => $status === 'POSTED' ? $createdBy : null,
                'approved_at' => $status === 'POSTED' ? date('Y-m-d H:i:s') : null,
            ]);

            if ($status === 'POSTED') {
                $this->recalculateInvoiceFinancials($invoiceId);
            }

            $this->writeActivityLog(
                'BILLING_ADJUSTMENT',
                $adjustmentId,
                'ADJUSTMENT_CREATED',
                'SUCCESS',
                'Billing adjustment created',
                sprintf(
                    'Adjustment %s was created for invoice %s.',
                    $adjustmentNo,
                    $invoice['invoice_no'] ?? ('#' . $invoiceId)
                ),
                null,
                $this->adjustments->find($adjustmentId),
                [
                    'invoice_id' => $invoiceId,
                    'invoice_no' => $invoice['invoice_no'] ?? null,
                    'adjustment_type' => $type,
                    'amount' => $amount,
                    'status' => $status,
                    'reason' => $reason,
                ],
                $createdBy
            );

            $this->audit->log(
                'BILLING',
                'CREATE_ADJUSTMENT',
                sprintf(
                    'Created %s adjustment %s amount %.2f for invoice %s with status %s. Reason: %s',
                    $type,
                    $adjustmentNo,
                    $amount,
                    $invoice['invoice_no'] ?? ('#' . $invoiceId),
                    $status,
                    $reason
                )
            );

            $this->db->commit();

            return $this->show($adjustmentId);
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function void(int $id, ?int $userId = null, ?string $reason = null): array
    {
        $reason = trim((string)($reason ?? ''));

        if ($reason === '') {
            throw new Exception('Void reason is required.');
        }

        $adjustment = $this->adjustments->find($id);

        if (!$adjustment) {
            throw new Exception('Billing adjustment not found.');
        }

        if (strtoupper((string)($adjustment['status'] ?? '')) !== 'POSTED') {
            throw new Exception('Only posted adjustments can be voided.');
        }

        $invoiceId = (int)($adjustment['invoice_id'] ?? 0);

        if ($invoiceId <= 0) {
            throw new Exception('Adjustment invoice reference is missing.');
        }

        $this->db->beginTransaction();

        try {
            $ok = $this->adjustments->void($id, $userId, $reason);

            if (!$ok) {
                throw new Exception('Unable to void adjustment.');
            }

            $this->recalculateInvoiceFinancials($invoiceId);

            $updatedAdjustment = $this->adjustments->find($id);

            $this->writeActivityLog(
                'BILLING_ADJUSTMENT',
                $id,
                'ADJUSTMENT_VOIDED',
                'SUCCESS',
                'Billing adjustment voided',
                sprintf(
                    'Adjustment %s was voided. Reason: %s',
                    $adjustment['adjustment_no'] ?? ('#' . $id),
                    $reason
                ),
                $adjustment,
                $updatedAdjustment,
                [
                    'invoice_id' => $invoiceId,
                    'adjustment_no' => $adjustment['adjustment_no'] ?? null,
                    'amount' => $adjustment['amount'] ?? 0,
                    'reason' => $reason,
                ],
                $userId
            );

            $this->audit->log(
                'BILLING',
                'VOID_ADJUSTMENT',
                sprintf(
                    'Voided adjustment %s for invoice %s amount %.2f. Reason: %s',
                    $adjustment['adjustment_no'] ?? ('#' . $id),
                    $adjustment['invoice_no'] ?? ('#' . $invoiceId),
                    (float)($adjustment['amount'] ?? 0),
                    $reason
                )
            );

            $this->db->commit();

            return $this->show($id);
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function getByInvoice(int $invoiceId): array
    {
        $invoice = $this->invoices->find($invoiceId);

        if (!$invoice) {
            throw new Exception('Invoice not found.');
        }

        return [
            'invoice' => $invoice,
            'items' => $this->adjustments->findByInvoice($invoiceId),
            'totals' => $this->adjustments->getPostedAdjustmentTotalsByInvoice($invoiceId),
        ];
    }

    private function recalculateInvoiceFinancials(int $invoiceId): void
    {
        $invoice = $this->invoices->find($invoiceId);

        if (!$invoice) {
            throw new Exception('Invoice not found during recalculation.');
        }

        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(line_total), 0)
            FROM invoice_items
            WHERE invoice_id = :invoice_id
        ");

        $stmt->execute([
            ':invoice_id' => $invoiceId,
        ]);

        $subtotal = (float)$stmt->fetchColumn();

        $adjustmentTotals = $this->adjustments->getPostedAdjustmentTotalsByInvoice($invoiceId);

        $creditTotal = (float)($adjustmentTotals['credit_total'] ?? 0);
        $debitTotal = (float)($adjustmentTotals['debit_total'] ?? 0);

        $discountAmount = round($creditTotal, 2);
        $taxAmount = round($debitTotal, 2);

        $total = max(0, round($subtotal - $discountAmount + $taxAmount, 2));

        $paid = $this->getPostedPaidAmount($invoiceId);
        $balance = max(0, round($total - $paid, 2));

        $status = $this->resolveInvoiceStatus(
            $total,
            $paid,
            $balance,
            $invoice['due_date'] ?? null,
            $invoice['status'] ?? 'UNPAID'
        );

        $update = $this->db->prepare("
            UPDATE invoices
            SET
                amount = :amount,
                subtotal = :subtotal,
                discount_amount = :discount_amount,
                tax_amount = :tax_amount,
                total_amount = :total_amount,
                paid_amount = :paid_amount,
                balance_amount = :balance_amount,
                status = :status
            WHERE id = :id
        ");

        $update->execute([
            ':amount' => $total,
            ':subtotal' => $subtotal,
            ':discount_amount' => $discountAmount,
            ':tax_amount' => $taxAmount,
            ':total_amount' => $total,
            ':paid_amount' => $paid,
            ':balance_amount' => $balance,
            ':status' => $status,
            ':id' => $invoiceId,
        ]);
    }

    private function getPostedPaidAmount(int $invoiceId): float
    {
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(pa.allocated_amount), 0)
            FROM payment_allocations pa
            INNER JOIN payments p ON p.id = pa.payment_id
            WHERE pa.invoice_id = :invoice_id
              AND p.payment_status = 'POSTED'
        ");

        $stmt->execute([
            ':invoice_id' => $invoiceId,
        ]);

        return round((float)$stmt->fetchColumn(), 2);
    }

    private function resolveInvoiceStatus(
        float $total,
        float $paid,
        float $balance,
        ?string $dueDate,
        string $currentStatus
    ): string {
        $currentStatus = strtoupper($currentStatus);

        if ($currentStatus === 'CANCELLED') {
            return 'CANCELLED';
        }

        if ($total <= 0) {
            return 'PAID';
        }

        if ($balance <= 0) {
            return 'PAID';
        }

        if ($paid > 0) {
            return 'PARTIAL';
        }

        if ($dueDate && $dueDate < date('Y-m-d')) {
            return 'OVERDUE';
        }

        return 'UNPAID';
    }

    private function writeActivityLog(
        string $entityType,
        int $entityId,
        string $action,
        string $status,
        string $title,
        string $message,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $meta = null,
               $performedBy = null
    ): void {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO activity_logs (
                    entity_type,
                    entity_id,
                    action,
                    status,
                    title,
                    message,
                    old_values,
                    new_values,
                    meta_json,
                    performed_by,
                    ip_address,
                    user_agent,
                    created_at
                ) VALUES (
                    :entity_type,
                    :entity_id,
                    :action,
                    :status,
                    :title,
                    :message,
                    :old_values,
                    :new_values,
                    :meta_json,
                    :performed_by,
                    :ip_address,
                    :user_agent,
                    NOW()
                )
            ");

            $stmt->execute([
                ':entity_type' => $entityType,
                ':entity_id' => $entityId,
                ':action' => $action,
                ':status' => $status,
                ':title' => $title,
                ':message' => $message,
                ':old_values' => $oldValues ? json_encode($oldValues, JSON_UNESCAPED_SLASHES) : null,
                ':new_values' => $newValues ? json_encode($newValues, JSON_UNESCAPED_SLASHES) : null,
                ':meta_json' => $meta ? json_encode($meta, JSON_UNESCAPED_SLASHES) : null,
                ':performed_by' => $performedBy ?: null,
                ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ]);
        } catch (Throwable $e) {
        }
    }
}