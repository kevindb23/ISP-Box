<?php

namespace App\Modules\TechnicianPortal\Repositories;

use Framework\DatabaseConnection;
use PDO;

class TechnicianPortalRepository
{
    private PDO $db;

    public function __construct(DatabaseConnection $connection)
    {
        $this->db = $connection->get();
    }

    public function getTodayAttendance(int $userId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM staff_attendance
            WHERE user_id = :user_id
              AND attendance_date = CURDATE()
            ORDER BY id DESC
            LIMIT 1
        ");

        $stmt->execute([
            ':user_id' => $userId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function timeIn(int $userId, array $data = []): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO staff_attendance (
                user_id,
                attendance_date,
                time_in_at,
                status,
                latitude,
                longitude
            ) VALUES (
                :user_id,
                CURDATE(),
                NOW(),
                :status,
                :latitude,
                :longitude
            )
        ");

        $stmt->execute([
            ':user_id' => $userId,
            ':status' => $data['status'] ?? 'AVAILABLE',
            ':latitude' => $data['latitude'] ?? null,
            ':longitude' => $data['longitude'] ?? null,
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function timeOut(int $userId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE staff_attendance
            SET
                time_out_at = NOW(),
                status = 'OFF_DUTY',
                updated_at = NOW()
            WHERE user_id = :user_id
              AND attendance_date = CURDATE()
              AND time_in_at IS NOT NULL
              AND time_out_at IS NULL
            ORDER BY id DESC
            LIMIT 1
        ");

        return $stmt->execute([
            ':user_id' => $userId,
        ]);
    }

    public function updateAttendanceStatus(int $userId, string $status): bool
    {
        $stmt = $this->db->prepare("
            UPDATE staff_attendance
            SET
                status = :status,
                updated_at = NOW()
            WHERE user_id = :user_id
              AND attendance_date = CURDATE()
              AND time_in_at IS NOT NULL
              AND time_out_at IS NULL
            ORDER BY id DESC
            LIMIT 1
        ");

        return $stmt->execute([
            ':status' => strtoupper($status),
            ':user_id' => $userId,
        ]);
    }

    public function getWorkOrderSummary(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                COALESCE(SUM(CASE WHEN status = 'ASSIGNED' THEN 1 ELSE 0 END), 0) AS assigned_count,
                COALESCE(SUM(CASE WHEN status = 'IN_PROGRESS' THEN 1 ELSE 0 END), 0) AS in_progress_count,
                COALESCE(SUM(CASE WHEN status = 'COMPLETED' THEN 1 ELSE 0 END), 0) AS completed_count,
                COALESCE(SUM(CASE WHEN status IN ('FAILED','CANCELLED') THEN 1 ELSE 0 END), 0) AS issue_count,
                COUNT(*) AS total_count
            FROM work_orders
            WHERE assigned_user_id = :user_id
        ");

        $stmt->execute([
            ':user_id' => $userId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'assigned_count' => (int)($row['assigned_count'] ?? 0),
            'in_progress_count' => (int)($row['in_progress_count'] ?? 0),
            'completed_count' => (int)($row['completed_count'] ?? 0),
            'issue_count' => (int)($row['issue_count'] ?? 0),
            'total_count' => (int)($row['total_count'] ?? 0),
        ];
    }

    public function getAssignedWorkOrders(int $userId, array $filters = []): array
    {
        $sql = "
            SELECT
                wo.*,
                t.ticket_no,
                t.subject AS ticket_subject,
                t.category AS ticket_category,
                s.account_number,
                s.full_name AS subscriber_name,
                s.contact_number,
                s.email,
                s.address,
                ss.service_number,
                ss.ppp_username
            FROM work_orders wo
            LEFT JOIN tickets t ON t.id = wo.ticket_id
            LEFT JOIN subscribers s ON s.id = wo.subscriber_id
            LEFT JOIN subscriber_services ss ON ss.id = wo.service_id
            WHERE wo.assigned_user_id = :user_id
        ";

        $params = [
            ':user_id' => $userId,
        ];

        if (!empty($filters['status'])) {
            $sql .= " AND wo.status = :status";
            $params[':status'] = strtoupper((string)$filters['status']);
        }

        $sql .= "
            ORDER BY
                CASE wo.status
                    WHEN 'IN_PROGRESS' THEN 1
                    WHEN 'ASSIGNED' THEN 2
                    WHEN 'PENDING' THEN 3
                    WHEN 'COMPLETED' THEN 8
                    ELSE 9
                END,
                wo.scheduled_date ASC,
                wo.scheduled_time ASC,
                wo.id DESC
            LIMIT :limit OFFSET :offset
        ";

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

    public function countAssignedWorkOrders(int $userId, array $filters = []): int
    {
        $sql = "
            SELECT COUNT(*)
            FROM work_orders wo
            WHERE wo.assigned_user_id = :user_id
        ";

        $params = [
            ':user_id' => $userId,
        ];

        if (!empty($filters['status'])) {
            $sql .= " AND wo.status = :status";
            $params[':status'] = strtoupper((string)$filters['status']);
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn();
    }

    public function findAssignedWorkOrder(int $workOrderId, int $userId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                wo.*,
                t.ticket_no,
                t.subject AS ticket_subject,
                t.description AS ticket_description,
                t.category AS ticket_category,
                t.priority AS ticket_priority,
                s.account_number,
                s.full_name AS subscriber_name,
                s.contact_number,
                s.email,
                s.address,
                ss.service_number,
                ss.ppp_username,
                ss.status AS service_status
            FROM work_orders wo
            LEFT JOIN tickets t ON t.id = wo.ticket_id
            LEFT JOIN subscribers s ON s.id = wo.subscriber_id
            LEFT JOIN subscriber_services ss ON ss.id = wo.service_id
            WHERE wo.id = :work_order_id
              AND wo.assigned_user_id = :user_id
            LIMIT 1
        ");

        $stmt->execute([
            ':work_order_id' => $workOrderId,
            ':user_id' => $userId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function checkInWorkOrder(int $workOrderId, ?string $latitude, ?string $longitude): bool
    {
        $stmt = $this->db->prepare("
            UPDATE work_orders
            SET
                check_in_at = COALESCE(check_in_at, NOW()),
                check_in_latitude = :latitude,
                check_in_longitude = :longitude,
                updated_at = NOW()
            WHERE id = :work_order_id
            LIMIT 1
        ");

        return $stmt->execute([
            ':latitude' => $latitude,
            ':longitude' => $longitude,
            ':work_order_id' => $workOrderId,
        ]);
    }

    public function updateWorkOrderStatus(int $workOrderId, string $status): bool
    {
        $stmt = $this->db->prepare("
            UPDATE work_orders
            SET
                status = :status,
                started_at = CASE WHEN :status_start = 'IN_PROGRESS' AND started_at IS NULL THEN NOW() ELSE started_at END,
                updated_at = NOW()
            WHERE id = :work_order_id
            LIMIT 1
        ");

        return $stmt->execute([
            ':status' => strtoupper($status),
            ':status_start' => strtoupper($status),
            ':work_order_id' => $workOrderId,
        ]);
    }

    public function completeWorkOrder(int $workOrderId, string $completionNotes): bool
    {
        $stmt = $this->db->prepare("
            UPDATE work_orders
            SET
                status = 'COMPLETED',
                completion_notes = :completion_notes,
                completed_at = NOW(),
                updated_at = NOW()
            WHERE id = :work_order_id
            LIMIT 1
        ");

        return $stmt->execute([
            ':completion_notes' => $completionNotes,
            ':work_order_id' => $workOrderId,
        ]);
    }

    public function getWorkOrderNotes(int $workOrderId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                won.*,
                u.full_name,
                u.username,
                u.role
            FROM work_order_notes won
            LEFT JOIN users u ON u.id = won.user_id
            WHERE won.work_order_id = :work_order_id
            ORDER BY won.id ASC
        ");

        $stmt->execute([
            ':work_order_id' => $workOrderId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function createWorkOrderNote(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO work_order_notes (
                work_order_id,
                user_id,
                note,
                note_type
            ) VALUES (
                :work_order_id,
                :user_id,
                :note,
                :note_type
            )
        ");

        $stmt->execute([
            ':work_order_id' => $data['work_order_id'],
            ':user_id' => $data['user_id'],
            ':note' => $data['note'],
            ':note_type' => $data['note_type'] ?? 'NOTE',
        ]);

        return (int)$this->db->lastInsertId();
    }
}