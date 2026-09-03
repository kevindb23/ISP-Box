<?php

namespace App\Modules\Tickets\Repositories;

use Framework\DatabaseConnection;
use PDO;

class TicketsRepository
{
    private PDO $db;

    public function __construct(DatabaseConnection $connection)
    {
        $this->db = $connection->get();
    }

    public function getTickets(array $filters = []): array
    {
        $sql = "
            SELECT
                t.*,
                s.account_number,
                s.user_id AS subscriber_user_id,
                s.full_name AS subscriber_name,
                s.contact_number,
                s.email,
                ss.service_number,
                ss.ppp_username,
                au.full_name AS assigned_user_name,
                au.username AS assigned_username,
                au.role AS assigned_user_role,
                wo.id AS work_order_id,
                wo.work_order_no,
                wo.status AS work_order_status,
                wo.scheduled_date,
                wo.scheduled_time
            FROM tickets t
            INNER JOIN subscribers s ON s.id = t.subscriber_id
            LEFT JOIN subscriber_services ss ON ss.id = t.service_id
            LEFT JOIN users au ON au.id = t.assigned_user_id
            LEFT JOIN work_orders wo ON wo.ticket_id = t.id
            WHERE 1 = 1
        ";

        $params = [];

        if (!empty($filters['status'])) {
            $sql .= " AND t.status = :status";
            $params[':status'] = strtoupper((string)$filters['status']);
        }

        if (!empty($filters['queue_role'])) {
            $sql .= " AND (au.role = :queue_assigned_role OR (t.assigned_user_id IS NULL AND " . $this->queueCategorySql(':queue_category_role') . "))";
            $params[':queue_assigned_role'] = strtoupper((string)$filters['queue_role']);
            $params[':queue_category_role'] = strtoupper((string)$filters['queue_role']);
        }

        if (!empty($filters['search'])) {
            $sql .= "
                AND (
                    t.ticket_no LIKE :search
                    OR t.subject LIKE :search
                    OR t.category LIKE :search
                    OR t.status LIKE :search
                    OR s.full_name LIKE :search
                    OR s.account_number LIKE :search
                    OR s.contact_number LIKE :search
                    OR s.email LIKE :search
                    OR ss.service_number LIKE :search
                    OR ss.ppp_username LIKE :search
                    OR au.full_name LIKE :search
                    OR au.username LIKE :search
                    OR au.role LIKE :search
                    OR wo.work_order_no LIKE :search
                )
            ";

            $params[':search'] = '%' . trim((string)$filters['search']) . '%';
        }

        $sql .= " GROUP BY t.id ORDER BY t.id DESC LIMIT :limit OFFSET :offset";

        $limit = isset($filters['limit']) ? max(1, min(200, (int)$filters['limit'])) : 100;
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

    public function countTickets(array $filters = []): int
    {
        $sql = "
            SELECT COUNT(DISTINCT t.id)
            FROM tickets t
            INNER JOIN subscribers s ON s.id = t.subscriber_id
            LEFT JOIN subscriber_services ss ON ss.id = t.service_id
            LEFT JOIN users au ON au.id = t.assigned_user_id
            LEFT JOIN work_orders wo ON wo.ticket_id = t.id
            WHERE 1 = 1
        ";

        $params = [];

        if (!empty($filters['status'])) {
            $sql .= " AND t.status = :status";
            $params[':status'] = strtoupper((string)$filters['status']);
        }

        if (!empty($filters['queue_role'])) {
            $sql .= " AND (au.role = :queue_assigned_role OR (t.assigned_user_id IS NULL AND " . $this->queueCategorySql(':queue_category_role') . "))";
            $params[':queue_assigned_role'] = strtoupper((string)$filters['queue_role']);
            $params[':queue_category_role'] = strtoupper((string)$filters['queue_role']);
        }

        if (!empty($filters['search'])) {
            $sql .= "
                AND (
                    t.ticket_no LIKE :search
                    OR t.subject LIKE :search
                    OR t.category LIKE :search
                    OR t.status LIKE :search
                    OR s.full_name LIKE :search
                    OR s.account_number LIKE :search
                    OR s.contact_number LIKE :search
                    OR s.email LIKE :search
                    OR ss.service_number LIKE :search
                    OR ss.ppp_username LIKE :search
                    OR au.full_name LIKE :search
                    OR au.username LIKE :search
                    OR au.role LIKE :search
                    OR wo.work_order_no LIKE :search
                )
            ";

            $params[':search'] = '%' . trim((string)$filters['search']) . '%';
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn();
    }

    public function getSummary(array $filters = []): array
    {
        $sql = "
            SELECT
                COALESCE(SUM(CASE WHEN t.status = 'OPEN' THEN 1 ELSE 0 END), 0) AS open_count,
                COALESCE(SUM(CASE WHEN t.status = 'IN_PROGRESS' THEN 1 ELSE 0 END), 0) AS in_progress_count,
                COALESCE(SUM(CASE WHEN t.status IN (
                    'WAITING_CUSTOMER',
                    'WAITING_TECHNICIAN',
                    'WAITING_CUSTOMER_SCHEDULE',
                    'VISIT_SCHEDULED'
                ) THEN 1 ELSE 0 END), 0) AS waiting_count,
                COALESCE(SUM(CASE WHEN t.status = 'RESOLVED' THEN 1 ELSE 0 END), 0) AS resolved_count,
                COUNT(*) AS total_count
            FROM tickets t
            LEFT JOIN users au ON au.id = t.assigned_user_id
            WHERE 1 = 1
        ";

        $params = [];

        if (!empty($filters['queue_role'])) {
            $sql .= " AND (au.role = :queue_assigned_role OR (t.assigned_user_id IS NULL AND " . $this->queueCategorySql(':queue_category_role') . "))";
            $params[':queue_assigned_role'] = strtoupper((string)$filters['queue_role']);
            $params[':queue_category_role'] = strtoupper((string)$filters['queue_role']);
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'open_count' => (int)($row['open_count'] ?? 0),
            'in_progress_count' => (int)($row['in_progress_count'] ?? 0),
            'waiting_count' => (int)($row['waiting_count'] ?? 0),
            'resolved_count' => (int)($row['resolved_count'] ?? 0),
            'total_count' => (int)($row['total_count'] ?? 0),
        ];
    }

    public function findTicket(int $ticketId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                t.*,
                s.account_number,
                s.full_name AS subscriber_name,
                s.contact_number,
                s.email,
                s.address,
                ss.service_number,
                ss.ppp_username,
                ss.status AS service_status,
                au.full_name AS assigned_user_name,
                au.username AS assigned_username,
                au.role AS assigned_user_role,
                wo.id AS work_order_id,
                wo.work_order_no,
                wo.status AS work_order_status,
                wo.scheduled_date,
                wo.scheduled_time
            FROM tickets t
            INNER JOIN subscribers s ON s.id = t.subscriber_id
            LEFT JOIN subscriber_services ss ON ss.id = t.service_id
            LEFT JOIN users au ON au.id = t.assigned_user_id
            LEFT JOIN work_orders wo ON wo.ticket_id = t.id
            WHERE t.id = :ticket_id
            ORDER BY wo.id DESC
            LIMIT 1
        ");

        $stmt->execute([
            ':ticket_id' => $ticketId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function getTicketMessages(int $ticketId, bool $includeInternal = true): array
    {
        $sql = "
            SELECT
                tm.id,
                tm.ticket_id,
                tm.sender_type,
                tm.sender_user_id,
                tm.message,
                tm.is_internal,
                tm.created_at,
                u.full_name,
                u.username,
                u.role
            FROM ticket_messages tm
            LEFT JOIN users u ON u.id = tm.sender_user_id
            WHERE tm.ticket_id = :ticket_id
        ";

        if (!$includeInternal) {
            $sql .= " AND tm.is_internal = 0";
        }

        $sql .= " ORDER BY tm.id ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':ticket_id' => $ticketId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function createTicketMessage(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO ticket_messages (
                ticket_id,
                sender_type,
                sender_user_id,
                message,
                is_internal
            ) VALUES (
                :ticket_id,
                :sender_type,
                :sender_user_id,
                :message,
                :is_internal
            )
        ");

        $stmt->execute([
            ':ticket_id' => $data['ticket_id'],
            ':sender_type' => $data['sender_type'],
            ':sender_user_id' => $data['sender_user_id'],
            ':message' => $data['message'],
            ':is_internal' => $data['is_internal'],
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function updateTicketStatus(int $ticketId, string $status): bool
    {
        $status = strtoupper($status);

        $stmt = $this->db->prepare("
            UPDATE tickets
            SET
                status = :status,
                resolved_at = CASE
                    WHEN :status_resolved = 'RESOLVED' THEN COALESCE(resolved_at, NOW())
                    WHEN :status_resolved NOT IN ('RESOLVED', 'CLOSED') THEN NULL
                    ELSE resolved_at
                END,
                closed_at = CASE WHEN :status_closed = 'CLOSED' THEN COALESCE(closed_at, NOW()) ELSE NULL END,
                updated_at = NOW()
            WHERE id = :ticket_id
            LIMIT 1
        ");

        return $stmt->execute([
            ':status' => $status,
            ':status_resolved' => $status,
            ':status_closed' => $status,
            ':ticket_id' => $ticketId,
        ]);
    }

    public function updateTicketPriority(int $ticketId, string $priority): bool
    {
        $stmt = $this->db->prepare("
            UPDATE tickets
            SET
                priority = :priority,
                updated_at = NOW()
            WHERE id = :ticket_id
            LIMIT 1
        ");

        return $stmt->execute([
            ':priority' => strtoupper($priority),
            ':ticket_id' => $ticketId,
        ]);
    }

    public function assignTicket(int $ticketId, ?int $assignedUserId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE tickets
            SET
                assigned_user_id = :assigned_user_id,
                updated_at = NOW()
            WHERE id = :ticket_id
            LIMIT 1
        ");

        return $stmt->execute([
            ':assigned_user_id' => $assignedUserId,
            ':ticket_id' => $ticketId,
        ]);
    }

    public function updateVisitSchedule(
        int $ticketId,
        ?string $preferredVisitDate,
        ?string $preferredVisitTime,
        ?string $preferredVisitNotes = null
    ): bool {
        $stmt = $this->db->prepare("
            UPDATE tickets
            SET
                preferred_visit_date = :preferred_visit_date,
                preferred_visit_time = :preferred_visit_time,
                preferred_visit_notes = :preferred_visit_notes,
                status = 'VISIT_SCHEDULED',
                updated_at = NOW()
            WHERE id = :ticket_id
            LIMIT 1
        ");

        return $stmt->execute([
            ':preferred_visit_date' => $preferredVisitDate,
            ':preferred_visit_time' => $preferredVisitTime,
            ':preferred_visit_notes' => $preferredVisitNotes,
            ':ticket_id' => $ticketId,
        ]);
    }

    public function createStatusLog(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO ticket_status_logs (
                ticket_id,
                old_status,
                new_status,
                changed_by_user_id,
                note
            ) VALUES (
                :ticket_id,
                :old_status,
                :new_status,
                :changed_by_user_id,
                :note
            )
        ");

        $stmt->execute([
            ':ticket_id' => $data['ticket_id'],
            ':old_status' => $data['old_status'],
            ':new_status' => $data['new_status'],
            ':changed_by_user_id' => $data['changed_by_user_id'],
            ':note' => $data['note'],
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function getAssignableUsers(): array
    {
        $stmt = $this->db->query("
            SELECT
                id,
                username,
                full_name,
                email,
                role,
                status
            FROM users
            WHERE status = 'ACTIVE'
              AND role IN ('NOC', 'SUPPORT', 'BILLING')
            ORDER BY
                CASE role
                    WHEN 'NOC' THEN 1
                    WHEN 'SUPPORT' THEN 2
                    WHEN 'BILLING' THEN 3
                    ELSE 9
                END,
                full_name ASC,
                username ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findAssignableUser(int $userId): ?array
    {
        $stmt = $this->db->prepare("SELECT id, username, full_name, role, status FROM users WHERE id = :id AND status = 'ACTIVE' AND role IN ('NOC','SUPPORT','BILLING') LIMIT 1");
        $stmt->execute([':id' => $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function queueCategorySql(string $rolePlaceholder): string
    {
        return "CASE t.category WHEN 'INTERNET' THEN 'NOC' WHEN 'BILLING' THEN 'BILLING' ELSE 'SUPPORT' END = {$rolePlaceholder}";
    }

    public function findBestAvailableAssignee(?string $category = null): ?array
    {
        $category = strtoupper((string)($category ?? ''));

        $preferredRoles = match ($category) {
            'INTERNET' => ['NOC'],
            'BILLING' => ['BILLING'],
            'ACCOUNT', 'OTHERS' => ['SUPPORT'],
            default => ['SUPPORT'],
        };

        $rolePlaceholders = [];
        $params = [];

        foreach ($preferredRoles as $index => $role) {
            $placeholder = ':role_' . $index;
            $rolePlaceholders[] = $placeholder;
            $params[$placeholder] = $role;
        }

        $roleInSql = implode(',', $rolePlaceholders);

        $stmt = $this->db->prepare("
            SELECT
                u.id,
                u.username,
                u.full_name,
                u.email,
                u.role,
                sa.status,
                sa.time_in_at,
                COUNT(t.id) AS active_ticket_count
            FROM staff_attendance sa
            INNER JOIN users u ON u.id = sa.user_id
            LEFT JOIN tickets t
                ON t.assigned_user_id = u.id
               AND t.status IN (
                    'OPEN',
                    'IN_PROGRESS',
                    'WAITING_CUSTOMER',
                    'WAITING_TECHNICIAN',
                    'WAITING_CUSTOMER_SCHEDULE',
                    'VISIT_SCHEDULED'
               )
            WHERE sa.attendance_date = CURDATE()
              AND sa.time_in_at IS NOT NULL
              AND sa.time_out_at IS NULL
              AND sa.status = 'AVAILABLE'
              AND u.status = 'ACTIVE'
              AND u.role IN ({$roleInSql})
            GROUP BY
                u.id,
                u.username,
                u.full_name,
                u.email,
                u.role,
                sa.status,
                sa.time_in_at
            ORDER BY
                active_ticket_count ASC,
                sa.time_in_at ASC,
                u.id ASC
            LIMIT 1
        ");

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}
