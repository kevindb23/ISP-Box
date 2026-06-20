<?php

namespace App\Modules\Billing\Repositories;

use PDO;

class SubscriberBillingRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getSubscriberByUserId(int $userId): ?array
    {
        /*
         * This assumes your subscribers table has user_id.
         * If your subscriber-user link uses another column/table,
         * send me DESCRIBE subscribers; and DESCRIBE users; so we can adjust.
         */
        $stmt = $this->db->prepare("
            SELECT
                s.*
            FROM subscribers s
            WHERE s.user_id = :user_id
            LIMIT 1
        ");

        $stmt->execute([
            ':user_id' => $userId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function getSubscriberById(int $subscriberId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                s.*
            FROM subscribers s
            WHERE s.id = :subscriber_id
            LIMIT 1
        ");

        $stmt->execute([
            ':subscriber_id' => $subscriberId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function getBillingSummary(int $subscriberId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) AS total_invoices,

                COALESCE(SUM(CASE
                    WHEN status != 'CANCELLED'
                    THEN total_amount
                    ELSE 0
                END), 0) AS lifetime_billed,

                COALESCE(SUM(CASE
                    WHEN status != 'CANCELLED'
                    THEN paid_amount
                    ELSE 0
                END), 0) AS lifetime_paid,

                COALESCE(SUM(CASE
                    WHEN status IN ('UNPAID', 'PARTIAL', 'OVERDUE')
                    THEN balance_amount
                    ELSE 0
                END), 0) AS outstanding_balance,

                COALESCE(SUM(CASE
                    WHEN status = 'OVERDUE'
                    THEN balance_amount
                    ELSE 0
                END), 0) AS overdue_balance,

                COUNT(CASE
                    WHEN status = 'UNPAID'
                    THEN 1
                END) AS unpaid_count,

                COUNT(CASE
                    WHEN status = 'PARTIAL'
                    THEN 1
                END) AS partial_count,

                COUNT(CASE
                    WHEN status = 'OVERDUE'
                    THEN 1
                END) AS overdue_count,

                COUNT(CASE
                    WHEN status = 'PAID'
                    THEN 1
                END) AS paid_count
            FROM invoices
            WHERE subscriber_id = :subscriber_id
        ");

        $stmt->execute([
            ':subscriber_id' => $subscriberId,
        ]);

        $summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total_invoices' => (int)($summary['total_invoices'] ?? 0),
            'lifetime_billed' => (float)($summary['lifetime_billed'] ?? 0),
            'lifetime_paid' => (float)($summary['lifetime_paid'] ?? 0),
            'outstanding_balance' => (float)($summary['outstanding_balance'] ?? 0),
            'overdue_balance' => (float)($summary['overdue_balance'] ?? 0),
            'unpaid_count' => (int)($summary['unpaid_count'] ?? 0),
            'partial_count' => (int)($summary['partial_count'] ?? 0),
            'overdue_count' => (int)($summary['overdue_count'] ?? 0),
            'paid_count' => (int)($summary['paid_count'] ?? 0),
        ];
    }

    public function getActiveServices(int $subscriberId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                ss.id AS service_id,
                ss.service_number,
                ss.ppp_username,
                ss.account_type,
                ss.status AS service_status,
                ss.next_due_date,
                ss.expires_at,
                ss.created_at,
                ss.updated_at,

                p.id AS plan_id,
                p.plan_name,
                p.price,
                p.plan_type,
                p.validity_days,
                p.speed_down,
                p.speed_up,
                p.speed_mbps
            FROM subscriber_services ss
            LEFT JOIN plans p ON p.id = ss.plan_id
            WHERE ss.subscriber_id = :subscriber_id
            ORDER BY ss.id DESC
        ");

        $stmt->execute([
            ':subscriber_id' => $subscriberId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getLatestOpenInvoice(int $subscriberId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                i.*,
                ss.service_number,
                ss.ppp_username,
                p.plan_name
            FROM invoices i
            LEFT JOIN subscriber_services ss ON ss.id = i.service_id
            LEFT JOIN plans p ON p.id = i.plan_id
            WHERE i.subscriber_id = :subscriber_id
              AND i.status IN ('UNPAID', 'PARTIAL', 'OVERDUE')
            ORDER BY
                CASE i.status
                    WHEN 'OVERDUE' THEN 1
                    WHEN 'PARTIAL' THEN 2
                    WHEN 'UNPAID' THEN 3
                    ELSE 4
                END ASC,
                i.due_date ASC,
                i.id DESC
            LIMIT 1
        ");

        $stmt->execute([
            ':subscriber_id' => $subscriberId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function listInvoices(
        int $subscriberId,
        array $filters = []
    ): array {
        $sql = "
            SELECT
                i.*,
                ss.service_number,
                ss.ppp_username,
                p.plan_name
            FROM invoices i
            LEFT JOIN subscriber_services ss ON ss.id = i.service_id
            LEFT JOIN plans p ON p.id = i.plan_id
            WHERE i.subscriber_id = :subscriber_id
        ";

        $params = [
            ':subscriber_id' => $subscriberId,
        ];

        if (!empty($filters['status'])) {
            $sql .= " AND i.status = :status";
            $params[':status'] = strtoupper((string)$filters['status']);
        }

        if (!empty($filters['service_id'])) {
            $sql .= " AND i.service_id = :service_id";
            $params[':service_id'] = (int)$filters['service_id'];
        }

        if (!empty($filters['search'])) {
            $sql .= "
                AND (
                    i.invoice_no LIKE :search
                    OR p.plan_name LIKE :search
                    OR ss.service_number LIKE :search
                    OR ss.ppp_username LIKE :search
                )
            ";

            $params[':search'] = '%' . trim((string)$filters['search']) . '%';
        }

        $sql .= " ORDER BY i.id DESC LIMIT :limit OFFSET :offset";

        $limit = isset($filters['limit'])
            ? max(1, min(100, (int)$filters['limit']))
            : 20;

        $offset = isset($filters['offset'])
            ? max(0, (int)$filters['offset'])
            : 0;

        $stmt = $this->db->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function countInvoices(
        int $subscriberId,
        array $filters = []
    ): int {
        $sql = "
            SELECT COUNT(*)
            FROM invoices i
            LEFT JOIN subscriber_services ss ON ss.id = i.service_id
            LEFT JOIN plans p ON p.id = i.plan_id
            WHERE i.subscriber_id = :subscriber_id
        ";

        $params = [
            ':subscriber_id' => $subscriberId,
        ];

        if (!empty($filters['status'])) {
            $sql .= " AND i.status = :status";
            $params[':status'] = strtoupper((string)$filters['status']);
        }

        if (!empty($filters['service_id'])) {
            $sql .= " AND i.service_id = :service_id";
            $params[':service_id'] = (int)$filters['service_id'];
        }

        if (!empty($filters['search'])) {
            $sql .= "
                AND (
                    i.invoice_no LIKE :search
                    OR p.plan_name LIKE :search
                    OR ss.service_number LIKE :search
                    OR ss.ppp_username LIKE :search
                )
            ";

            $params[':search'] = '%' . trim((string)$filters['search']) . '%';
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn();
    }

    public function findInvoiceForSubscriber(
        int $subscriberId,
        int $invoiceId
    ): ?array {
        $stmt = $this->db->prepare("
            SELECT
                i.*,
                ss.service_number,
                ss.ppp_username,
                ss.account_type,
                ss.status AS service_status,
                p.plan_name,
                p.price AS plan_price,
                p.speed_down,
                p.speed_up,
                p.speed_mbps
            FROM invoices i
            LEFT JOIN subscriber_services ss ON ss.id = i.service_id
            LEFT JOIN plans p ON p.id = i.plan_id
            WHERE i.id = :invoice_id
              AND i.subscriber_id = :subscriber_id
            LIMIT 1
        ");

        $stmt->execute([
            ':invoice_id' => $invoiceId,
            ':subscriber_id' => $subscriberId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function getInvoiceItems(int $invoiceId): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM invoice_items
            WHERE invoice_id = :invoice_id
            ORDER BY id ASC
        ");

        $stmt->execute([
            ':invoice_id' => $invoiceId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getInvoicePayments(int $invoiceId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                p.id,
                p.payment_no,
                p.invoice_id,
                p.subscriber_id,
                p.service_id,
                p.amount,
                p.payment_date,
                p.method,
                p.reference_no,
                p.payment_status,
                p.remarks,
                p.posted_at,
                p.voided_at,
                p.created_at,
                pa.allocated_amount
            FROM payment_allocations pa
            INNER JOIN payments p ON p.id = pa.payment_id
            WHERE pa.invoice_id = :invoice_id
            ORDER BY p.payment_date DESC, p.id DESC
        ");

        $stmt->execute([
            ':invoice_id' => $invoiceId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listPayments(
        int $subscriberId,
        array $filters = []
    ): array {
        $sql = "
            SELECT
                p.*,
                i.invoice_no,
                i.billing_period_start,
                i.billing_period_end,
                i.total_amount AS invoice_total_amount,
                i.balance_amount AS invoice_balance_amount,
                i.status AS invoice_status
            FROM payments p
            LEFT JOIN invoices i ON i.id = p.invoice_id
            WHERE p.subscriber_id = :subscriber_id
        ";

        $params = [
            ':subscriber_id' => $subscriberId,
        ];

        if (!empty($filters['payment_status'])) {
            $sql .= " AND p.payment_status = :payment_status";
            $params[':payment_status'] = strtoupper((string)$filters['payment_status']);
        }

        if (!empty($filters['service_id'])) {
            $sql .= " AND p.service_id = :service_id";
            $params[':service_id'] = (int)$filters['service_id'];
        }

        if (!empty($filters['search'])) {
            $sql .= "
                AND (
                    p.payment_no LIKE :search
                    OR p.reference_no LIKE :search
                    OR p.method LIKE :search
                    OR i.invoice_no LIKE :search
                )
            ";

            $params[':search'] = '%' . trim((string)$filters['search']) . '%';
        }

        $sql .= " ORDER BY p.id DESC LIMIT :limit OFFSET :offset";

        $limit = isset($filters['limit'])
            ? max(1, min(100, (int)$filters['limit']))
            : 20;

        $offset = isset($filters['offset'])
            ? max(0, (int)$filters['offset'])
            : 0;

        $stmt = $this->db->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function countPayments(
        int $subscriberId,
        array $filters = []
    ): int {
        $sql = "
            SELECT COUNT(*)
            FROM payments p
            LEFT JOIN invoices i ON i.id = p.invoice_id
            WHERE p.subscriber_id = :subscriber_id
        ";

        $params = [
            ':subscriber_id' => $subscriberId,
        ];

        if (!empty($filters['payment_status'])) {
            $sql .= " AND p.payment_status = :payment_status";
            $params[':payment_status'] = strtoupper((string)$filters['payment_status']);
        }

        if (!empty($filters['service_id'])) {
            $sql .= " AND p.service_id = :service_id";
            $params[':service_id'] = (int)$filters['service_id'];
        }

        if (!empty($filters['search'])) {
            $sql .= "
                AND (
                    p.payment_no LIKE :search
                    OR p.reference_no LIKE :search
                    OR p.method LIKE :search
                    OR i.invoice_no LIKE :search
                )
            ";

            $params[':search'] = '%' . trim((string)$filters['search']) . '%';
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn();
    }
}