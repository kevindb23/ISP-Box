<?php

namespace App\Modules\StaffAttendance\Repositories;

use Framework\DatabaseConnection;
use PDO;

class StaffAttendanceRepository
{
    private PDO $db;

    public function __construct(DatabaseConnection $connection)
    {
        $this->db = $connection->get();
    }

    public function findTodayAttendance(int $userId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM staff_attendance
            WHERE user_id = :user_id
              AND attendance_date = CURDATE()
            LIMIT 1
        ");

        $stmt->execute([
            ':user_id' => $userId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function timeIn(int $userId, string $ip, string $userAgent): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO staff_attendance (
                user_id,
                attendance_date,
                time_in_at,
                status,
                time_in_ip,
                time_in_user_agent
            ) VALUES (
                :user_id,
                CURDATE(),
                NOW(),
                'AVAILABLE',
                :time_in_ip,
                :time_in_user_agent
            )
            ON DUPLICATE KEY UPDATE
                time_in_at = IF(time_in_at IS NULL, NOW(), time_in_at),
                status = IF(time_out_at IS NULL, 'AVAILABLE', status),
                time_in_ip = IF(time_in_ip IS NULL, VALUES(time_in_ip), time_in_ip),
                time_in_user_agent = IF(time_in_user_agent IS NULL, VALUES(time_in_user_agent), time_in_user_agent),
                updated_at = NOW()
        ");

        $stmt->execute([
            ':user_id' => $userId,
            ':time_in_ip' => $ip,
            ':time_in_user_agent' => $userAgent,
        ]);

        $today = $this->findTodayAttendance($userId);

        return (int)($today['id'] ?? 0);
    }

    public function timeOut(int $userId, string $ip, string $userAgent): bool
    {
        $stmt = $this->db->prepare("
            UPDATE staff_attendance
            SET
                time_out_at = NOW(),
                status = 'OFFLINE',
                time_out_ip = :time_out_ip,
                time_out_user_agent = :time_out_user_agent,
                updated_at = NOW()
            WHERE user_id = :user_id
              AND attendance_date = CURDATE()
              AND time_in_at IS NOT NULL
              AND time_out_at IS NULL
            LIMIT 1
        ");

        return $stmt->execute([
            ':user_id' => $userId,
            ':time_out_ip' => $ip,
            ':time_out_user_agent' => $userAgent,
        ]);
    }

    public function updateStatus(int $userId, string $status): bool
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
            LIMIT 1
        ");

        return $stmt->execute([
            ':user_id' => $userId,
            ':status' => $status,
        ]);
    }

    public function getTodayStaff(): array
    {
        $stmt = $this->db->query("
            SELECT
                sa.*,
                u.username,
                u.full_name,
                u.email,
                u.role
            FROM staff_attendance sa
            INNER JOIN users u ON u.id = sa.user_id
            WHERE sa.attendance_date = CURDATE()
            ORDER BY
                FIELD(sa.status, 'AVAILABLE', 'BUSY', 'TRAVELING', 'ON_SITE', 'ON_BREAK', 'OFFLINE'),
                sa.time_in_at ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getAvailableTechnicians(): array
    {
        $stmt = $this->db->query("
            SELECT
                sa.user_id,
                sa.status,
                sa.time_in_at,
                u.username,
                u.full_name,
                u.email,
                u.role
            FROM staff_attendance sa
            INNER JOIN users u ON u.id = sa.user_id
            WHERE sa.attendance_date = CURDATE()
              AND sa.time_in_at IS NOT NULL
              AND sa.time_out_at IS NULL
              AND sa.status = 'AVAILABLE'
              AND u.role IN ('TECHNICIAN', 'NOC')
              AND u.status = 'ACTIVE'
            ORDER BY sa.time_in_at ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function createLog(array $data): int
    {
        $stmt = $this->db->prepare("
        INSERT INTO staff_attendance_logs (
            attendance_id,
            user_id,
            action,
            old_status,
            new_status,
            ip_address,
            user_agent,
            note
        ) VALUES (
            :attendance_id,
            :user_id,
            :action,
            :old_status,
            :new_status,
            :ip_address,
            :user_agent,
            :note
        )
    ");

        $stmt->execute([
            ':attendance_id' => $data['attendance_id'],
            ':user_id' => $data['user_id'],
            ':action' => $data['action'],
            ':old_status' => $data['old_status'] ?? null,
            ':new_status' => $data['new_status'] ?? null,
            ':ip_address' => $data['ip_address'] ?? null,
            ':user_agent' => $data['user_agent'] ?? null,
            ':note' => $data['note'] ?? null,
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function getTodayLogs(): array
    {
        $stmt = $this->db->query("
        SELECT
            sal.*,
            u.username,
            u.full_name,
            u.email,
            u.role
        FROM staff_attendance_logs sal
        INNER JOIN users u ON u.id = sal.user_id
        WHERE DATE(sal.created_at) = CURDATE()
        ORDER BY sal.created_at DESC, sal.id DESC
    ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getUserTodayLogs(int $userId): array
    {
        $stmt = $this->db->prepare("
        SELECT *
        FROM staff_attendance_logs
        WHERE user_id = :user_id
          AND DATE(created_at) = CURDATE()
        ORDER BY created_at DESC, id DESC
    ");

        $stmt->execute([
            ':user_id' => $userId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

}