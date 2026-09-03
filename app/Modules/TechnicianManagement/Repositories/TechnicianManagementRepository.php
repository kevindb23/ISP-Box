<?php

namespace App\Modules\TechnicianManagement\Repositories;

use App\Infrastructure\Database\DatabaseConnection;
use PDO;

class TechnicianManagementRepository
{
    private PDO $db;

    public function __construct(DatabaseConnection $connection)
    {
        $this->db = $connection->get();
    }

    public function getTechnicians(array $filters = []): array
    {
        $sql = "
            SELECT
                u.id,
                u.username,
                u.full_name,
                u.email,
                u.status AS account_status,
                u.last_login,

                CASE
                    WHEN sa.id IS NULL OR sa.time_in_at IS NULL OR sa.time_out_at IS NOT NULL THEN 'OFFLINE'
                    WHEN COUNT(CASE WHEN wo.status = 'ON_SITE' THEN 1 END) > 0 THEN 'ON_SITE'
                    WHEN COUNT(CASE WHEN wo.status = 'IN_PROGRESS' THEN 1 END) > 0 THEN 'BUSY'
                    ELSE COALESCE(sa.status, 'OFFLINE')
                END AS availability_status,

                sa.time_in_at,
                sa.time_out_at,
                sa.notes,

                tp.employee_no,
                tp.mobile_number,
                tp.service_area,
                tp.skill_level,
                tp.vehicle,
                tp.vehicle_plate,

                COUNT(wo.id) AS total_work_orders,
                COUNT(CASE WHEN wo.status IN ('OPEN','ASSIGNED','IN_PROGRESS','ON_SITE') THEN 1 END) AS active_work_orders,
                COUNT(CASE WHEN wo.status = 'COMPLETED' THEN 1 END) AS completed_work_orders,
                COUNT(CASE WHEN wo.status = 'FAILED' THEN 1 END) AS failed_work_orders,
                COUNT(CASE WHEN wo.status = 'CANCELLED' THEN 1 END) AS cancelled_work_orders,

                ROUND(
                    CASE
                        WHEN COUNT(CASE WHEN wo.status IN ('COMPLETED','FAILED') THEN 1 END) = 0 THEN 0
                        ELSE COUNT(CASE WHEN wo.status = 'COMPLETED' THEN 1 END)
                        / COUNT(CASE WHEN wo.status IN ('COMPLETED','FAILED') THEN 1 END) * 100
                    END,
                2) AS success_rate,

                ROUND(
                    CASE
                        WHEN COUNT(wo.id) = 0 THEN 0
                        ELSE COUNT(CASE WHEN wo.status = 'COMPLETED' THEN 1 END) / COUNT(wo.id) * 100
                    END,
                2) AS completion_rate
            FROM users u
            LEFT JOIN staff_attendance sa
                ON sa.user_id = u.id
                AND sa.attendance_date = CURDATE()
            LEFT JOIN technician_profiles tp
                ON tp.user_id = u.id
            LEFT JOIN work_orders wo
                ON wo.assigned_user_id = u.id
            WHERE u.role = 'TECHNICIAN'
              AND u.status = 'ACTIVE'
        ";

        $params = [];

        if (!empty($filters['search'])) {
            $sql .= "
                AND (
                    u.username LIKE :search
                    OR u.full_name LIKE :search
                    OR u.email LIKE :search
                    OR tp.employee_no LIKE :search
                    OR tp.mobile_number LIKE :search
                    OR tp.service_area LIKE :search
                    OR tp.skill_level LIKE :search
                    OR tp.vehicle LIKE :search
                    OR tp.vehicle_plate LIKE :search
                )
            ";
            $params['search'] = '%' . $filters['search'] . '%';
        }

        $sql .= "
            GROUP BY
                u.id,
                u.username,
                u.full_name,
                u.email,
                u.status,
                u.last_login,
                sa.id,
                sa.status,
                sa.time_in_at,
                sa.time_out_at,
                sa.notes,
                tp.employee_no,
                tp.mobile_number,
                tp.service_area,
                tp.skill_level,
                tp.vehicle,
                tp.vehicle_plate
        ";

        if (!empty($filters['status'])) {
            $sql .= ' HAVING availability_status = :status';
            $params['status'] = strtoupper(trim((string)$filters['status']));
        }

        $sql .= ' ORDER BY u.full_name ASC, u.username ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findTechnician(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                u.id,
                u.username,
                u.full_name,
                u.email,
                u.status AS account_status,
                u.last_login,

                CASE
                    WHEN sa.id IS NULL OR sa.time_in_at IS NULL OR sa.time_out_at IS NOT NULL THEN 'OFFLINE'
                    WHEN COUNT(CASE WHEN wo.status = 'ON_SITE' THEN 1 END) > 0 THEN 'ON_SITE'
                    WHEN COUNT(CASE WHEN wo.status = 'IN_PROGRESS' THEN 1 END) > 0 THEN 'BUSY'
                    ELSE COALESCE(sa.status, 'OFFLINE')
                END AS availability_status,

                sa.time_in_at,
                sa.time_out_at,
                sa.notes,

                tp.employee_no,
                tp.mobile_number,
                tp.service_area,
                tp.skill_level,
                tp.vehicle,
                tp.vehicle_plate,
                tp.emergency_contact,
                tp.emergency_number,
                tp.notes AS profile_notes,

                COUNT(wo.id) AS total_work_orders,
                COUNT(CASE WHEN wo.status IN ('OPEN','ASSIGNED','IN_PROGRESS','ON_SITE') THEN 1 END) AS active_work_orders,
                COUNT(CASE WHEN wo.status = 'COMPLETED' THEN 1 END) AS completed_work_orders,
                COUNT(CASE WHEN wo.status = 'FAILED' THEN 1 END) AS failed_work_orders,
                COUNT(CASE WHEN wo.status = 'CANCELLED' THEN 1 END) AS cancelled_work_orders,

                ROUND(
                    CASE
                        WHEN COUNT(CASE WHEN wo.status IN ('COMPLETED','FAILED') THEN 1 END) = 0 THEN 0
                        ELSE
                            COUNT(CASE WHEN wo.status = 'COMPLETED' THEN 1 END)
                            / COUNT(CASE WHEN wo.status IN ('COMPLETED','FAILED') THEN 1 END) * 100
                    END,
                2) AS success_rate,

                ROUND(
                    CASE
                        WHEN COUNT(wo.id) = 0 THEN 0
                        ELSE COUNT(CASE WHEN wo.status = 'COMPLETED' THEN 1 END) / COUNT(wo.id) * 100
                    END,
                2) AS completion_rate
            FROM users u
            LEFT JOIN staff_attendance sa
                ON sa.user_id = u.id
                AND sa.attendance_date = CURDATE()
            LEFT JOIN technician_profiles tp
                ON tp.user_id = u.id
            LEFT JOIN work_orders wo
                ON wo.assigned_user_id = u.id
            WHERE u.id = :id
            AND u.role = 'TECHNICIAN'
            GROUP BY
                u.id,
                u.username,
                u.full_name,
                u.email,
                u.status,
                u.last_login,
                sa.id,
                sa.status,
                sa.time_in_at,
                sa.time_out_at,
                sa.notes,
                tp.employee_no,
                tp.mobile_number,
                tp.service_area,
                tp.skill_level,
                tp.vehicle,
                tp.vehicle_plate,
                tp.emergency_contact,
                tp.emergency_number,
                tp.notes
            LIMIT 1
        ");

        $stmt->execute([
            'id' => $id
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function getSummary(): array
    {
        $stmt = $this->db->query("
            SELECT
                COUNT(*) AS total,
                COUNT(CASE WHEN u.status = 'ACTIVE' THEN 1 END) AS active_accounts,
                COUNT(CASE WHEN effective.status = 'AVAILABLE' THEN 1 END) AS available,
                COUNT(CASE WHEN effective.status = 'BUSY' THEN 1 END) AS busy,
                COUNT(CASE WHEN effective.status = 'ON_SITE' THEN 1 END) AS on_site,
                COUNT(CASE WHEN effective.status = 'TRAVELING' THEN 1 END) AS traveling,
                COUNT(CASE WHEN effective.status = 'ON_BREAK' THEN 1 END) AS on_break,
                COUNT(CASE WHEN effective.status = 'OFFLINE' THEN 1 END) AS offline
            FROM users u
            LEFT JOIN (
                SELECT
                    u2.id AS user_id,
                    CASE
                        WHEN sa2.id IS NULL OR sa2.time_in_at IS NULL OR sa2.time_out_at IS NOT NULL THEN 'OFFLINE'
                        WHEN COUNT(CASE WHEN wo.status = 'ON_SITE' THEN 1 END) > 0 THEN 'ON_SITE'
                        WHEN COUNT(CASE WHEN wo.status = 'IN_PROGRESS' THEN 1 END) > 0 THEN 'BUSY'
                        ELSE COALESCE(sa2.status, 'OFFLINE')
                    END AS status
                FROM users u2
                LEFT JOIN staff_attendance sa2
                    ON sa2.user_id = u2.id
                    AND sa2.attendance_date = CURDATE()
                LEFT JOIN work_orders wo
                    ON wo.assigned_user_id = u2.id
                    AND wo.status IN ('ASSIGNED','IN_PROGRESS','ON_SITE')
                WHERE u2.role = 'TECHNICIAN'
                GROUP BY u2.id, sa2.id, sa2.status, sa2.time_in_at, sa2.time_out_at
            ) effective ON effective.user_id = u.id
            WHERE u.role = 'TECHNICIAN'
        ");

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function getWorkOrderSummary(): array
    {
        $stmt = $this->db->query("
            SELECT
                COUNT(CASE WHEN status = 'OPEN' THEN 1 END) AS open,
                COUNT(CASE WHEN status = 'ASSIGNED' THEN 1 END) AS assigned,
                COUNT(CASE WHEN status = 'IN_PROGRESS' THEN 1 END) AS in_progress,
                COUNT(CASE WHEN status = 'ON_SITE' THEN 1 END) AS on_site,
                COUNT(CASE WHEN status = 'COMPLETED' THEN 1 END) AS completed,
                COUNT(CASE WHEN status = 'FAILED' THEN 1 END) AS failed,
                COUNT(CASE WHEN status = 'CANCELLED' THEN 1 END) AS cancelled,

                COUNT(CASE WHEN status IN ('OPEN','ASSIGNED','IN_PROGRESS','ON_SITE') THEN 1 END) AS active_jobs,
                COUNT(CASE WHEN status = 'ON_SITE' THEN 1 END) AS on_site_jobs,

                COUNT(CASE WHEN status = 'COMPLETED' AND DATE(completed_at) = CURDATE() THEN 1 END) AS completed_today,
                COUNT(CASE WHEN status = 'FAILED' AND DATE(updated_at) = CURDATE() THEN 1 END) AS failed_today
            FROM work_orders
        ");

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function getTechnicianWorkOrders(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                wo.id,
                wo.work_order_no,
                wo.work_order_type,
                wo.type,
                wo.title,
                wo.description,
                wo.priority,
                wo.status,
                wo.scheduled_date,
                wo.scheduled_time,
                wo.location,
                wo.contact_name,
                wo.contact_number,
                wo.started_at,
                wo.arrived_at,
                wo.completed_at,
                wo.completion_notes,
                wo.failure_reason,
                wo.created_at,
                s.full_name AS subscriber_name,
                s.account_number,
                ss.service_number,
                ss.ppp_username
            FROM work_orders wo
            LEFT JOIN subscribers s ON s.id = wo.subscriber_id
            LEFT JOIN subscriber_services ss ON ss.id = wo.service_id
            WHERE wo.assigned_user_id = :user_id
            ORDER BY wo.id DESC
            LIMIT 100
        ");

        $stmt->execute([
            'user_id' => $userId
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAttendanceLogs(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                id,
                attendance_id,
                user_id,
                action,
                old_status,
                new_status,
                ip_address,
                note,
                created_at
            FROM staff_attendance_logs
            WHERE user_id = :user_id
            ORDER BY created_at DESC
            LIMIT 50
        ");

        $stmt->execute([
            'user_id' => $userId
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function upsertProfile(array $data): array
    {
        $stmt = $this->db->prepare("
            INSERT INTO technician_profiles
            (
                user_id,
                employee_no,
                mobile_number,
                service_area,
                skill_level,
                vehicle,
                vehicle_plate,
                emergency_contact,
                emergency_number,
                notes
            )
            VALUES
            (
                :user_id,
                :employee_no,
                :mobile_number,
                :service_area,
                :skill_level,
                :vehicle,
                :vehicle_plate,
                :emergency_contact,
                :emergency_number,
                :notes
            )
            ON DUPLICATE KEY UPDATE
                employee_no = VALUES(employee_no),
                mobile_number = VALUES(mobile_number),
                service_area = VALUES(service_area),
                skill_level = VALUES(skill_level),
                vehicle = VALUES(vehicle),
                vehicle_plate = VALUES(vehicle_plate),
                emergency_contact = VALUES(emergency_contact),
                emergency_number = VALUES(emergency_number),
                notes = VALUES(notes),
                updated_at = NOW()
        ");

        $stmt->execute([
            'user_id' => $data['user_id'],
            'employee_no' => $data['employee_no'] ?: null,
            'mobile_number' => $data['mobile_number'] ?: null,
            'service_area' => $data['service_area'] ?: null,
            'skill_level' => $data['skill_level'] ?: 'JUNIOR',
            'vehicle' => $data['vehicle'] ?: null,
            'vehicle_plate' => $data['vehicle_plate'] ?: null,
            'emergency_contact' => $data['emergency_contact'] ?: null,
            'emergency_number' => $data['emergency_number'] ?: null,
            'notes' => $data['notes'] ?: null,
        ]);

        return $this->findTechnician((int)$data['user_id']) ?: [];
    }

    public function getUnassignedWorkOrders(): array
    {
        $stmt = $this->db->query("
            SELECT
                wo.id,
                wo.work_order_no,
                wo.work_order_type,
                wo.title,
                wo.priority,
                wo.status,
                wo.scheduled_date,
                wo.scheduled_time,
                wo.location,
                wo.contact_name,
                wo.contact_number,
                s.full_name AS subscriber_name,
                s.account_number,
                ss.service_number,
                ss.ppp_username
            FROM work_orders wo
            LEFT JOIN subscribers s ON s.id = wo.subscriber_id
            LEFT JOIN subscriber_services ss ON ss.id = wo.service_id
            WHERE wo.assigned_user_id IS NULL
            AND wo.status = 'OPEN'
            ORDER BY
                CASE wo.priority
                    WHEN 'URGENT' THEN 1
                    WHEN 'HIGH' THEN 2
                    WHEN 'MEDIUM' THEN 3
                    WHEN 'LOW' THEN 4
                    ELSE 5
                END,
                wo.id DESC
            LIMIT 100
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAvailableTechnicians(): array
    {
        $stmt = $this->db->query("
            SELECT
                u.id,
                u.username,
                u.full_name,
                u.email,

                CASE
                    WHEN sa.id IS NULL OR sa.time_in_at IS NULL OR sa.time_out_at IS NOT NULL THEN 'OFFLINE'
                    WHEN COUNT(CASE WHEN wo.status = 'ON_SITE' THEN 1 END) > 0 THEN 'ON_SITE'
                    WHEN COUNT(CASE WHEN wo.status = 'IN_PROGRESS' THEN 1 END) > 0 THEN 'BUSY'
                    ELSE COALESCE(sa.status, 'OFFLINE')
                END AS availability_status,

                tp.service_area,
                tp.skill_level,
                COUNT(CASE WHEN wo.status IN ('OPEN','ASSIGNED','IN_PROGRESS','ON_SITE') THEN 1 END) AS active_work_orders
            FROM users u
            LEFT JOIN staff_attendance sa
                ON sa.user_id = u.id
                AND sa.attendance_date = CURDATE()
            LEFT JOIN technician_profiles tp
                ON tp.user_id = u.id
            LEFT JOIN work_orders wo
                ON wo.assigned_user_id = u.id
            WHERE u.role = 'TECHNICIAN'
            AND u.status = 'ACTIVE'
            GROUP BY
                u.id,
                u.username,
                u.full_name,
                u.email,
                sa.status,
                sa.id,
                sa.time_in_at,
                sa.time_out_at,
                tp.service_area,
                tp.skill_level
            HAVING availability_status = 'AVAILABLE'
            ORDER BY
                CASE
                    WHEN sa.id IS NULL OR sa.time_in_at IS NULL OR sa.time_out_at IS NOT NULL THEN 6
                    WHEN COUNT(CASE WHEN wo.status = 'ON_SITE' THEN 1 END) > 0 THEN 2
                    WHEN COUNT(CASE WHEN wo.status = 'IN_PROGRESS' THEN 1 END) > 0 THEN 4
                    WHEN COALESCE(sa.status, 'OFFLINE') = 'AVAILABLE' THEN 1
                    WHEN COALESCE(sa.status, 'OFFLINE') = 'TRAVELING' THEN 3
                    WHEN COALESCE(sa.status, 'OFFLINE') = 'ON_BREAK' THEN 5
                    ELSE 6
                END,
                active_work_orders ASC,
                u.full_name ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findWorkOrderAssignment(int $workOrderId): ?array
    {
        $stmt = $this->db->prepare('SELECT id, assigned_user_id, status FROM work_orders WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $workOrderId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function hasActiveFieldWork(int $userId): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM work_orders WHERE assigned_user_id = :user_id AND status IN ('IN_PROGRESS','ON_SITE')");
        $stmt->execute([':user_id' => $userId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function hasActiveAttendance(int $userId): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM staff_attendance WHERE user_id = :user_id AND attendance_date = CURDATE() AND time_in_at IS NOT NULL AND time_out_at IS NULL");
        $stmt->execute([':user_id' => $userId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function assignWorkOrderToTechnician(int $workOrderId, int $technicianId, int $changedBy = 0): array
    {
        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                SELECT id, status, assigned_user_id
                FROM work_orders
                WHERE id = :id
                LIMIT 1
                FOR UPDATE
            ");

            $stmt->execute([
                'id' => $workOrderId
            ]);

            $workOrder = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$workOrder) {
                throw new \Exception('Work order not found.');
            }

            if (!empty($workOrder['assigned_user_id'])) {
                throw new \Exception('Work order is already assigned.');
            }

            $oldStatus = $workOrder['status'];
            $newStatus = 'ASSIGNED';

            $update = $this->db->prepare("
                UPDATE work_orders
                SET
                    assigned_user_id = :technician_id,
                    status = :status,
                    updated_at = NOW()
                WHERE id = :id
            ");

            $update->execute([
                'technician_id' => $technicianId,
                'status' => $newStatus,
                'id' => $workOrderId
            ]);

            $log = $this->db->prepare("
                INSERT INTO work_order_status_logs
                (
                    work_order_id,
                    old_status,
                    new_status,
                    changed_by_user_id,
                    note
                )
                VALUES
                (
                    :work_order_id,
                    :old_status,
                    :new_status,
                    :changed_by_user_id,
                    :note
                )
            ");

            $log->execute([
                'work_order_id' => $workOrderId,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'changed_by_user_id' => $changedBy ?: null,
                'note' => 'Assigned to technician from Technician Management Dispatch Board.'
            ]);

            $this->db->commit();

            return [
                'work_order_id' => $workOrderId,
                'technician_id' => $technicianId,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
            ];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }

    public function updateWorkOrderStatus(int $workOrderId, string $status, int $changedBy = 0, string $note = ''): array
    {
        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                SELECT id, status, assigned_user_id
                FROM work_orders
                WHERE id = :id
                LIMIT 1
                FOR UPDATE
            ");

            $stmt->execute(['id' => $workOrderId]);
            $workOrder = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$workOrder) {
                throw new \Exception('Work order not found.');
            }

            if (empty($workOrder['assigned_user_id'])) {
                throw new \Exception('Work order must be assigned first.');
            }

            $oldStatus = strtoupper((string)$workOrder['status']);

            $updateFields = [
                'status = :status',
                'updated_at = NOW()',
            ];

            $params = [
                'status' => $status,
                'id' => $workOrderId,
            ];

            if ($status === 'IN_PROGRESS') {
                $updateFields[] = 'started_at = COALESCE(started_at, NOW())';
            }

            if ($status === 'ON_SITE') {
                $updateFields[] = 'arrived_at = COALESCE(arrived_at, NOW())';
            }

            if ($status === 'COMPLETED') {
                $updateFields[] = 'completed_at = COALESCE(completed_at, NOW())';
                $updateFields[] = 'completion_notes = :completion_notes';
                $params['completion_notes'] = $note ?: 'Completed from Technician Management.';
            }

            if ($status === 'FAILED') {
                $updateFields[] = 'failure_reason = :failure_reason';
                $params['failure_reason'] = $note ?: 'Marked failed from Technician Management.';
            }

            $update = $this->db->prepare("
                UPDATE work_orders
                SET " . implode(', ', $updateFields) . "
                WHERE id = :id
            ");

            $update->execute($params);

            $log = $this->db->prepare("
                INSERT INTO work_order_status_logs
                (
                    work_order_id,
                    old_status,
                    new_status,
                    changed_by_user_id,
                    note
                )
                VALUES
                (
                    :work_order_id,
                    :old_status,
                    :new_status,
                    :changed_by_user_id,
                    :note
                )
            ");

            $log->execute([
                'work_order_id' => $workOrderId,
                'old_status' => $oldStatus,
                'new_status' => $status,
                'changed_by_user_id' => $changedBy ?: null,
                'note' => $note ?: 'Status updated from Technician Management.',
            ]);

            $this->db->commit();

            return [
                'work_order_id' => $workOrderId,
                'technician_id' => (int)$workOrder['assigned_user_id'],
                'old_status' => $oldStatus,
                'new_status' => $status,
            ];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }

    public function updateTodayStatus(int $userId, string $status, string $note = ''): array
    {
        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                SELECT *
                FROM staff_attendance
                WHERE user_id = :user_id
                AND attendance_date = CURDATE()
                LIMIT 1
                FOR UPDATE
            ");

            $stmt->execute([
                'user_id' => $userId
            ]);

            $attendance = $stmt->fetch(PDO::FETCH_ASSOC);
            $oldStatus = $attendance['status'] ?? 'OFFLINE';

            if (!$attendance || empty($attendance['time_in_at']) || !empty($attendance['time_out_at'])) {
                throw new \Exception('Technician must be clocked in before availability can be changed.');
            } else {
                $attendanceId = (int)$attendance['id'];

                $update = $this->db->prepare("
                    UPDATE staff_attendance
                    SET
                        status = :status,
                        notes = :notes,
                        updated_at = NOW()
                    WHERE id = :id
                ");

                $update->execute([
                    'status' => $status,
                    'notes' => $note ?: $attendance['notes'],
                    'id' => $attendanceId,
                ]);
            }

            $log = $this->db->prepare("
                INSERT INTO staff_attendance_logs
                (
                    attendance_id,
                    user_id,
                    action,
                    old_status,
                    new_status,
                    ip_address,
                    user_agent,
                    note
                )
                VALUES
                (
                    :attendance_id,
                    :user_id,
                    'STATUS_CHANGE',
                    :old_status,
                    :new_status,
                    :ip,
                    :user_agent,
                    :note
                )
            ");

            $log->execute([
                'attendance_id' => $attendanceId,
                'user_id' => $userId,
                'old_status' => $oldStatus,
                'new_status' => $status,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'note' => $note ?: null,
            ]);

            $this->db->commit();

            return [
                'user_id' => $userId,
                'old_status' => $oldStatus,
                'new_status' => $status,
            ];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }
}
