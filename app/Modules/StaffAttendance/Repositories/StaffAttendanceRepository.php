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

    public function transaction(callable $callback): mixed
    {
        if ($this->db->inTransaction()) return $callback();
        $this->db->beginTransaction();
        try { $result=$callback(); $this->db->commit(); return $result; }
        catch (\Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
    }

    public function acquireUserLock(int $userId): void
    {
        $stmt=$this->db->prepare('SELECT GET_LOCK(?,10)'); $stmt->execute(['nexusbox:attendance:'.$userId]);
        if ((int)$stmt->fetchColumn()!==1) throw new \RuntimeException('Attendance is busy. Please retry.');
    }

    public function releaseUserLock(int $userId): void
    {
        $stmt=$this->db->prepare('SELECT RELEASE_LOCK(?)'); $stmt->execute(['nexusbox:attendance:'.$userId]);
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

        $stmt->execute([
            ':user_id' => $userId,
            ':time_out_ip' => $ip,
            ':time_out_user_agent' => $userAgent,
        ]);
        return $stmt->rowCount() === 1;
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

        $stmt->execute([
            ':user_id' => $userId,
            ':status' => $status,
        ]);
        return $stmt->rowCount() === 1;
    }

    public function getTodayStaff(): array
    {
        $stmt = $this->db->query("
            SELECT
                sa.id, sa.attendance_date, sa.time_in_at, sa.time_out_at,
                COALESCE(CASE WHEN sa.time_out_at IS NOT NULL THEN 'OFFLINE' ELSE sa.status END, 'OFFLINE') AS status,
                u.username,
                u.full_name,
                u.email,
                u.role
            FROM users u
            LEFT JOIN staff_attendance sa ON sa.user_id = u.id AND sa.attendance_date = CURDATE()
            WHERE u.status = 'ACTIVE' AND u.role <> 'SUBSCRIBER'
            ORDER BY
                FIELD(COALESCE(sa.status, 'OFFLINE'), 'AVAILABLE', 'BUSY', 'TRAVELING', 'ON_SITE', 'ON_BREAK', 'OFFLINE'),
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
              AND u.role = 'TECHNICIAN'
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

    public function hasActiveFieldWork(int $userId): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM work_orders WHERE assigned_user_id=:user_id AND status IN ('IN_PROGRESS','ON_SITE')");
        $stmt->execute([':user_id'=>$userId]);
        return (int)$stmt->fetchColumn() > 0;
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

    public function getHistory(string $from, string $to, int $limit = 500): array
    {
        $stmt=$this->db->prepare("SELECT sa.id,sa.attendance_date,sa.time_in_at,sa.time_out_at,sa.status,sa.notes,u.username,u.full_name,u.email,u.role,TIMESTAMPDIFF(MINUTE,sa.time_in_at,COALESCE(sa.time_out_at,NOW())) AS duty_minutes FROM staff_attendance sa INNER JOIN users u ON u.id=sa.user_id WHERE sa.attendance_date BETWEEN :from_date AND :to_date ORDER BY sa.attendance_date DESC,sa.time_in_at DESC LIMIT :limit");
        $stmt->bindValue(':from_date',$from); $stmt->bindValue(':to_date',$to); $stmt->bindValue(':limit',max(1,min(2000,$limit)),PDO::PARAM_INT); $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

}
