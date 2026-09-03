<?php

namespace App\Modules\Billing\Repositories;

use PDO;

class InvoiceRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function list(array $filters = []): array
    {
        $sql = "
            SELECT
                i.*,
                s.full_name AS subscriber_name,
                s.account_number,
                ss.service_number,
                ss.ppp_username,
                p.plan_name
            FROM invoices i
            LEFT JOIN subscribers s ON s.id = i.subscriber_id
            LEFT JOIN subscriber_services ss ON ss.id = i.service_id
            LEFT JOIN plans p ON p.id = i.plan_id
            WHERE 1 = 1
        ";

        $params = [];

        if (!empty($filters['status'])) {
            $sql .= " AND i.status = :status";
            $params[':status'] = strtoupper((string)$filters['status']);
        }

        if (!empty($filters['subscriber_id'])) {
            $sql .= " AND i.subscriber_id = :subscriber_id";
            $params[':subscriber_id'] = (int)$filters['subscriber_id'];
        }

        if (!empty($filters['service_id'])) {
            $sql .= " AND i.service_id = :service_id";
            $params[':service_id'] = (int)$filters['service_id'];
        }

        if (!empty($filters['search'])) {
            $sql .= "
                AND (
                    i.invoice_no LIKE :search
                    OR s.full_name LIKE :search
                    OR s.account_number LIKE :search
                    OR ss.service_number LIKE :search
                    OR ss.ppp_username LIKE :search
                    OR p.plan_name LIKE :search
                )
            ";
            $params[':search'] = '%' . trim((string)$filters['search']) . '%';
        }

        $sql .= " ORDER BY i.id DESC LIMIT :limit OFFSET :offset";

        $limit = isset($filters['limit']) ? max(1, min(200, (int)$filters['limit'])) : 50;
        $offset = isset($filters['offset']) ? max(0, (int)$filters['offset']) : 0;

        $stmt = $this->db->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function count(array $filters = []): int
    {
        $sql = "
            SELECT COUNT(*)
            FROM invoices i
            LEFT JOIN subscribers s ON s.id = i.subscriber_id
            LEFT JOIN subscriber_services ss ON ss.id = i.service_id
            LEFT JOIN plans p ON p.id = i.plan_id
            WHERE 1 = 1
        ";

        $params = [];

        if (!empty($filters['status'])) {
            $sql .= " AND i.status = :status";
            $params[':status'] = strtoupper((string)$filters['status']);
        }

        if (!empty($filters['subscriber_id'])) {
            $sql .= " AND i.subscriber_id = :subscriber_id";
            $params[':subscriber_id'] = (int)$filters['subscriber_id'];
        }

        if (!empty($filters['service_id'])) {
            $sql .= " AND i.service_id = :service_id";
            $params[':service_id'] = (int)$filters['service_id'];
        }

        if (!empty($filters['search'])) {
            $sql .= "
                AND (
                    i.invoice_no LIKE :search
                    OR s.full_name LIKE :search
                    OR s.account_number LIKE :search
                    OR ss.service_number LIKE :search
                    OR ss.ppp_username LIKE :search
                    OR p.plan_name LIKE :search
                )
            ";
            $params[':search'] = '%' . trim((string)$filters['search']) . '%';
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                i.*,
                s.full_name AS subscriber_name,
                s.account_number,
                s.contact_number,
                s.email,
                s.address,
                ss.service_number,
                ss.ppp_username,
                ss.account_type,
                ss.status AS service_status,
                p.plan_name,
                p.price AS plan_price
            FROM invoices i
            LEFT JOIN subscribers s ON s.id = i.subscriber_id
            LEFT JOIN subscriber_services ss ON ss.id = i.service_id
            LEFT JOIN plans p ON p.id = i.plan_id
            WHERE i.id = :id
            LIMIT 1
        ");

        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Fetch an invoice while holding a row lock for the current transaction.
     * Payment writers must use this method before validating the balance.
     */
    public function findForUpdate(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                i.*,
                s.full_name AS subscriber_name,
                s.account_number,
                s.contact_number,
                s.email,
                s.address,
                ss.service_number,
                ss.ppp_username,
                ss.account_type,
                ss.status AS service_status,
                p.plan_name,
                p.price AS plan_price
            FROM invoices i
            LEFT JOIN subscribers s ON s.id = i.subscriber_id
            LEFT JOIN subscriber_services ss ON ss.id = i.service_id
            LEFT JOIN plans p ON p.id = i.plan_id
            WHERE i.id = :id
            LIMIT 1
            FOR UPDATE
        ");

        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findByInvoiceNo(string $invoiceNo): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM invoices
            WHERE invoice_no = :invoice_no
            LIMIT 1
        ");

        $stmt->execute([':invoice_no' => $invoiceNo]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function getItems(int $invoiceId): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM invoice_items
            WHERE invoice_id = :invoice_id
            ORDER BY id ASC
        ");

        $stmt->execute([':invoice_id' => $invoiceId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getAdjustmentsByInvoice(int $invoiceId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                ba.*,
                i.invoice_no,
                i.total_amount,
                i.paid_amount,
                i.balance_amount,
                i.status AS invoice_status,
                i.billing_period_start,
                i.billing_period_end,
                i.issue_date,
                i.due_date,

                s.full_name AS subscriber_name,
                s.account_number,
                s.contact_number,
                s.email,
                s.address,

                ss.service_number,
                ss.account_type,
                ss.status AS service_status,

                p.plan_name
            FROM billing_adjustments ba
            LEFT JOIN invoices i ON i.id = ba.invoice_id
            LEFT JOIN subscribers s ON s.id = ba.subscriber_id
            LEFT JOIN subscriber_services ss ON ss.id = ba.service_id
            LEFT JOIN plans p ON p.id = i.plan_id
            WHERE ba.invoice_id = :invoice_id
            ORDER BY ba.id DESC
        ");

        $stmt->execute([
            ':invoice_id' => $invoiceId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO invoices (
                invoice_no,
                service_id,
                subscriber_id,
                plan_id,
                billing_period_start,
                billing_period_end,
                issue_date,
                amount,
                subtotal,
                discount_amount,
                tax_amount,
                total_amount,
                paid_amount,
                balance_amount,
                due_date,
                status,
                notes
            ) VALUES (
                :invoice_no,
                :service_id,
                :subscriber_id,
                :plan_id,
                :billing_period_start,
                :billing_period_end,
                :issue_date,
                :amount,
                :subtotal,
                :discount_amount,
                :tax_amount,
                :total_amount,
                :paid_amount,
                :balance_amount,
                :due_date,
                :status,
                :notes
            )
        ");

        $stmt->execute([
            ':invoice_no' => $data['invoice_no'],
            ':service_id' => $data['service_id'] ?? null,
            ':subscriber_id' => $data['subscriber_id'] ?? null,
            ':plan_id' => $data['plan_id'] ?? null,
            ':billing_period_start' => $data['billing_period_start'] ?? null,
            ':billing_period_end' => $data['billing_period_end'] ?? null,
            ':issue_date' => $data['issue_date'] ?? date('Y-m-d'),
            ':amount' => $data['amount'] ?? 0,
            ':subtotal' => $data['subtotal'] ?? 0,
            ':discount_amount' => $data['discount_amount'] ?? 0,
            ':tax_amount' => $data['tax_amount'] ?? 0,
            ':total_amount' => $data['total_amount'] ?? 0,
            ':paid_amount' => $data['paid_amount'] ?? 0,
            ':balance_amount' => $data['balance_amount'] ?? 0,
            ':due_date' => $data['due_date'] ?? null,
            ':status' => $data['status'] ?? 'UNPAID',
            ':notes' => $data['notes'] ?? null,
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Assign the public invoice number after insertion so its sequence is based
     * on the database-generated primary key and cannot race another request.
     */
    public function assignInvoiceNo(int $invoiceId, string $invoiceNo): void
    {
        $stmt = $this->db->prepare("
            UPDATE invoices
            SET invoice_no = :invoice_no
            WHERE id = :id
              AND invoice_no IS NULL
        ");
        $stmt->execute([
            ':id' => $invoiceId,
            ':invoice_no' => $invoiceNo,
        ]);

        if ($stmt->rowCount() !== 1) {
            throw new \RuntimeException('Unable to assign invoice number.');
        }
    }

    public function createItem(int $invoiceId, array $item): int
    {
        $quantity = (float)($item['quantity'] ?? 1);
        $unitPrice = (float)($item['unit_price'] ?? 0);

        $lineTotal = isset($item['line_total'])
            ? (float)$item['line_total']
            : round($quantity * $unitPrice, 2);

        $stmt = $this->db->prepare("
            INSERT INTO invoice_items (
                invoice_id,
                item_type,
                description,
                quantity,
                unit_price,
                line_total
            ) VALUES (
                :invoice_id,
                :item_type,
                :description,
                :quantity,
                :unit_price,
                :line_total
            )
        ");

        $stmt->execute([
            ':invoice_id' => $invoiceId,
            ':item_type' => $item['item_type'] ?? 'PLAN',
            ':description' => $item['description'] ?? 'Billing item',
            ':quantity' => $quantity,
            ':unit_price' => $unitPrice,
            ':line_total' => $lineTotal,
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function recalculateTotals(int $invoiceId): void
    {
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(line_total), 0)
            FROM invoice_items
            WHERE invoice_id = :invoice_id
        ");

        $stmt->execute([':invoice_id' => $invoiceId]);

        $subtotal = (float)$stmt->fetchColumn();

        $invoice = $this->find($invoiceId);

        if (!$invoice) {
            return;
        }

        $discount = (float)($invoice['discount_amount'] ?? 0);
        $tax = (float)($invoice['tax_amount'] ?? 0);
        $paid = (float)($invoice['paid_amount'] ?? 0);

        $total = max(0, round($subtotal - $discount + $tax, 2));
        $balance = max(0, round($total - $paid, 2));

        $status = $this->resolveStatus(
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
                total_amount = :total_amount,
                balance_amount = :balance_amount,
                status = :status
            WHERE id = :id
        ");

        $update->execute([
            ':amount' => $total,
            ':subtotal' => $subtotal,
            ':total_amount' => $total,
            ':balance_amount' => $balance,
            ':status' => $status,
            ':id' => $invoiceId,
        ]);
    }

    public function updatePaymentTotals(int $invoiceId): void
    {
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(pa.allocated_amount), 0)
            FROM payment_allocations pa
            INNER JOIN payments p ON p.id = pa.payment_id
            WHERE pa.invoice_id = :invoice_id
              AND p.payment_status = 'POSTED'
        ");

        $stmt->execute([':invoice_id' => $invoiceId]);

        $paid = (float)$stmt->fetchColumn();

        $invoice = $this->find($invoiceId);

        if (!$invoice) {
            return;
        }

        $total = (float)($invoice['total_amount'] ?? 0);
        $balance = max(0, round($total - $paid, 2));

        $status = $this->resolveStatus(
            $total,
            $paid,
            $balance,
            $invoice['due_date'] ?? null,
            $invoice['status'] ?? 'UNPAID'
        );

        $update = $this->db->prepare("
            UPDATE invoices
            SET
                paid_amount = :paid_amount,
                balance_amount = :balance_amount,
                status = :status
            WHERE id = :id
        ");

        $update->execute([
            ':paid_amount' => $paid,
            ':balance_amount' => $balance,
            ':status' => $status,
            ':id' => $invoiceId,
        ]);
    }

    public function cancel(int $id, ?int $userId = null): bool
    {
        $stmt = $this->db->prepare("
            UPDATE invoices
            SET
                status = 'CANCELLED',
                cancelled_at = NOW(),
                cancelled_by = :cancelled_by
            WHERE id = :id
              AND status != 'PAID'
        ");

        $stmt->execute([
            ':id' => $id,
            ':cancelled_by' => $userId,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function getServiceContext(int $serviceId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                ss.id AS service_id,
                ss.subscriber_id,
                ss.plan_id,
                ss.account_type,
                ss.status AS service_status,
                ss.next_due_date,
                ss.expires_at,
                ss.service_number,
                ss.ppp_username,
                s.full_name AS subscriber_name,
                s.account_number,
                p.plan_name,
                p.price,
                p.validity_days
            FROM subscriber_services ss
            INNER JOIN subscribers s ON s.id = ss.subscriber_id
            LEFT JOIN plans p ON p.id = ss.plan_id
            WHERE ss.id = :service_id
            LIMIT 1
        ");

        $stmt->execute([':service_id' => $serviceId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function cancelPendingGatewayTransactions(int $invoiceId): void
    {
        $stmt=$this->db->prepare("UPDATE payment_gateway_transactions SET gateway_status='CANCELLED',updated_at=NOW() WHERE invoice_id=:invoice_id AND gateway_status IN ('PENDING','UPDATED')");
        $stmt->execute([':invoice_id'=>$invoiceId]);
    }

    public function findByServiceAndPeriod(int $serviceId, string $periodStart, string $periodEnd): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM invoices
            WHERE service_id = :service_id
              AND billing_period_start = :billing_period_start
              AND billing_period_end = :billing_period_end
              AND status != 'CANCELLED'
            LIMIT 1
        ");

        $stmt->execute([
            ':service_id' => $serviceId,
            ':billing_period_start' => $periodStart,
            ':billing_period_end' => $periodEnd,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function updateServiceNextDueDate(int $serviceId, string $nextDueDate): void
    {
        $stmt = $this->db->prepare("
            UPDATE subscriber_services
            SET next_due_date = :next_due_date
            WHERE id = :service_id
        ");

        $stmt->execute([
            ':next_due_date' => $nextDueDate,
            ':service_id' => $serviceId,
        ]);
    }

    public function findDuePostpaidServices(?string $asOfDate = null, int $limit = 200): array
    {
        $asOfDate = $asOfDate ?: date('Y-m-d');

        $stmt = $this->db->prepare("
            SELECT
                ss.id AS service_id,
                ss.subscriber_id,
                ss.plan_id,
                ss.account_type,
                ss.status AS service_status,
                ss.next_due_date,
                ss.expires_at,
                ss.service_number,
                ss.ppp_username,

                s.full_name AS subscriber_name,
                s.account_number,

                p.plan_name,
                p.price,
                p.validity_days,
                p.plan_type
            FROM subscriber_services ss
            INNER JOIN subscribers s ON s.id = ss.subscriber_id
            INNER JOIN plans p ON p.id = ss.plan_id
            WHERE ss.status = 'ACTIVE'
              AND ss.account_type = 'POSTPAID'
              AND ss.next_due_date IS NOT NULL
              AND ss.next_due_date != '0000-00-00'
              AND ss.next_due_date <= :as_of_date
              AND s.deleted_at IS NULL
            ORDER BY ss.next_due_date ASC, ss.id ASC
            LIMIT :limit
        ");

        $stmt->bindValue(':as_of_date', $asOfDate);
        $stmt->bindValue(':limit', max(1, min(1000, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findOverdueInvoices(?string $asOfDate = null, int $limit = 500): array
    {
        $asOfDate = $asOfDate ?: date('Y-m-d');
        $limit = max(1, min(1000, $limit));

        $stmt = $this->db->prepare("
            SELECT
                i.*,

                s.full_name AS subscriber_name,
                s.account_number,
                s.contact_number,
                s.email,
                s.address,

                ss.service_number,
                ss.ppp_username,
                ss.account_type,
                ss.status AS service_status,

                p.plan_name,
                p.price AS plan_price,

                DATEDIFF(:as_of_date_for_days, i.due_date) AS days_overdue
            FROM invoices i
            LEFT JOIN subscribers s ON s.id = i.subscriber_id
            LEFT JOIN subscriber_services ss ON ss.id = i.service_id
            LEFT JOIN plans p ON p.id = i.plan_id
            WHERE i.status IN ('UNPAID', 'PARTIAL')
              AND i.balance_amount > 0
              AND i.due_date IS NOT NULL
              AND i.due_date != '0000-00-00'
              AND i.due_date < :as_of_date
            ORDER BY i.due_date ASC, i.id ASC
            LIMIT :limit
        ");

        $stmt->bindValue(':as_of_date_for_days', $asOfDate);
        $stmt->bindValue(':as_of_date', $asOfDate);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function markInvoiceOverdue(int $invoiceId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE invoices
            SET status = 'OVERDUE'
            WHERE id = :id
              AND status IN ('UNPAID', 'PARTIAL')
              AND balance_amount > 0
              AND due_date IS NOT NULL
              AND due_date != '0000-00-00'
              AND due_date < CURDATE()
        ");

        $stmt->execute([
            ':id' => $invoiceId,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function markOverdueInvoices(?string $asOfDate = null, int $limit = 500): array
    {
        $asOfDate = $asOfDate ?: date('Y-m-d');
        $limit = max(1, min(1000, $limit));

        $candidates = $this->findOverdueInvoices($asOfDate, $limit);
        $marked = 0;
        $items = [];

        foreach ($candidates as $invoice) {
            $invoiceId = (int)($invoice['id'] ?? 0);

            if ($invoiceId <= 0) {
                continue;
            }

            $stmt = $this->db->prepare("
                UPDATE invoices
                SET status = 'OVERDUE'
                WHERE id = :id
                  AND status IN ('UNPAID', 'PARTIAL')
                  AND balance_amount > 0
                  AND due_date IS NOT NULL
                  AND due_date != '0000-00-00'
                  AND due_date < :as_of_date
            ");

            $stmt->execute([
                ':id' => $invoiceId,
                ':as_of_date' => $asOfDate,
            ]);

            $updated = $stmt->rowCount() > 0;

            if ($updated) {
                $marked++;
            }

            $items[] = [
                'invoice_id' => $invoiceId,
                'invoice_no' => $invoice['invoice_no'] ?? null,
                'subscriber_id' => $invoice['subscriber_id'] ?? null,
                'subscriber_name' => $invoice['subscriber_name'] ?? null,
                'service_id' => $invoice['service_id'] ?? null,
                'due_date' => $invoice['due_date'] ?? null,
                'days_overdue' => isset($invoice['days_overdue']) ? (int)$invoice['days_overdue'] : null,
                'balance_amount' => $invoice['balance_amount'] ?? null,
                'previous_status' => $invoice['status'] ?? null,
                'new_status' => $updated ? 'OVERDUE' : ($invoice['status'] ?? null),
                'marked' => $updated,
            ];
        }

        return [
            'as_of_date' => $asOfDate,
            'checked' => count($candidates),
            'marked' => $marked,
            'skipped' => count($candidates) - $marked,
            'items' => $items,
        ];
    }

    private function resolveStatus(float $total, float $paid, float $balance, ?string $dueDate, string $currentStatus): string
    {
        if ($currentStatus === 'CANCELLED') {
            return 'CANCELLED';
        }

        if ($total <= 0) {
            return 'UNPAID';
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
}
