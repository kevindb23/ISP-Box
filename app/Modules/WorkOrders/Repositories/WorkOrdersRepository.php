<?php

namespace App\Modules\WorkOrders\Repositories;

use Framework\DatabaseConnection;
use PDO;
use Throwable;

class WorkOrdersRepository
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
        try {
            $result = $callback();
            $this->db->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function createWithTasks(array $data, array $statusLog, array $tasks): array
    {
        $lockName = 'nexusbox:work-order-number:' . date('Y');
        $stmt = $this->db->prepare('SELECT GET_LOCK(?, 15)');
        $stmt->execute([$lockName]);
        if ((int)$stmt->fetchColumn() !== 1) throw new \RuntimeException('Work order numbering is busy. Please retry.');
        try {
            return $this->transaction(function () use ($data, $statusLog, $tasks): array {
                $data['work_order_no'] = $this->generateWorkOrderNo();
                $id = $this->createWorkOrder($data);
                $statusLog['work_order_id'] = $id;
                $this->createStatusLog($statusLog);
                foreach ($tasks as $task) $this->createTask($id, (string)$task, true);
                return ['work_order_id' => $id, 'work_order_no' => $data['work_order_no']];
            });
        } finally {
            $stmt = $this->db->prepare('SELECT RELEASE_LOCK(?)');
            $stmt->execute([$lockName]);
        }
    }

    public function getWorkOrders(array $filters = []): array
    {
        $sql = "
            SELECT
                wo.*,
                COALESCE(s.account_number, '-') AS account_number,
                COALESCE(s.full_name, wo.contact_name, 'Unknown Subscriber') AS subscriber_name,
                COALESCE(s.contact_number, wo.contact_number, '-') AS subscriber_contact_number,
                COALESCE(s.email, '-') AS subscriber_email,
                ss.service_number,
                ss.ppp_username,
                t.ticket_no,
                au.full_name AS assigned_user_name,
                au.username AS assigned_username,
                cu.full_name AS created_by_name,
                cu.username AS created_by_username
            FROM work_orders wo
            LEFT JOIN subscribers s ON s.id = wo.subscriber_id
            LEFT JOIN subscriber_services ss ON ss.id = wo.service_id
            LEFT JOIN tickets t ON t.id = wo.ticket_id
            LEFT JOIN users au ON au.id = wo.assigned_user_id
            LEFT JOIN users cu ON cu.id = wo.created_by_user_id
            WHERE 1 = 1
        ";

        $params = [];

        if (!empty($filters['status'])) {
            $sql .= " AND wo.status = :status";
            $params[':status'] = strtoupper((string)$filters['status']);
        }

        if (!empty($filters['work_order_type'])) {
            $sql .= " AND wo.work_order_type = :work_order_type";
            $params[':work_order_type'] = strtoupper((string)$filters['work_order_type']);
        }

        if (!empty($filters['assigned_user_id'])) {
            $sql .= " AND wo.assigned_user_id = :assigned_user_id";
            $params[':assigned_user_id'] = (int)$filters['assigned_user_id'];
        }

        if (!empty($filters['search'])) {
            $sql .= "
                AND (
                    wo.work_order_no LIKE :search
                    OR wo.title LIKE :search
                    OR wo.description LIKE :search
                    OR wo.work_order_type LIKE :search
                    OR wo.status LIKE :search
                    OR wo.contact_name LIKE :search
                    OR wo.contact_number LIKE :search
                    OR s.full_name LIKE :search
                    OR s.account_number LIKE :search
                    OR s.contact_number LIKE :search
                    OR ss.service_number LIKE :search
                    OR ss.ppp_username LIKE :search
                    OR t.ticket_no LIKE :search
                )
            ";

            $params[':search'] = '%' . trim((string)$filters['search']) . '%';
        }

        $sql .= " ORDER BY wo.id DESC LIMIT :limit OFFSET :offset";

        $limit = isset($filters['limit']) ? max(1, min(200, (int)$filters['limit'])) : 100;
        $offset = isset($filters['offset']) ? max(0, (int)$filters['offset']) : 0;

        $stmt = $this->db->prepare($sql);

        foreach ($params as $key => $value) {
            if ($key === ':assigned_user_id') {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
                continue;
            }

            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function countWorkOrders(array $filters = []): int
    {
        $sql = "
            SELECT COUNT(*)
            FROM work_orders wo
            LEFT JOIN subscribers s ON s.id = wo.subscriber_id
            LEFT JOIN subscriber_services ss ON ss.id = wo.service_id
            LEFT JOIN tickets t ON t.id = wo.ticket_id
            WHERE 1 = 1
        ";

        $params = [];

        if (!empty($filters['status'])) {
            $sql .= " AND wo.status = :status";
            $params[':status'] = strtoupper((string)$filters['status']);
        }

        if (!empty($filters['work_order_type'])) {
            $sql .= " AND wo.work_order_type = :work_order_type";
            $params[':work_order_type'] = strtoupper((string)$filters['work_order_type']);
        }

        if (!empty($filters['assigned_user_id'])) {
            $sql .= " AND wo.assigned_user_id = :assigned_user_id";
            $params[':assigned_user_id'] = (int)$filters['assigned_user_id'];
        }

        if (!empty($filters['search'])) {
            $sql .= "
                AND (
                    wo.work_order_no LIKE :search
                    OR wo.title LIKE :search
                    OR wo.description LIKE :search
                    OR wo.work_order_type LIKE :search
                    OR wo.status LIKE :search
                    OR wo.contact_name LIKE :search
                    OR wo.contact_number LIKE :search
                    OR s.full_name LIKE :search
                    OR s.account_number LIKE :search
                    OR s.contact_number LIKE :search
                    OR ss.service_number LIKE :search
                    OR ss.ppp_username LIKE :search
                    OR t.ticket_no LIKE :search
                )
            ";

            $params[':search'] = '%' . trim((string)$filters['search']) . '%';
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn();
    }

    public function getSummary(): array
    {
        $stmt = $this->db->query("
            SELECT
                COALESCE(SUM(CASE WHEN status = 'OPEN' THEN 1 ELSE 0 END), 0) AS open_count,
                COALESCE(SUM(CASE WHEN status = 'ASSIGNED' THEN 1 ELSE 0 END), 0) AS assigned_count,
                COALESCE(SUM(CASE WHEN status IN ('IN_PROGRESS','ON_SITE') THEN 1 ELSE 0 END), 0) AS active_count,
                COALESCE(SUM(CASE WHEN status = 'COMPLETED' THEN 1 ELSE 0 END), 0) AS completed_count,
                COALESCE(SUM(CASE WHEN status IN ('FAILED','CANCELLED') THEN 1 ELSE 0 END), 0) AS issue_count,
                COUNT(*) AS total_count
            FROM work_orders
        ");

        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'open_count' => (int)($row['open_count'] ?? 0),
            'assigned_count' => (int)($row['assigned_count'] ?? 0),
            'active_count' => (int)($row['active_count'] ?? 0),
            'completed_count' => (int)($row['completed_count'] ?? 0),
            'issue_count' => (int)($row['issue_count'] ?? 0),
            'total_count' => (int)($row['total_count'] ?? 0),
        ];
    }

    public function findWorkOrder(int $workOrderId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                wo.*,
                COALESCE(s.account_number, '-') AS account_number,
                COALESCE(s.full_name, wo.contact_name, 'Unknown Subscriber') AS subscriber_name,
                COALESCE(s.contact_number, wo.contact_number, '-') AS subscriber_contact_number,
                COALESCE(s.email, '-') AS subscriber_email,
                COALESCE(s.address, wo.location, '-') AS subscriber_address,
                ss.service_number,
                ss.ppp_username,
                ss.status AS service_status,
                t.ticket_no,
                t.subject AS ticket_subject,
                au.full_name AS assigned_user_name,
                au.username AS assigned_username,
                cu.full_name AS created_by_name,
                cu.username AS created_by_username
            FROM work_orders wo
            LEFT JOIN subscribers s ON s.id = wo.subscriber_id
            LEFT JOIN subscriber_services ss ON ss.id = wo.service_id
            LEFT JOIN tickets t ON t.id = wo.ticket_id
            LEFT JOIN users au ON au.id = wo.assigned_user_id
            LEFT JOIN users cu ON cu.id = wo.created_by_user_id
            WHERE wo.id = :work_order_id
            LIMIT 1
        ");

        $stmt->execute([
            ':work_order_id' => $workOrderId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function getTasks(int $workOrderId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                wot.*,
                u.full_name AS completed_by_name,
                u.username AS completed_by_username
            FROM work_order_tasks wot
            LEFT JOIN users u ON u.id = wot.completed_by_user_id
            WHERE wot.work_order_id = :work_order_id
            ORDER BY wot.id ASC
        ");

        $stmt->execute([
            ':work_order_id' => $workOrderId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getStatusLogs(int $workOrderId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                wosl.*,
                u.full_name,
                u.username,
                u.role
            FROM work_order_status_logs wosl
            LEFT JOIN users u ON u.id = wosl.changed_by_user_id
            WHERE wosl.work_order_id = :work_order_id
            ORDER BY wosl.id ASC
        ");

        $stmt->execute([
            ':work_order_id' => $workOrderId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function generateWorkOrderNo(): string
    {
        $year = date('Y');

        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM work_orders
            WHERE work_order_no LIKE :prefix
        ");

        $stmt->execute([
            ':prefix' => 'WO-' . $year . '-%',
        ]);

        $next = ((int)$stmt->fetchColumn()) + 1;

        return 'WO-' . $year . '-' . str_pad((string)$next, 6, '0', STR_PAD_LEFT);
    }

    public function findBySource(string $sourceType, int $sourceId, ?string $workOrderType = null): ?array
    {
        $typeSql = $workOrderType !== null ? ' AND work_order_type = ?' : '';
        $stmt = $this->db->prepare("
            SELECT *
            FROM work_orders
            WHERE source_type = ? AND source_id = ? {$typeSql}
            ORDER BY id DESC
            LIMIT 1
        ");
        $params = [strtoupper($sourceType), $sourceId];
        if ($workOrderType !== null) $params[] = strtoupper($workOrderType);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function acquireSourceLock(string $sourceType, int $sourceId): void
    {
        $name = sprintf('nexusbox:work-order:%s:%d', strtolower($sourceType), $sourceId);
        $stmt = $this->db->prepare('SELECT GET_LOCK(?, 15)');
        $stmt->execute([$name]);
        if ((int)$stmt->fetchColumn() !== 1) {
            throw new \RuntimeException('Work order generation is busy. Please retry shortly.');
        }
    }

    public function releaseSourceLock(string $sourceType, int $sourceId): void
    {
        $name = sprintf('nexusbox:work-order:%s:%d', strtolower($sourceType), $sourceId);
        $stmt = $this->db->prepare('SELECT RELEASE_LOCK(?)');
        $stmt->execute([$name]);
    }

    public function createWorkOrder(array $data): int
    {
        $stmt = $this->db->prepare("
        INSERT INTO work_orders (
            work_order_no,
            source_type,
            source_id,
            ticket_id,
            subscriber_id,
            service_id,
            work_order_type,
            title,
            description,
            priority,
            status,
            assigned_user_id,
            scheduled_date,
            scheduled_time,
            location,
            contact_name,
            contact_number,
            created_by_user_id
        ) VALUES (
            :work_order_no,
            :source_type,
            :source_id,
            :ticket_id,
            :subscriber_id,
            :service_id,
            :work_order_type,
            :title,
            :description,
            :priority,
            :status,
            :assigned_user_id,
            :scheduled_date,
            :scheduled_time,
            :location,
            :contact_name,
            :contact_number,
            :created_by_user_id
        )
    ");

        $stmt->execute([
            ':work_order_no' => $data['work_order_no'],
            ':source_type' => $data['source_type'],
            ':source_id' => $data['source_id'],
            ':ticket_id' => $data['ticket_id'],
            ':subscriber_id' => $data['subscriber_id'],
            ':service_id' => $data['service_id'],
            ':work_order_type' => $data['work_order_type'],
            ':title' => $data['title'],
            ':description' => $data['description'],
            ':priority' => $data['priority'],
            ':status' => $data['status'],
            ':assigned_user_id' => $data['assigned_user_id'],
            ':scheduled_date' => $data['scheduled_date'] ?? null,
            ':scheduled_time' => $data['scheduled_time'] ?? null,
            ':location' => $data['location'],
            ':contact_name' => $data['contact_name'],
            ':contact_number' => $data['contact_number'],
            ':created_by_user_id' => $data['created_by_user_id'],
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function createStatusLog(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO work_order_status_logs (
                work_order_id,
                old_status,
                new_status,
                changed_by_user_id,
                note
            ) VALUES (
                :work_order_id,
                :old_status,
                :new_status,
                :changed_by_user_id,
                :note
            )
        ");

        $stmt->execute([
            ':work_order_id' => $data['work_order_id'],
            ':old_status' => $data['old_status'],
            ':new_status' => $data['new_status'],
            ':changed_by_user_id' => $data['changed_by_user_id'],
            ':note' => $data['note'],
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function createTask(int $workOrderId, string $taskName, bool $required = true): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO work_order_tasks (
                work_order_id,
                task_name,
                is_required
            ) VALUES (
                :work_order_id,
                :task_name,
                :is_required
            )
        ");

        $stmt->execute([
            ':work_order_id' => $workOrderId,
            ':task_name' => $taskName,
            ':is_required' => $required ? 1 : 0,
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function findAvailableTechnicians(): array
    {
        $stmt = $this->db->query("
            SELECT
                u.id,
                u.username,
                u.full_name,
                u.email,
                u.role,
                sa.status,
                sa.time_in_at,
                COUNT(wo.id) AS active_work_order_count
            FROM staff_attendance sa
            INNER JOIN users u ON u.id = sa.user_id
            LEFT JOIN work_orders wo
                ON wo.assigned_user_id = u.id
               AND wo.status IN ('ASSIGNED','IN_PROGRESS','ON_SITE')
            WHERE sa.attendance_date = CURDATE()
              AND sa.time_in_at IS NOT NULL
              AND sa.time_out_at IS NULL
              AND sa.status = 'AVAILABLE'
              AND u.status = 'ACTIVE'
              AND u.role = 'TECHNICIAN'
            GROUP BY
                u.id,
                u.username,
                u.full_name,
                u.email,
                u.role,
                sa.status,
                sa.time_in_at
            ORDER BY
                active_work_order_count ASC,
                sa.time_in_at ASC,
                u.id ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findNearestAvailableTechnician(float $latitude, float $longitude): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                u.id,
                u.username,
                u.full_name,
                sa.time_in_latitude AS latitude,
                sa.time_in_longitude AS longitude,
                COUNT(wo.id) AS active_work_order_count,
                6371 * ACOS(LEAST(1, GREATEST(-1,
                    COS(RADIANS(?)) * COS(RADIANS(sa.time_in_latitude))
                    * COS(RADIANS(sa.time_in_longitude) - RADIANS(?))
                    + SIN(RADIANS(?)) * SIN(RADIANS(sa.time_in_latitude))
                ))) AS distance_km
            FROM staff_attendance sa
            INNER JOIN users u ON u.id = sa.user_id
            LEFT JOIN work_orders wo
                ON wo.assigned_user_id = u.id
               AND wo.status IN ('ASSIGNED','IN_PROGRESS','ON_SITE')
            WHERE sa.attendance_date = CURDATE()
              AND sa.time_in_at IS NOT NULL
              AND sa.time_out_at IS NULL
              AND sa.status = 'AVAILABLE'
              AND sa.time_in_latitude IS NOT NULL
              AND sa.time_in_longitude IS NOT NULL
              AND u.status = 'ACTIVE'
              AND u.role = 'TECHNICIAN'
            GROUP BY
                u.id, u.username, u.full_name,
                sa.time_in_latitude, sa.time_in_longitude,
                sa.time_in_at
            ORDER BY distance_km ASC, active_work_order_count ASC, sa.time_in_at ASC, u.id ASC
            LIMIT 1
        ");
        $stmt->execute([$latitude, $longitude, $latitude]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getAssignableTechnicians(): array
    {
        $stmt = $this->db->query("
        SELECT
            u.id,
            u.username,
            u.full_name,
            u.email,
            u.role,
            COALESCE(sa.status, 'OFFLINE') AS attendance_status,
            sa.time_in_at,
            sa.time_out_at,
            COUNT(wo.id) AS active_work_order_count
        FROM users u
        LEFT JOIN staff_attendance sa
            ON sa.user_id = u.id
           AND sa.attendance_date = CURDATE()
        LEFT JOIN work_orders wo
            ON wo.assigned_user_id = u.id
           AND wo.status IN ('ASSIGNED','IN_PROGRESS','ON_SITE')
        WHERE u.status = 'ACTIVE'
          AND u.role = 'TECHNICIAN'
        GROUP BY
            u.id,
            u.username,
            u.full_name,
            u.email,
            u.role,
            sa.status,
            sa.time_in_at,
            sa.time_out_at
        ORDER BY
            CASE
                WHEN sa.status = 'AVAILABLE'
                 AND sa.time_in_at IS NOT NULL
                 AND sa.time_out_at IS NULL THEN 1
                ELSE 2
            END ASC,
            active_work_order_count ASC,
            u.full_name ASC,
            u.username ASC
    ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findActiveTechnician(int $userId): ?array
    {
        $stmt = $this->db->prepare("SELECT id, username, full_name, role, status FROM users WHERE id = :id AND role = 'TECHNICIAN' AND status = 'ACTIVE' LIMIT 1");
        $stmt->execute([':id' => $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findDispatchableTechnician(int $userId): ?array
    {
        $stmt = $this->db->prepare("SELECT u.id, u.username, u.full_name FROM users u INNER JOIN staff_attendance sa ON sa.user_id=u.id AND sa.attendance_date=CURDATE() WHERE u.id=:id AND u.role='TECHNICIAN' AND u.status='ACTIVE' AND sa.time_in_at IS NOT NULL AND sa.time_out_at IS NULL AND sa.status='AVAILABLE' LIMIT 1");
        $stmt->execute([':id' => $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function assignWorkOrder(int $workOrderId, ?int $assignedUserId): bool
    {
        $stmt = $this->db->prepare("
        UPDATE work_orders
        SET
            assigned_user_id = :assigned_user_id,
            status = CASE
                WHEN :assigned_user_id_status IS NULL THEN 'OPEN'
                WHEN status = 'OPEN' THEN 'ASSIGNED'
                ELSE status
            END,
            updated_at = NOW()
        WHERE id = :work_order_id
        LIMIT 1
    ");

        return $stmt->execute([
            ':assigned_user_id' => $assignedUserId,
            ':assigned_user_id_status' => $assignedUserId,
            ':work_order_id' => $workOrderId,
        ]);
    }

    public function updateWorkOrderStatus(int $workOrderId, string $status, ?int $changedByUserId = null, string $note = ''): bool
    {
        $status = strtoupper($status);

        $startedAtSql = $status === 'IN_PROGRESS' ? ', started_at = COALESCE(started_at, NOW())' : '';
        $arrivedAtSql = $status === 'ON_SITE' ? ', arrived_at = COALESCE(arrived_at, NOW())' : '';
        $completedAtSql = $status === 'COMPLETED' ? ', completed_at = COALESCE(completed_at, NOW())' : '';
        $cancelledAtSql = $status === 'CANCELLED' ? ', cancelled_at = COALESCE(cancelled_at, NOW())' : '';

        if ($status !== 'COMPLETED') {
            $stmt = $this->db->prepare("
                UPDATE work_orders
                SET status = :status,
                    updated_at = NOW()
                    {$startedAtSql}
                    {$arrivedAtSql}
                    {$completedAtSql}
                    {$cancelledAtSql},
                    completion_notes = CASE WHEN :status_note = 'COMPLETED' AND :note_completion <> '' THEN :note_completion_value ELSE completion_notes END,
                    failure_reason = CASE WHEN :status_failure = 'FAILED' AND :note_failure <> '' THEN :note_failure_value ELSE failure_reason END
                WHERE id = :work_order_id
                LIMIT 1
            ");

            return $stmt->execute([
                ':status' => $status,
                ':work_order_id' => $workOrderId,
                ':status_note' => $status,
                ':note_completion' => $note,
                ':note_completion_value' => $note,
                ':status_failure' => $status,
                ':note_failure' => $note,
                ':note_failure_value' => $note,
            ]);
        }

        $ownsTransaction = !$this->db->inTransaction();
        try {
            if ($ownsTransaction) $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                SELECT ticket_id, source_type, source_id, service_id, work_order_type
                FROM work_orders
                WHERE id = :work_order_id
                LIMIT 1
                FOR UPDATE
            ");
            $stmt->execute([':work_order_id' => $workOrderId]);
            $completionContext = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $ticketId = (int)($completionContext['ticket_id'] ?? 0);

            $stmt = $this->db->prepare("
                UPDATE work_orders
                SET status = 'COMPLETED',
                    updated_at = NOW(),
                    completed_at = COALESCE(completed_at, NOW()),
                    completion_notes = CASE WHEN :note <> '' THEN :note_value ELSE completion_notes END
                WHERE id = :work_order_id
                LIMIT 1
            ");
            $ok = $stmt->execute([':work_order_id' => $workOrderId, ':note' => $note, ':note_value' => $note]);

            if (
                strtoupper((string)($completionContext['source_type'] ?? '')) === 'SERVICE_PROVISIONING'
                && strtoupper((string)($completionContext['work_order_type'] ?? '')) === 'ONT_INSTALLATION'
                && (int)($completionContext['service_id'] ?? 0) > 0
            ) {
                $stmt = $this->db->prepare("
                    UPDATE service_provisioning_bindings
                    SET installed_at = COALESCE(installed_at, NOW())
                    WHERE service_id = :service_id
                      AND activated_at IS NOT NULL
                ");
                $stmt->execute([':service_id' => (int)$completionContext['service_id']]);
                if ($stmt->rowCount() < 1) {
                    $check = $this->db->prepare("
                        SELECT installed_at
                        FROM service_provisioning_bindings
                        WHERE service_id = :service_id
                        LIMIT 1
                    ");
                    $check->execute([':service_id' => (int)$completionContext['service_id']]);
                    if (!$check->fetchColumn()) {
                        throw new \RuntimeException('Installation completion could not update the provisioning binding.');
                    }
                }
            }

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
                            (:ticket_id, :old_status, 'RESOLVED', :changed_by_user_id, :note)
                    ");
                    $stmt->execute([
                        ':ticket_id' => $ticketId,
                        ':old_status' => $oldTicketStatus,
                        ':changed_by_user_id' => $changedByUserId,
                        ':note' => 'Automatically resolved when linked work order was completed.',
                    ]);
                }
            }

            if ($ownsTransaction) $this->db->commit();
            return $ok;
        } catch (Throwable $e) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function findTask(int $taskId): ?array
    {
        $stmt = $this->db->prepare("
        SELECT *
        FROM work_order_tasks
        WHERE id = :task_id
        LIMIT 1
    ");

        $stmt->execute([
            ':task_id' => $taskId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function completeTask(int $taskId, int $userId, string $notes = ''): bool
    {
        $stmt = $this->db->prepare("
        UPDATE work_order_tasks
        SET
            is_completed = 1,
            completed_at = NOW(),
            completed_by_user_id = :user_id,
            notes = CASE
                WHEN :notes = '' THEN notes
                ELSE :notes
            END
        WHERE id = :task_id
        LIMIT 1
    ");

        return $stmt->execute([
            ':task_id' => $taskId,
            ':user_id' => $userId,
            ':notes' => $notes,
        ]);
    }

    public function reopenTask(int $taskId): bool
    {
        $stmt = $this->db->prepare("
        UPDATE work_order_tasks
        SET
            is_completed = 0,
            completed_at = NULL,
            completed_by_user_id = NULL
        WHERE id = :task_id
        LIMIT 1
    ");

        return $stmt->execute([
            ':task_id' => $taskId,
        ]);
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

        $stmt->execute([
            ':work_order_id' => $workOrderId,
        ]);

        return (int)$stmt->fetchColumn() === 0;
    }
}
