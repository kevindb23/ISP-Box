<?php

namespace App\Modules\Billing\Repositories;

use PDO;

class PaymentRepository
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
                p.*,
                i.invoice_no,
                i.total_amount,
                i.paid_amount,
                i.balance_amount,
                i.status AS invoice_status,
                s.full_name AS subscriber_name,
                s.account_number,
                s.contact_number,
                s.email,
                s.address,
                ss.service_number,
                ss.ppp_username,
                pl.plan_name
            FROM payments p
            LEFT JOIN invoices i ON i.id = p.invoice_id
            LEFT JOIN subscribers s ON s.id = p.subscriber_id
            LEFT JOIN subscriber_services ss ON ss.id = p.service_id
            LEFT JOIN plans pl ON pl.id = i.plan_id
            WHERE 1 = 1
        ";

        $params = [];

        if (!empty($filters['payment_status'])) {
            $sql .= " AND p.payment_status = :payment_status";
            $params[':payment_status'] = strtoupper((string)$filters['payment_status']);
        }

        if (!empty($filters['invoice_id'])) {
            $sql .= " AND p.invoice_id = :invoice_id";
            $params[':invoice_id'] = (int)$filters['invoice_id'];
        }

        if (!empty($filters['subscriber_id'])) {
            $sql .= " AND p.subscriber_id = :subscriber_id";
            $params[':subscriber_id'] = (int)$filters['subscriber_id'];
        }

        if (!empty($filters['search'])) {
            $sql .= "
                AND (
                    p.payment_no LIKE :search
                    OR p.reference_no LIKE :search
                    OR p.method LIKE :search
                    OR i.invoice_no LIKE :search
                    OR s.full_name LIKE :search
                    OR s.account_number LIKE :search
                    OR ss.service_number LIKE :search
                    OR ss.ppp_username LIKE :search
                    OR pl.plan_name LIKE :search
                )
            ";
            $params[':search'] = '%' . trim((string)$filters['search']) . '%';
        }

        $sql .= " ORDER BY p.id DESC LIMIT :limit OFFSET :offset";

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
            FROM payments p
            LEFT JOIN invoices i ON i.id = p.invoice_id
            LEFT JOIN subscribers s ON s.id = p.subscriber_id
            LEFT JOIN subscriber_services ss ON ss.id = p.service_id
            LEFT JOIN plans pl ON pl.id = i.plan_id
            WHERE 1 = 1
        ";

        $params = [];

        if (!empty($filters['payment_status'])) {
            $sql .= " AND p.payment_status = :payment_status";
            $params[':payment_status'] = strtoupper((string)$filters['payment_status']);
        }

        if (!empty($filters['invoice_id'])) {
            $sql .= " AND p.invoice_id = :invoice_id";
            $params[':invoice_id'] = (int)$filters['invoice_id'];
        }

        if (!empty($filters['subscriber_id'])) {
            $sql .= " AND p.subscriber_id = :subscriber_id";
            $params[':subscriber_id'] = (int)$filters['subscriber_id'];
        }

        if (!empty($filters['search'])) {
            $sql .= "
                AND (
                    p.payment_no LIKE :search
                    OR p.reference_no LIKE :search
                    OR p.method LIKE :search
                    OR i.invoice_no LIKE :search
                    OR s.full_name LIKE :search
                    OR s.account_number LIKE :search
                    OR ss.service_number LIKE :search
                    OR ss.ppp_username LIKE :search
                    OR pl.plan_name LIKE :search
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
                p.*,

                i.invoice_no,
                i.billing_period_start,
                i.billing_period_end,
                i.issue_date,
                i.due_date,
                i.amount AS invoice_amount,
                i.subtotal,
                i.discount_amount,
                i.tax_amount,
                i.total_amount,
                i.paid_amount,
                i.balance_amount,
                i.status AS invoice_status,
                i.notes AS invoice_notes,

                s.full_name AS subscriber_name,
                s.account_number,
                s.contact_number,
                s.email,
                s.address,

                ss.service_number,
                ss.ppp_username,
                ss.account_type,
                ss.status AS service_status,

                pl.plan_name,
                pl.price AS plan_price
            FROM payments p
            LEFT JOIN invoices i ON i.id = p.invoice_id
            LEFT JOIN subscribers s ON s.id = p.subscriber_id
            LEFT JOIN subscriber_services ss ON ss.id = p.service_id
            LEFT JOIN plans pl ON pl.id = i.plan_id
            WHERE p.id = :id
            LIMIT 1
        ");

        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findForUpdate(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM payments WHERE id = :id LIMIT 1 FOR UPDATE');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO payments (
                payment_no,
                invoice_id,
                subscriber_id,
                service_id,
                amount,
                payment_date,
                method,
                reference_no,
                payment_status,
                remarks,
                proof_file_path,
                proof_file_name,
                proof_file_type,
                submitted_by_user_id,
                received_by,
                posted_at
            ) VALUES (
                :payment_no,
                :invoice_id,
                :subscriber_id,
                :service_id,
                :amount,
                :payment_date,
                :method,
                :reference_no,
                :payment_status,
                :remarks,
                :proof_file_path,
                :proof_file_name,
                :proof_file_type,
                :submitted_by_user_id,
                :received_by,
                :posted_at
            )
        ");

        $paymentStatus = strtoupper((string)($data['payment_status'] ?? 'POSTED'));

        $stmt->execute([
            ':payment_no' => $data['payment_no'],
            ':invoice_id' => $data['invoice_id'] ?? null,
            ':subscriber_id' => $data['subscriber_id'] ?? null,
            ':service_id' => $data['service_id'] ?? null,
            ':amount' => $data['amount'] ?? 0,
            ':payment_date' => $data['payment_date'] ?? date('Y-m-d H:i:s'),
            ':method' => $data['method'] ?? 'CASH',
            ':reference_no' => $data['reference_no'] ?? null,
            ':payment_status' => $paymentStatus,
            ':remarks' => $data['remarks'] ?? null,
            ':proof_file_path' => $data['proof_file_path'] ?? null,
            ':proof_file_name' => $data['proof_file_name'] ?? null,
            ':proof_file_type' => $data['proof_file_type'] ?? null,
            ':submitted_by_user_id' => $data['submitted_by_user_id'] ?? null,
            ':received_by' => $data['received_by'] ?? null,
            ':posted_at' => $paymentStatus === 'POSTED' ? ($data['posted_at'] ?? date('Y-m-d H:i:s')) : null,
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function assignPaymentNo(int $paymentId, string $paymentNo): void
    {
        $stmt = $this->db->prepare('UPDATE payments SET payment_no = :payment_no WHERE id = :id AND payment_no IS NULL');
        $stmt->execute(['payment_no' => $paymentNo, 'id' => $paymentId]);
        if ($stmt->rowCount() !== 1) throw new \RuntimeException('Unable to assign payment number.');
    }

    public function createAllocation(int $paymentId, int $invoiceId, float $amount): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO payment_allocations (
                payment_id,
                invoice_id,
                allocated_amount
            ) VALUES (
                :payment_id,
                :invoice_id,
                :allocated_amount
            )
        ");

        $stmt->execute([
            ':payment_id' => $paymentId,
            ':invoice_id' => $invoiceId,
            ':allocated_amount' => $amount,
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function getAllocationsByPayment(int $paymentId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                pa.*,
                i.invoice_no,
                i.total_amount,
                i.paid_amount,
                i.balance_amount,
                i.status AS invoice_status
            FROM payment_allocations pa
            INNER JOIN invoices i ON i.id = pa.invoice_id
            WHERE pa.payment_id = :payment_id
            ORDER BY pa.id ASC
        ");

        $stmt->execute([':payment_id' => $paymentId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getPaymentsByInvoice(int $invoiceId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                p.*,
                pa.allocated_amount
            FROM payment_allocations pa
            INNER JOIN payments p ON p.id = pa.payment_id
            WHERE pa.invoice_id = :invoice_id
            ORDER BY p.payment_date DESC, p.id DESC
        ");

        $stmt->execute([':invoice_id' => $invoiceId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function void(int $paymentId, ?int $userId = null, ?string $reason = null): bool
    {
        $stmt = $this->db->prepare("
            UPDATE payments
            SET
                payment_status = 'VOIDED',
                voided_at = NOW(),
                voided_by = :voided_by,
                void_reason = :void_reason
            WHERE id = :id
              AND payment_status = 'POSTED'
        ");

        $stmt->execute([
            ':id' => $paymentId,
            ':voided_by' => $userId,
            ':void_reason' => $reason,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function approvePending(int $paymentId, int $reviewedBy): bool
    {
        $stmt = $this->db->prepare("UPDATE payments SET payment_status='POSTED', reviewed_by_user_id=:reviewed_by, reviewed_at=NOW(), posted_at=NOW(), received_by=:reviewed_by WHERE id=:id AND payment_status='PENDING'");
        $stmt->execute([':id'=>$paymentId, ':reviewed_by'=>$reviewedBy]);
        return $stmt->rowCount() === 1;
    }

    public function rejectPending(int $paymentId, int $reviewedBy, string $reason): bool
    {
        $stmt = $this->db->prepare("UPDATE payments SET payment_status='REJECTED', reviewed_by_user_id=:reviewed_by, reviewed_at=NOW(), rejection_reason=:reason WHERE id=:id AND payment_status='PENDING'");
        $stmt->execute([':id'=>$paymentId, ':reviewed_by'=>$reviewedBy, ':reason'=>$reason]);
        return $stmt->rowCount() === 1;
    }

    public function findProofForUser(int $paymentId, int $userId, bool $staff): ?array
    {
        $sql = "SELECT p.*,s.user_id AS subscriber_user_id FROM payments p LEFT JOIN subscribers s ON s.id=p.subscriber_id WHERE p.id=:id";
        if (!$staff) $sql .= " AND s.user_id=:user_id";
        $stmt=$this->db->prepare($sql . ' LIMIT 1');
        $params=[':id'=>$paymentId]; if(!$staff)$params[':user_id']=$userId;
        $stmt->execute($params); return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function subscriberIsSuspended(int $subscriberId): bool
    {
        $stmt=$this->db->prepare("SELECT COUNT(*) FROM subscribers s JOIN subscriber_services ss ON ss.subscriber_id=s.id WHERE s.id=:id AND (s.status='SUSPENDED' OR ss.status='SUSPENDED')");
        $stmt->execute([':id'=>$subscriberId]); return (int)$stmt->fetchColumn()>0;
    }

    public function subscriberHasOutstandingOverdueInvoices(int $subscriberId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM invoices WHERE subscriber_id=:id AND status IN ('OVERDUE','UNPAID','PARTIAL') AND due_date<CURDATE() AND balance_amount>0"
        );
        $stmt->execute([':id' => $subscriberId]);
        return (int)$stmt->fetchColumn() > 0;
    }

}
