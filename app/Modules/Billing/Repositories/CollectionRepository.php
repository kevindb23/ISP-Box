<?php

namespace App\Modules\Billing\Repositories;

use PDO;

class CollectionRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function agingSummary(?string $asOfDate = null): array
    {
        $asOfDate = $asOfDate ?: date('Y-m-d');

        $rows = $this->agingItems([
            'as_of_date' => $asOfDate,
            'limit' => 10000,
            'offset' => 0,
        ]);

        $summary = $this->emptySummary();

        foreach ($rows as $row) {
            $bucket = $row['aging_bucket_key'] ?? 'current';
            $balance = (float)($row['balance_amount'] ?? 0);

            if (!isset($summary[$bucket])) {
                continue;
            }

            $summary[$bucket]['count']++;
            $summary[$bucket]['amount'] += $balance;
        }

        foreach ($summary as &$bucket) {
            $bucket['amount'] = round((float)$bucket['amount'], 2);
        }

        unset($bucket);

        return $summary;
    }

    public function agingItems(array $filters = []): array
    {
        $asOfDate = $filters['as_of_date'] ?? date('Y-m-d');

        $sql = "
            SELECT
                i.id,
                i.invoice_no,
                i.service_id,
                i.subscriber_id,
                i.plan_id,
                i.billing_period_start,
                i.billing_period_end,
                i.issue_date,
                i.due_date,
                i.subtotal,
                i.discount_amount,
                i.tax_amount,
                i.total_amount,
                i.paid_amount,
                i.balance_amount,
                i.status,
                i.created_at,
                i.updated_at,

                s.full_name AS subscriber_name,
                s.account_number,
                s.contact_number,
                s.email,
                s.address,

                ss.service_number,
                ss.account_type,
                ss.status AS service_status,
                ss.next_due_date,

                p.plan_name,

                CASE
                    WHEN i.due_date IS NULL THEN 0
                    WHEN DATEDIFF(:as_of_date_select, i.due_date) < 0 THEN 0
                    ELSE DATEDIFF(:as_of_date_select2, i.due_date)
                END AS days_overdue,

                CASE
                    WHEN i.due_date IS NULL THEN 'current'
                    WHEN DATEDIFF(:as_of_date_bucket1, i.due_date) <= 0 THEN 'current'
                    WHEN DATEDIFF(:as_of_date_bucket2, i.due_date) BETWEEN 1 AND 7 THEN 'overdue_1_7'
                    WHEN DATEDIFF(:as_of_date_bucket3, i.due_date) BETWEEN 8 AND 30 THEN 'overdue_8_30'
                    WHEN DATEDIFF(:as_of_date_bucket4, i.due_date) BETWEEN 31 AND 60 THEN 'overdue_31_60'
                    ELSE 'overdue_60_plus'
                END AS aging_bucket_key,

                CASE
                    WHEN i.due_date IS NULL THEN 'Current'
                    WHEN DATEDIFF(:as_of_date_label1, i.due_date) <= 0 THEN 'Current'
                    WHEN DATEDIFF(:as_of_date_label2, i.due_date) BETWEEN 1 AND 7 THEN '1-7 Days Overdue'
                    WHEN DATEDIFF(:as_of_date_label3, i.due_date) BETWEEN 8 AND 30 THEN '8-30 Days Overdue'
                    WHEN DATEDIFF(:as_of_date_label4, i.due_date) BETWEEN 31 AND 60 THEN '31-60 Days Overdue'
                    ELSE '60+ Days Overdue'
                END AS aging_bucket_label

            FROM invoices i
            LEFT JOIN subscribers s ON s.id = i.subscriber_id
            LEFT JOIN subscriber_services ss ON ss.id = i.service_id
            LEFT JOIN plans p ON p.id = i.plan_id
            WHERE i.status IN ('UNPAID', 'PARTIAL', 'OVERDUE')
              AND i.balance_amount > 0
              AND i.status != 'CANCELLED'
        ";

        $params = [
            ':as_of_date_select' => $asOfDate,
            ':as_of_date_select2' => $asOfDate,
            ':as_of_date_bucket1' => $asOfDate,
            ':as_of_date_bucket2' => $asOfDate,
            ':as_of_date_bucket3' => $asOfDate,
            ':as_of_date_bucket4' => $asOfDate,
            ':as_of_date_label1' => $asOfDate,
            ':as_of_date_label2' => $asOfDate,
            ':as_of_date_label3' => $asOfDate,
            ':as_of_date_label4' => $asOfDate,
        ];

        if (!empty($filters['bucket'])) {
            $bucket = strtolower((string)$filters['bucket']);

            if ($bucket === 'current') {
                $sql .= " AND (i.due_date IS NULL OR DATEDIFF(:as_of_date_filter_current, i.due_date) <= 0)";
                $params[':as_of_date_filter_current'] = $asOfDate;
            }

            if ($bucket === 'overdue_1_7') {
                $sql .= " AND DATEDIFF(:as_of_date_filter_1, i.due_date) BETWEEN 1 AND 7";
                $params[':as_of_date_filter_1'] = $asOfDate;
            }

            if ($bucket === 'overdue_8_30') {
                $sql .= " AND DATEDIFF(:as_of_date_filter_2, i.due_date) BETWEEN 8 AND 30";
                $params[':as_of_date_filter_2'] = $asOfDate;
            }

            if ($bucket === 'overdue_31_60') {
                $sql .= " AND DATEDIFF(:as_of_date_filter_3, i.due_date) BETWEEN 31 AND 60";
                $params[':as_of_date_filter_3'] = $asOfDate;
            }

            if ($bucket === 'overdue_60_plus') {
                $sql .= " AND DATEDIFF(:as_of_date_filter_4, i.due_date) > 60";
                $params[':as_of_date_filter_4'] = $asOfDate;
            }
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
                    OR p.plan_name LIKE :search
                    OR s.contact_number LIKE :search
                    OR s.email LIKE :search
                )
            ";

            $params[':search'] = '%' . trim((string)$filters['search']) . '%';
        }

        $sql .= "
            ORDER BY
                CASE
                    WHEN i.due_date IS NULL THEN 0
                    ELSE DATEDIFF(:as_of_date_order, i.due_date)
                END DESC,
                i.due_date ASC,
                i.id DESC
            LIMIT :limit OFFSET :offset
        ";

        $params[':as_of_date_order'] = $asOfDate;

        $limit = isset($filters['limit']) ? max(1, min(500, (int)$filters['limit'])) : 100;
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

    public function agingCount(array $filters = []): int
    {
        $asOfDate = $filters['as_of_date'] ?? date('Y-m-d');

        $sql = "
            SELECT COUNT(*)
            FROM invoices i
            LEFT JOIN subscribers s ON s.id = i.subscriber_id
            LEFT JOIN subscriber_services ss ON ss.id = i.service_id
            LEFT JOIN plans p ON p.id = i.plan_id
            WHERE i.status IN ('UNPAID', 'PARTIAL', 'OVERDUE')
              AND i.balance_amount > 0
              AND i.status != 'CANCELLED'
        ";

        $params = [];

        if (!empty($filters['bucket'])) {
            $bucket = strtolower((string)$filters['bucket']);

            if ($bucket === 'current') {
                $sql .= " AND (i.due_date IS NULL OR DATEDIFF(:as_of_date_filter_current, i.due_date) <= 0)";
                $params[':as_of_date_filter_current'] = $asOfDate;
            }

            if ($bucket === 'overdue_1_7') {
                $sql .= " AND DATEDIFF(:as_of_date_filter_1, i.due_date) BETWEEN 1 AND 7";
                $params[':as_of_date_filter_1'] = $asOfDate;
            }

            if ($bucket === 'overdue_8_30') {
                $sql .= " AND DATEDIFF(:as_of_date_filter_2, i.due_date) BETWEEN 8 AND 30";
                $params[':as_of_date_filter_2'] = $asOfDate;
            }

            if ($bucket === 'overdue_31_60') {
                $sql .= " AND DATEDIFF(:as_of_date_filter_3, i.due_date) BETWEEN 31 AND 60";
                $params[':as_of_date_filter_3'] = $asOfDate;
            }

            if ($bucket === 'overdue_60_plus') {
                $sql .= " AND DATEDIFF(:as_of_date_filter_4, i.due_date) > 60";
                $params[':as_of_date_filter_4'] = $asOfDate;
            }
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
                    OR p.plan_name LIKE :search
                    OR s.contact_number LIKE :search
                    OR s.email LIKE :search
                )
            ";

            $params[':search'] = '%' . trim((string)$filters['search']) . '%';
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn();
    }

    private function emptySummary(): array
    {
        return [
            'current' => [
                'label' => 'Current',
                'count' => 0,
                'amount' => 0,
            ],
            'overdue_1_7' => [
                'label' => '1-7 Days',
                'count' => 0,
                'amount' => 0,
            ],
            'overdue_8_30' => [
                'label' => '8-30 Days',
                'count' => 0,
                'amount' => 0,
            ],
            'overdue_31_60' => [
                'label' => '31-60 Days',
                'count' => 0,
                'amount' => 0,
            ],
            'overdue_60_plus' => [
                'label' => '60+ Days',
                'count' => 0,
                'amount' => 0,
            ],
        ];
    }
}