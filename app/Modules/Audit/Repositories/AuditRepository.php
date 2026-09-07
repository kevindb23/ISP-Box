<?php

namespace App\Modules\Audit\Repositories;

use App\Infrastructure\Database\DatabaseConnection;
use PDO;

class AuditRepository
{
    private PDO $db;
    private ?bool $extendedSchema = null;

    public function __construct(DatabaseConnection $connection)
    {
        $this->db = $connection->get();
    }

    public function latest(array $filters = []): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['module'])) {
            $where[] = 'module = :module';
            $params['module'] = $filters['module'];
        }

        if (!empty($filters['action'])) {
            $where[] = 'action = :action';
            $params['action'] = $filters['action'];
        }

        if (!empty($filters['username'])) {
            $where[] = '(username LIKE :search
                OR description LIKE :search
                OR ip_address LIKE :search
                OR module LIKE :search
                OR action LIKE :search
                OR object_type LIKE :search
                OR CAST(object_id AS CHAR) LIKE :search)';
            $params['search'] = '%' . $filters['username'] . '%';
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'created_at >= :date_from';
            $params['date_from'] = $filters['date_from'] . ' 00:00:00';
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'created_at <= :date_to';
            $params['date_to'] = $filters['date_to'] . ' 23:59:59';
        }

        $sql = 'SELECT * FROM audit_logs';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY created_at DESC, id DESC LIMIT :limit OFFSET :offset';

        $stmt = $this->db->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }

        $stmt->bindValue(':limit', max(1, min(500, (int)($filters['limit'] ?? 500))), PDO::PARAM_INT);
        $stmt->bindValue(':offset', max(0, (int)($filters['offset'] ?? 0)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function latestSecurityNotifications(int $limit = 30): array
    {
        return $this->latest([
            'module' => 'AUTH',
            'action' => 'LOGIN_SECURITY_ALERT',
            'limit' => $limit,
        ]);
    }

<<<<<<< HEAD
    public function securityNotificationsForUser(int $userId, int $limit = 30): array
    {
        $stmt = $this->db->prepare('SELECT a.*, CASE WHEN r.notification_id IS NULL THEN 0 ELSE 1 END AS is_read
            FROM audit_logs a
            LEFT JOIN notification_reads r ON r.notification_id = a.id AND r.user_id = :user_id
            WHERE a.module = \'AUTH\' AND a.action = \'LOGIN_SECURITY_ALERT\'
            ORDER BY a.created_at DESC, a.id DESC LIMIT :limit');
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', max(1, min(100, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function maintenanceNotificationsForUser(int $userId, int $limit = 30): array
    {
        $stmt = $this->db->prepare('SELECT n.*, CASE WHEN r.notification_id IS NULL THEN 0 ELSE 1 END AS is_read
            FROM (' . $this->maintenanceNotificationSourceSql() . ') n
            LEFT JOIN notification_reads r ON r.notification_id = n.id AND r.user_id = :user_id
            ORDER BY COALESCE(n.starts_at, n.created_at) ASC, n.id ASC
            LIMIT :limit');
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', max(1, min(100, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function unreadMaintenanceNotificationCount(int $userId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*)
            FROM (' . $this->maintenanceNotificationSourceSql() . ') n
            LEFT JOIN notification_reads r ON r.notification_id = n.id AND r.user_id = :user_id
            WHERE r.notification_id IS NULL');
        $stmt->execute(['user_id' => $userId]);

        return (int)$stmt->fetchColumn();
    }

    public function unreadSecurityNotificationCount(int $userId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM audit_logs a
            LEFT JOIN notification_reads r ON r.notification_id = a.id AND r.user_id = :user_id
            WHERE a.module = \'AUTH\' AND a.action = \'LOGIN_SECURITY_ALERT\' AND r.notification_id IS NULL');
        $stmt->execute(['user_id' => $userId]);
        return (int)$stmt->fetchColumn();
    }

    public function markNotificationRead(int $notificationId, int $userId): void
    {
        $stmt = $this->db->prepare('INSERT INTO notification_reads (notification_id, user_id)
            SELECT id, :user_id FROM audit_logs
            WHERE id = :notification_id AND module = \'AUTH\' AND action = \'LOGIN_SECURITY_ALERT\'
            ON DUPLICATE KEY UPDATE read_at = CURRENT_TIMESTAMP');
        $stmt->execute(['notification_id' => $notificationId, 'user_id' => $userId]);

        if ($stmt->rowCount() > 0) return;

        $maintenance = $this->db->prepare('SELECT 1
            FROM (' . $this->maintenanceNotificationSourceSql() . ') n
            WHERE n.id = :notification_id
            LIMIT 1');
        $maintenance->execute(['notification_id' => $notificationId]);
        if (!$maintenance->fetchColumn()) return;

        $read = $this->db->prepare('INSERT INTO notification_reads (notification_id, user_id)
            VALUES (:notification_id, :user_id)
            ON DUPLICATE KEY UPDATE read_at = CURRENT_TIMESTAMP');
        $read->execute(['notification_id' => $notificationId, 'user_id' => $userId]);
    }

    private function maintenanceNotificationSourceSql(): string
    {
        return "SELECT CAST(1000000000000000000 + id AS UNSIGNED) AS id,
                       'SCHEDULED_DOWNTIME' AS notification_type,
                       title,
                       message AS description,
                       starts_at,
                       ends_at,
                       updated_at AS created_at
                FROM scheduled_downtime
                WHERE enabled = 1 AND ends_at >= CURRENT_TIMESTAMP
                UNION ALL
                SELECT CAST(2000000000000000000 + id AS UNSIGNED) AS id,
                       'SYSTEM_MAINTENANCE' AS notification_type,
                       'System maintenance' AS title,
                       message AS description,
                       starts_at,
                       ends_at,
                       updated_at AS created_at
                FROM system_maintenance
                WHERE id = 1 AND enabled = 1
                  AND (ends_at IS NULL OR ends_at >= CURRENT_TIMESTAMP)";
    }

=======
>>>>>>> origin/main
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM audit_logs
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            'id' => $id
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function create(array $data): void
    {
        if (!$this->hasExtendedSchema()) {
            $this->createLegacy($data);
            return;
        }

        $stmt = $this->db->prepare("
            INSERT INTO audit_logs
            (
                user_id,
                username,
                actor_role,
                module,
                action,
                description,
                ip_address,
                object_type,
                object_id,
                result,
                source,
                http_method,
                route,
                request_id,
                metadata_json,
                old_values_json,
                new_values_json
            )
            VALUES
            (
                :user_id,
                :username,
                :actor_role,
                :module,
                :action,
                :description,
                :ip_address,
                :object_type,
                :object_id,
                :result,
                :source,
                :http_method,
                :route,
                :request_id,
                :metadata_json,
                :old_values_json,
                :new_values_json
            )
        ");

        $stmt->execute([
            'user_id'     => $data['user_id'],
            'username'    => $data['username'],
            'actor_role'  => $data['actor_role'] ?? null,
            'module'      => $data['module'],
            'action'      => $data['action'],
            'description' => $data['description'],
            'ip_address'  => $data['ip_address'],
            'object_type' => $data['object_type'] ?? null,
            'object_id' => $data['object_id'] ?? null,
            'result' => $data['result'] ?? 'SUCCESS',
            'source' => $data['source'] ?? 'APPLICATION',
            'http_method' => $data['http_method'] ?? null,
            'route' => $data['route'] ?? null,
            'request_id' => $data['request_id'] ?? null,
            'metadata_json' => $data['metadata_json'] ?? null,
            'old_values_json' => $data['old_values_json'] ?? null,
            'new_values_json' => $data['new_values_json'] ?? null,
        ]);
    }

    private function createLegacy(array $data): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO audit_logs
                (user_id, username, module, action, description, ip_address, object_type, object_id)
            VALUES
                (:user_id, :username, :module, :action, :description, :ip_address, :object_type, :object_id)
        ");

        $stmt->execute([
            'user_id' => $data['user_id'],
            'username' => $data['username'],
            'module' => $data['module'],
            'action' => $data['action'],
            'description' => $data['description'],
            'ip_address' => $data['ip_address'],
            'object_type' => $data['object_type'] ?? null,
            'object_id' => $data['object_id'] ?? null,
        ]);
    }

    private function hasExtendedSchema(): bool
    {
        if ($this->extendedSchema !== null) {
            return $this->extendedSchema;
        }

        $stmt = $this->db->query("SHOW COLUMNS FROM audit_logs LIKE 'result'");
        return $this->extendedSchema = (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    }
}
