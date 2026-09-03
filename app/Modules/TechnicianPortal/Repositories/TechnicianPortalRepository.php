<?php

namespace App\Modules\TechnicianPortal\Repositories;

use Framework\DatabaseConnection;
use PDO;
use RuntimeException;
use Throwable;

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
        $existing = $this->getTodayAttendance($userId);

        if ($existing && empty($existing['time_out_at'])) {
            return (int)$existing['id'];
        }

        $stmt = $this->db->prepare("
            INSERT INTO staff_attendance (
                user_id,
                attendance_date,
                time_in_at,
                status,
                time_in_ip,
                time_in_user_agent,
                time_in_latitude,
                time_in_longitude,
                notes
            ) VALUES (
                :user_id,
                CURDATE(),
                NOW(),
                :status,
                :time_in_ip,
                :time_in_user_agent,
                :time_in_latitude,
                :time_in_longitude,
                :notes
            )
        ");

        $stmt->execute([
            ':user_id' => $userId,
            ':status' => $data['status'] ?? 'AVAILABLE',
            ':time_in_ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':time_in_user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ':time_in_latitude' => $data['latitude'] ?? null,
            ':time_in_longitude' => $data['longitude'] ?? null,
            ':notes' => $data['notes'] ?? null,
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function updateTimeInLocation(int $userId, ?string $latitude, ?string $longitude, ?string $notes): void
    {
        $stmt=$this->db->prepare('UPDATE staff_attendance SET time_in_latitude=:lat,time_in_longitude=:lng,notes=COALESCE(:notes,notes),updated_at=NOW() WHERE user_id=:user_id AND attendance_date=CURDATE() LIMIT 1');
        $stmt->execute([':lat'=>$latitude,':lng'=>$longitude,':notes'=>$notes,':user_id'=>$userId]);
    }

    public function updateTimeOutLocation(int $userId, ?string $latitude, ?string $longitude): void
    {
        $stmt=$this->db->prepare('UPDATE staff_attendance SET time_out_latitude=:lat,time_out_longitude=:lng,updated_at=NOW() WHERE user_id=:user_id AND attendance_date=CURDATE() LIMIT 1');
        $stmt->execute([':lat'=>$latitude,':lng'=>$longitude,':user_id'=>$userId]);
    }

    public function timeOut(int $userId, array $data = []): bool
    {
        $stmt = $this->db->prepare("
            UPDATE staff_attendance
            SET
                time_out_at = NOW(),
                status = 'OFFLINE',
                time_out_ip = :time_out_ip,
                time_out_user_agent = :time_out_user_agent,
                time_out_latitude = :time_out_latitude,
                time_out_longitude = :time_out_longitude,
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
            ':time_out_ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':time_out_user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ':time_out_latitude' => $data['latitude'] ?? null,
            ':time_out_longitude' => $data['longitude'] ?? null,
        ]);
    }

    public function updateAttendanceStatus(int $userId, string $status): bool
    {
        $allowedStatuses = [
            'AVAILABLE',
            'BUSY',
            'ON_BREAK',
            'ON_SITE',
            'TRAVELING',
            'OFFLINE',
        ];

        $status = strtoupper(trim($status));

        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'AVAILABLE';
        }

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
            ':status' => $status,
            ':user_id' => $userId,
        ]);
    }

    public function getWorkOrderSummary(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                COALESCE(SUM(CASE WHEN status = 'ASSIGNED' THEN 1 ELSE 0 END), 0) AS assigned_count,
                COALESCE(SUM(CASE WHEN status IN ('IN_PROGRESS','ON_SITE') THEN 1 ELSE 0 END), 0) AS in_progress_count,
                COALESCE(SUM(CASE WHEN status = 'COMPLETED' AND DATE(completed_at) = CURDATE() THEN 1 ELSE 0 END), 0) AS completed_today_count,
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
            'completed_count' => (int)($row['completed_today_count'] ?? 0),
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
                    WHEN 'ON_SITE' THEN 1
                    WHEN 'IN_PROGRESS' THEN 2
                    WHEN 'ASSIGNED' THEN 3
                    WHEN 'OPEN' THEN 4
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

    public function getWorkOrderTasks(int $workOrderId): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM work_order_tasks
            WHERE work_order_id = :work_order_id
            ORDER BY is_required DESC, id ASC
        ");
        $stmt->execute([':work_order_id' => $workOrderId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findAssignedTask(int $taskId, int $userId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT wot.*
            FROM work_order_tasks wot
            INNER JOIN work_orders wo ON wo.id = wot.work_order_id
            WHERE wot.id = :task_id
              AND wo.assigned_user_id = :user_id
            LIMIT 1
        ");
        $stmt->execute([':task_id' => $taskId, ':user_id' => $userId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function completeTask(int $taskId, int $userId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE work_order_tasks
            SET is_completed = 1,
                completed_at = COALESCE(completed_at, NOW()),
                completed_by_user_id = :user_id
            WHERE id = :task_id
            LIMIT 1
        ");

        return $stmt->execute([':task_id' => $taskId, ':user_id' => $userId]);
    }

    public function allRequiredTasksCompleted(int $workOrderId): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM work_order_tasks
            WHERE work_order_id = :work_order_id
              AND is_required = 1
              AND is_completed = 0
        ");
        $stmt->execute([':work_order_id' => $workOrderId]);

        return (int)$stmt->fetchColumn() === 0;
    }

    public function checkInWorkOrder(int $workOrderId, ?string $latitude, ?string $longitude): bool
    {
        $stmt = $this->db->prepare("
            UPDATE work_orders
            SET
                check_in_at = COALESCE(check_in_at, NOW()),
                arrived_at = COALESCE(arrived_at, NOW()),
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

    public function hasOtherActiveWork(int $userId, int $excludeWorkOrderId): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM work_orders WHERE assigned_user_id=:user_id AND id<>:exclude_id AND status IN ('IN_PROGRESS','ON_SITE')");
        $stmt->execute([':user_id'=>$userId, ':exclude_id'=>$excludeWorkOrderId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function updateWorkOrderStatus(int $workOrderId, string $status): bool
    {
        $status = strtoupper(trim($status));

        $stmt = $this->db->prepare("
            UPDATE work_orders
            SET
                status = :status,
                started_at = CASE
                    WHEN :status_start = 'IN_PROGRESS' AND started_at IS NULL THEN NOW()
                    ELSE started_at
                END,
                updated_at = NOW()
            WHERE id = :work_order_id
            LIMIT 1
        ");

        return $stmt->execute([
            ':status' => $status,
            ':status_start' => $status,
            ':work_order_id' => $workOrderId,
        ]);
    }

    public function completeWorkOrder(int $workOrderId, string $completionNotes, int $userId): array
    {
        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                SELECT id, ticket_id, status, work_order_no
                FROM work_orders
                WHERE id = :work_order_id
                LIMIT 1
                FOR UPDATE
            ");
            $stmt->execute([':work_order_id' => $workOrderId]);
            $workOrder = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$workOrder) {
                throw new RuntimeException('Work order not found.');
            }

            if (!$this->allRequiredTasksCompleted($workOrderId)) {
                throw new RuntimeException('Complete all required tasks before completing the work order.');
            }

            $stmt = $this->db->prepare("
                UPDATE work_orders
                SET status = 'COMPLETED',
                    completion_notes = :completion_notes,
                    completed_at = COALESCE(completed_at, NOW()),
                    updated_at = NOW()
                WHERE id = :work_order_id
                LIMIT 1
            ");
            $stmt->execute([
                ':completion_notes' => $completionNotes,
                ':work_order_id' => $workOrderId,
            ]);

            $ticketId = (int)($workOrder['ticket_id'] ?? 0);
            $ticketResolved = false;

            if ($ticketId > 0) {
                $stmt = $this->db->prepare('SELECT status FROM tickets WHERE id = :ticket_id LIMIT 1 FOR UPDATE');
                $stmt->execute([':ticket_id' => $ticketId]);
                $oldTicketStatus = strtoupper((string)$stmt->fetchColumn());

                if ($oldTicketStatus !== '' && !in_array($oldTicketStatus, ['RESOLVED', 'CLOSED'], true)) {
                    $stmt = $this->db->prepare("
                        UPDATE tickets
                        SET status = 'RESOLVED',
                            resolved_at = COALESCE(resolved_at, NOW()),
                            updated_at = NOW()
                        WHERE id = :ticket_id
                        LIMIT 1
                    ");
                    $stmt->execute([':ticket_id' => $ticketId]);

                    $stmt = $this->db->prepare("
                        INSERT INTO ticket_status_logs
                            (ticket_id, old_status, new_status, changed_by_user_id, note)
                        VALUES
                            (:ticket_id, :old_status, 'RESOLVED', :user_id, :note)
                    ");
                    $stmt->execute([
                        ':ticket_id' => $ticketId,
                        ':old_status' => $oldTicketStatus,
                        ':user_id' => $userId > 0 ? $userId : null,
                        ':note' => 'Automatically resolved when linked work order was completed.',
                    ]);
                    $ticketResolved = true;
                }
            }

            $this->db->commit();

            return [
                'work_order_no' => (string)($workOrder['work_order_no'] ?? ''),
                'ticket_id' => $ticketId > 0 ? $ticketId : null,
                'ticket_resolved' => $ticketResolved,
            ];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
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

    public function getWorkOrderAttachments(int $workOrderId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                woa.*,
                u.full_name,
                u.username,
                u.role
            FROM work_order_attachments woa
            LEFT JOIN users u ON u.id = woa.uploaded_by_user_id
            WHERE woa.work_order_id = :work_order_id
            ORDER BY woa.id DESC
        ");

        $stmt->execute([
            ':work_order_id' => $workOrderId,
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$row) {
            $row['file_path'] = '/api/v1/technician-portal/attachments/' . (int)$row['id'] . '/download';
        }
        unset($row);
        return $rows;
    }

    public function createWorkOrderAttachment(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO work_order_attachments (
                work_order_id,
                uploaded_by_user_id,
                file_name,
                file_path,
                file_type,
                file_size,
                attachment_type,
                remarks
            ) VALUES (
                :work_order_id,
                :uploaded_by_user_id,
                :file_name,
                :file_path,
                :file_type,
                :file_size,
                :attachment_type,
                :remarks
            )
        ");

        $stmt->execute([
            ':work_order_id' => $data['work_order_id'],
            ':uploaded_by_user_id' => $data['uploaded_by_user_id'] ?? null,
            ':file_name' => $data['file_name'],
            ':file_path' => $data['file_path'],
            ':file_type' => $data['file_type'] ?? null,
            ':file_size' => $data['file_size'] ?? null,
            ':attachment_type' => strtoupper((string)($data['attachment_type'] ?? 'OTHER')),
            ':remarks' => $data['remarks'] ?? null,
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function findWorkOrderAttachmentForTechnician(int $attachmentId, int $userId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                woa.*,
                wo.assigned_user_id,
                wo.work_order_no
            FROM work_order_attachments woa
            INNER JOIN work_orders wo ON wo.id = woa.work_order_id
            WHERE woa.id = :attachment_id
              AND wo.assigned_user_id = :user_id
            LIMIT 1
        ");

        $stmt->execute([
            ':attachment_id' => $attachmentId,
            ':user_id' => $userId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findWorkOrderAttachment(int $attachmentId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM work_order_attachments WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $attachmentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function deleteWorkOrderAttachment(int $attachmentId): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM work_order_attachments
            WHERE id = :attachment_id
            LIMIT 1
        ");

        return $stmt->execute([
            ':attachment_id' => $attachmentId,
        ]);
    }
}
