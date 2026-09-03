<?php

namespace App\Modules\Billing\Repositories;

use PDO;

class AdjustmentRepository
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
            WHERE 1 = 1
        ";

        $params = [];

        if (!empty($filters['invoice_id'])) {
            $sql .= " AND ba.invoice_id = :invoice_id";
            $params[':invoice_id'] = (int)$filters['invoice_id'];
        }

        if (!empty($filters['subscriber_id'])) {
            $sql .= " AND ba.subscriber_id = :subscriber_id";
            $params[':subscriber_id'] = (int)$filters['subscriber_id'];
        }

        if (!empty($filters['service_id'])) {
            $sql .= " AND ba.service_id = :service_id";
            $params[':service_id'] = (int)$filters['service_id'];
        }

        if (!empty($filters['status'])) {
            $sql .= " AND ba.status = :status";
            $params[':status'] = strtoupper((string)$filters['status']);
        }

        if (!empty($filters['adjustment_type'])) {
            $sql .= " AND ba.adjustment_type = :adjustment_type";
            $params[':adjustment_type'] = strtoupper((string)$filters['adjustment_type']);
        }

        if (!empty($filters['search'])) {
            $sql .= "
                AND (
                    ba.adjustment_no LIKE :search
                    OR ba.reason LIKE :search
                    OR i.invoice_no LIKE :search
                    OR s.full_name LIKE :search
                    OR s.account_number LIKE :search
                    OR ss.service_number LIKE :search
                    OR p.plan_name LIKE :search
                )
            ";

            $params[':search'] = '%' . trim((string)$filters['search']) . '%';
        }

        $sql .= " ORDER BY ba.id DESC LIMIT :limit OFFSET :offset";

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
            FROM billing_adjustments ba
            LEFT JOIN invoices i ON i.id = ba.invoice_id
            LEFT JOIN subscribers s ON s.id = ba.subscriber_id
            LEFT JOIN subscriber_services ss ON ss.id = ba.service_id
            LEFT JOIN plans p ON p.id = i.plan_id
            WHERE 1 = 1
        ";

        $params = [];

        if (!empty($filters['invoice_id'])) {
            $sql .= " AND ba.invoice_id = :invoice_id";
            $params[':invoice_id'] = (int)$filters['invoice_id'];
        }

        if (!empty($filters['subscriber_id'])) {
            $sql .= " AND ba.subscriber_id = :subscriber_id";
            $params[':subscriber_id'] = (int)$filters['subscriber_id'];
        }

        if (!empty($filters['service_id'])) {
            $sql .= " AND ba.service_id = :service_id";
            $params[':service_id'] = (int)$filters['service_id'];
        }

        if (!empty($filters['status'])) {
            $sql .= " AND ba.status = :status";
            $params[':status'] = strtoupper((string)$filters['status']);
        }

        if (!empty($filters['adjustment_type'])) {
            $sql .= " AND ba.adjustment_type = :adjustment_type";
            $params[':adjustment_type'] = strtoupper((string)$filters['adjustment_type']);
        }

        if (!empty($filters['search'])) {
            $sql .= "
                AND (
                    ba.adjustment_no LIKE :search
                    OR ba.reason LIKE :search
                    OR i.invoice_no LIKE :search
                    OR s.full_name LIKE :search
                    OR s.account_number LIKE :search
                    OR ss.service_number LIKE :search
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
                i.notes AS invoice_notes,

                s.full_name AS subscriber_name,
                s.account_number,
                s.contact_number,
                s.email,
                s.address,

                ss.service_number,
                ss.account_type,
                ss.status AS service_status,

                p.plan_name,
                p.price AS plan_price
            FROM billing_adjustments ba
            LEFT JOIN invoices i ON i.id = ba.invoice_id
            LEFT JOIN subscribers s ON s.id = ba.subscriber_id
            LEFT JOIN subscriber_services ss ON ss.id = ba.service_id
            LEFT JOIN plans p ON p.id = i.plan_id
            WHERE ba.id = :id
            LIMIT 1
        ");

        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findForUpdate(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM billing_adjustments WHERE id = :id LIMIT 1 FOR UPDATE');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByAdjustmentNo(string $adjustmentNo): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM billing_adjustments
            WHERE adjustment_no = :adjustment_no
            LIMIT 1
        ");

        $stmt->execute([
            ':adjustment_no' => $adjustmentNo,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findByInvoice(int $invoiceId): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM billing_adjustments
            WHERE invoice_id = :invoice_id
            ORDER BY id DESC
        ");

        $stmt->execute([
            ':invoice_id' => $invoiceId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO billing_adjustments (
                adjustment_no,
                invoice_id,
                subscriber_id,
                service_id,
                adjustment_type,
                amount,
                reason,
                status,
                created_by,
                approved_by,
                approved_at
            ) VALUES (
                :adjustment_no,
                :invoice_id,
                :subscriber_id,
                :service_id,
                :adjustment_type,
                :amount,
                :reason,
                :status,
                :created_by,
                :approved_by,
                :approved_at
            )
        ");

        $status = strtoupper((string)($data['status'] ?? 'POSTED'));

        $stmt->execute([
            ':adjustment_no' => $data['adjustment_no'],
            ':invoice_id' => (int)$data['invoice_id'],
            ':subscriber_id' => $data['subscriber_id'] ?? null,
            ':service_id' => $data['service_id'] ?? null,
            ':adjustment_type' => strtoupper((string)($data['adjustment_type'] ?? 'CREDIT')),
            ':amount' => (float)($data['amount'] ?? 0),
            ':reason' => $data['reason'] ?? null,
            ':status' => $status,
            ':created_by' => $data['created_by'] ?? null,
            ':approved_by' => $data['approved_by'] ?? null,
            ':approved_at' => $data['approved_at'] ?? ($status === 'POSTED' ? date('Y-m-d H:i:s') : null),
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function assignAdjustmentNo(int $id, string $expectedTemporaryNo, string $adjustmentNo): void
    {
        $stmt = $this->db->prepare("
            UPDATE billing_adjustments
            SET adjustment_no = :adjustment_no
            WHERE id = :id
              AND adjustment_no = :expected_temporary_no
        ");
        $stmt->execute([
            ':id' => $id,
            ':expected_temporary_no' => $expectedTemporaryNo,
            ':adjustment_no' => $adjustmentNo,
        ]);

        if ($stmt->rowCount() !== 1) {
            throw new \RuntimeException('Unable to assign adjustment number.');
        }
    }

    public function void(int $id, ?int $userId = null, ?string $reason = null): bool
    {
        $stmt = $this->db->prepare("
            UPDATE billing_adjustments
            SET
                status = 'VOIDED',
                voided_by = :voided_by,
                voided_at = NOW(),
                void_reason = :void_reason
            WHERE id = :id
              AND status = 'POSTED'
        ");

        $stmt->execute([
            ':id' => $id,
            ':voided_by' => $userId,
            ':void_reason' => $reason,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function getPostedAdjustmentTotalsByInvoice(int $invoiceId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                COALESCE(SUM(
                    CASE
                        WHEN adjustment_type IN ('CREDIT', 'DISCOUNT', 'REBATE', 'WAIVER')
                        THEN amount
                        ELSE 0
                    END
                ), 0) AS credit_total,

                COALESCE(SUM(
                    CASE
                        WHEN adjustment_type IN ('DEBIT', 'CORRECTION')
                        THEN amount
                        ELSE 0
                    END
                ), 0) AS debit_total,

                COALESCE(SUM(
                    CASE
                        WHEN adjustment_type IN ('CREDIT', 'DISCOUNT', 'REBATE', 'WAIVER')
                        THEN amount
                        WHEN adjustment_type IN ('DEBIT', 'CORRECTION')
                        THEN amount * -1
                        ELSE 0
                    END
                ), 0) AS net_credit_total
            FROM billing_adjustments
            WHERE invoice_id = :invoice_id
              AND status = 'POSTED'
        ");

        $stmt->execute([
            ':invoice_id' => $invoiceId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'credit_total' => (float)($row['credit_total'] ?? 0),
            'debit_total' => (float)($row['debit_total'] ?? 0),
            'net_credit_total' => (float)($row['net_credit_total'] ?? 0),
        ];
    }

    public function getPostedAdjustmentsByInvoice(int $invoiceId): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM billing_adjustments
            WHERE invoice_id = :invoice_id
              AND status = 'POSTED'
            ORDER BY id ASC
        ");

        $stmt->execute([
            ':invoice_id' => $invoiceId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function invoiceItemsSubtotal(int $invoiceId): float
    {
        $stmt = $this->db->prepare('SELECT COALESCE(SUM(line_total), 0) FROM invoice_items WHERE invoice_id = :invoice_id');
        $stmt->execute([':invoice_id' => $invoiceId]);
        return (float)$stmt->fetchColumn();
    }

    public function postedPaidAmount(int $invoiceId): float
    {
        $stmt = $this->db->prepare("SELECT COALESCE(SUM(pa.allocated_amount), 0) FROM payment_allocations pa INNER JOIN payments p ON p.id = pa.payment_id WHERE pa.invoice_id = :invoice_id AND p.payment_status = 'POSTED'");
        $stmt->execute([':invoice_id' => $invoiceId]);
        return round((float)$stmt->fetchColumn(), 2);
    }

    public function updateInvoiceFinancials(int $invoiceId, array $values): void
    {
        $stmt = $this->db->prepare("UPDATE invoices SET amount = :amount, subtotal = :subtotal, discount_amount = :discount_amount, tax_amount = :tax_amount, total_amount = :total_amount, paid_amount = :paid_amount, balance_amount = :balance_amount, status = :status WHERE id = :id");
        $stmt->execute([
            ':amount' => $values['total'],
            ':subtotal' => $values['subtotal'],
            ':discount_amount' => $values['discount_amount'],
            ':tax_amount' => $values['tax_amount'],
            ':total_amount' => $values['total'],
            ':paid_amount' => $values['paid'],
            ':balance_amount' => $values['balance'],
            ':status' => $values['status'],
            ':id' => $invoiceId,
        ]);
    }

    public function createActivityLog(array $data): void
    {
        $stmt = $this->db->prepare("INSERT INTO activity_logs (entity_type, entity_id, action, status, title, message, old_values, new_values, meta_json, performed_by, ip_address, user_agent, created_at) VALUES (:entity_type, :entity_id, :action, :status, :title, :message, :old_values, :new_values, :meta_json, :performed_by, :ip_address, :user_agent, NOW())");
        $stmt->execute([
            ':entity_type' => $data['entity_type'],
            ':entity_id' => $data['entity_id'],
            ':action' => $data['action'],
            ':status' => $data['status'],
            ':title' => $data['title'],
            ':message' => $data['message'],
            ':old_values' => !empty($data['old_values']) ? json_encode($data['old_values'], JSON_UNESCAPED_SLASHES) : null,
            ':new_values' => !empty($data['new_values']) ? json_encode($data['new_values'], JSON_UNESCAPED_SLASHES) : null,
            ':meta_json' => !empty($data['meta']) ? json_encode($data['meta'], JSON_UNESCAPED_SLASHES) : null,
            ':performed_by' => $data['performed_by'] ?: null,
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);
    }
}
