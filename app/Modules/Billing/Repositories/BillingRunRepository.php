<?php

namespace App\Modules\Billing\Repositories;

use PDO;

class BillingRunRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function createRun(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO billing_runs (
                run_no,
                run_type,
                as_of_date,
                checked_count,
                created_count,
                skipped_count,
                failed_count,
                status,
                message,
                triggered_by,
                started_at,
                completed_at
            ) VALUES (
                :run_no,
                :run_type,
                :as_of_date,
                :checked_count,
                :created_count,
                :skipped_count,
                :failed_count,
                :status,
                :message,
                :triggered_by,
                :started_at,
                :completed_at
            )
        ");

        $stmt->execute([
            ':run_no' => $data['run_no'],
            ':run_type' => $data['run_type'] ?? 'MANUAL',
            ':as_of_date' => $data['as_of_date'],
            ':checked_count' => $data['checked_count'] ?? 0,
            ':created_count' => $data['created_count'] ?? 0,
            ':skipped_count' => $data['skipped_count'] ?? 0,
            ':failed_count' => $data['failed_count'] ?? 0,
            ':status' => $data['status'] ?? 'SUCCESS',
            ':message' => $data['message'] ?? null,
            ':triggered_by' => $data['triggered_by'] ?? null,
            ':started_at' => $data['started_at'] ?? date('Y-m-d H:i:s'),
            ':completed_at' => $data['completed_at'] ?? null,
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function updateRunSummary(int $runId, array $data): void
    {
        $stmt = $this->db->prepare("
            UPDATE billing_runs
            SET
                checked_count = :checked_count,
                created_count = :created_count,
                skipped_count = :skipped_count,
                failed_count = :failed_count,
                status = :status,
                message = :message,
                completed_at = :completed_at
            WHERE id = :id
        ");

        $stmt->execute([
            ':checked_count' => $data['checked_count'] ?? 0,
            ':created_count' => $data['created_count'] ?? 0,
            ':skipped_count' => $data['skipped_count'] ?? 0,
            ':failed_count' => $data['failed_count'] ?? 0,
            ':status' => $data['status'] ?? 'SUCCESS',
            ':message' => $data['message'] ?? null,
            ':completed_at' => $data['completed_at'] ?? date('Y-m-d H:i:s'),
            ':id' => $runId,
        ]);
    }

    public function createRunItem(int $runId, array $item): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO billing_run_items (
                billing_run_id,
                service_id,
                subscriber_id,
                plan_id,
                invoice_id,
                subscriber_name,
                account_number,
                plan_name,
                result_status,
                reason,
                error_message,
                billing_period_start,
                billing_period_end,
                next_due_date,
                payload_json
            ) VALUES (
                :billing_run_id,
                :service_id,
                :subscriber_id,
                :plan_id,
                :invoice_id,
                :subscriber_name,
                :account_number,
                :plan_name,
                :result_status,
                :reason,
                :error_message,
                :billing_period_start,
                :billing_period_end,
                :next_due_date,
                :payload_json
            )
        ");

        $stmt->execute([
            ':billing_run_id' => $runId,
            ':service_id' => $item['service_id'] ?? null,
            ':subscriber_id' => $item['subscriber_id'] ?? null,
            ':plan_id' => $item['plan_id'] ?? null,
            ':invoice_id' => $item['invoice_id'] ?? null,
            ':subscriber_name' => $item['subscriber_name'] ?? null,
            ':account_number' => $item['account_number'] ?? null,
            ':plan_name' => $item['plan_name'] ?? null,
            ':result_status' => $item['result_status'] ?? 'SKIPPED',
            ':reason' => $item['reason'] ?? null,
            ':error_message' => $item['error_message'] ?? null,
            ':billing_period_start' => $item['billing_period_start'] ?? null,
            ':billing_period_end' => $item['billing_period_end'] ?? null,
            ':next_due_date' => $item['next_due_date'] ?? null,
            ':payload_json' => $item['payload_json'] ?? null,
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function list(array $filters = []): array
    {
        $sql = "
            SELECT
                br.*,
                u.username AS triggered_by_username,
                u.full_name AS triggered_by_name
            FROM billing_runs br
            LEFT JOIN users u ON u.id = br.triggered_by
            WHERE 1 = 1
        ";

        $params = [];

        if (!empty($filters['run_type'])) {
            $sql .= " AND br.run_type = :run_type";
            $params[':run_type'] = strtoupper((string)$filters['run_type']);
        }

        if (!empty($filters['status'])) {
            $sql .= " AND br.status = :status";
            $params[':status'] = strtoupper((string)$filters['status']);
        }

        if (!empty($filters['as_of_date'])) {
            $sql .= " AND br.as_of_date = :as_of_date";
            $params[':as_of_date'] = $filters['as_of_date'];
        }

        $sql .= " ORDER BY br.id DESC LIMIT :limit OFFSET :offset";

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
            FROM billing_runs br
            WHERE 1 = 1
        ";

        $params = [];

        if (!empty($filters['run_type'])) {
            $sql .= " AND br.run_type = :run_type";
            $params[':run_type'] = strtoupper((string)$filters['run_type']);
        }

        if (!empty($filters['status'])) {
            $sql .= " AND br.status = :status";
            $params[':status'] = strtoupper((string)$filters['status']);
        }

        if (!empty($filters['as_of_date'])) {
            $sql .= " AND br.as_of_date = :as_of_date";
            $params[':as_of_date'] = $filters['as_of_date'];
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                br.*,
                u.username AS triggered_by_username,
                u.full_name AS triggered_by_name
            FROM billing_runs br
            LEFT JOIN users u ON u.id = br.triggered_by
            WHERE br.id = :id
            LIMIT 1
        ");

        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function getItems(int $runId): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM billing_run_items
            WHERE billing_run_id = :billing_run_id
            ORDER BY id ASC
        ");

        $stmt->execute([':billing_run_id' => $runId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function generateRunNo(): string
    {
        return 'BR-' . date('Ymd-His') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    }
}