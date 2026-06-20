<?php

namespace App\Modules\Billing\Repositories;

use PDO;

class BillingActivityLogRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO billing_activity_logs (
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
                performed_by_name,
                ip_address,
                user_agent
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
                :performed_by_name,
                :ip_address,
                :user_agent
            )
        ");

        $stmt->execute([
            ':entity_type' => strtoupper((string)($data['entity_type'] ?? 'BILLING')),
            ':entity_id' => (int)($data['entity_id'] ?? 0),
            ':action' => strtoupper((string)($data['action'] ?? 'UNKNOWN')),
            ':status' => strtoupper((string)($data['status'] ?? 'SUCCESS')),
            ':title' => (string)($data['title'] ?? 'Billing activity'),
            ':message' => $data['message'] ?? null,
            ':old_values' => $this->jsonOrNull($data['old_values'] ?? null),
            ':new_values' => $this->jsonOrNull($data['new_values'] ?? null),
            ':meta_json' => $this->jsonOrNull($data['meta_json'] ?? null),
            ':performed_by' => $data['performed_by'] ?? null,
            ':performed_by_name' => $data['performed_by_name'] ?? null,
            ':ip_address' => $data['ip_address'] ?? ($_SERVER['REMOTE_ADDR'] ?? null),
            ':user_agent' => $data['user_agent'] ?? substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function listForEntity(string $entityType, int $entityId, int $limit = 100): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM billing_activity_logs
            WHERE entity_type = :entity_type
              AND entity_id = :entity_id
            ORDER BY id DESC
            LIMIT :limit
        ");

        $stmt->bindValue(':entity_type', strtoupper($entityType));
        $stmt->bindValue(':entity_id', $entityId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', max(1, min(300, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listRecent(array $filters = []): array
    {
        $sql = "
            SELECT *
            FROM billing_activity_logs
            WHERE 1 = 1
        ";

        $params = [];

        if (!empty($filters['entity_type'])) {
            $sql .= " AND entity_type = :entity_type";
            $params[':entity_type'] = strtoupper((string)$filters['entity_type']);
        }

        if (!empty($filters['action'])) {
            $sql .= " AND action = :action";
            $params[':action'] = strtoupper((string)$filters['action']);
        }

        $sql .= " ORDER BY id DESC LIMIT :limit";

        $limit = isset($filters['limit']) ? (int)$filters['limit'] : 100;

        $stmt = $this->db->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue(':limit', max(1, min(300, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function jsonOrNull($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            json_decode($value, true);
            return json_last_error() === JSON_ERROR_NONE ? $value : json_encode($value);
        }

        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}