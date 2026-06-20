<?php

namespace App\Modules\SubscriberPortal\Repositories;

use Framework\DatabaseConnection;
use PDO;

class SubscriberPortalRepository
{
    private PDO $db;

    public function __construct(DatabaseConnection $connection)
    {
        $this->db = $connection->get();
    }

    public function findSubscriberByUserId(int $userId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                s.id,
                s.user_id,
                s.account_number,
                s.full_name,
                s.address,
                s.contact_number,
                s.email,
                s.status,
                s.created_at,
                s.updated_at,

                u.username,
                u.role AS user_role,
                u.status AS user_status,
                u.last_login
            FROM subscribers s
            INNER JOIN users u ON u.id = s.user_id
            WHERE s.user_id = :user_id
              AND s.deleted_at IS NULL
            LIMIT 1
        ");

        $stmt->execute([
            ':user_id' => $userId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findSubscriberById(int $subscriberId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                id,
                user_id,
                account_number,
                full_name,
                address,
                contact_number,
                email,
                status,
                created_at,
                updated_at
            FROM subscribers
            WHERE id = :subscriber_id
              AND deleted_at IS NULL
            LIMIT 1
        ");

        $stmt->execute([
            ':subscriber_id' => $subscriberId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function getServices(int $subscriberId): array
    {
        $stmt = $this->db->prepare("
        SELECT
            ss.id,
            ss.id AS service_id,
            ss.subscriber_id,
            ss.service_number,
            ss.ppp_username,
            ss.plan_id,
            ss.account_type,
            ss.status,
            ss.next_due_date,
            ss.expires_at,
            ss.created_at,
            ss.updated_at,

            p.plan_name,
            p.price,
            p.plan_type,
            p.validity_days,
            p.speed_down,
            p.speed_up,
            p.speed_mbps,

            spb.ont_serial,
            spb.cvlan,
            spb.svlan,
            spb.ont_assigned_id,
            spb.pppoe_service_port,
            spb.tr069_service_port,
            spb.activated_at,

            spb.network_box_id,
            spb.splitter_id,
            spb.splitter_output_port_id,

            nb.box_code AS nap_box_code,
            nb.box_name AS nap_name,
            nb.location AS nap_location,
            nb.address AS nap_address,

            sop.port_number AS nap_box_port,
            sop.status AS nap_box_port_status,

            od.name AS olt_name,
            CONCAT_WS('/', op.frame, op.slot, op.port) AS olt_port_label

        FROM subscriber_services ss

        LEFT JOIN plans p 
            ON p.id = ss.plan_id

        LEFT JOIN service_provisioning_bindings spb 
            ON spb.service_id = ss.id

        LEFT JOIN network_boxes nb
            ON nb.id = spb.network_box_id
           AND nb.deleted_at IS NULL

        LEFT JOIN splitter_output_ports sop
            ON sop.id = spb.splitter_output_port_id
           AND sop.deleted_at IS NULL

        LEFT JOIN olt_devices od 
            ON od.id = spb.olt_id

        LEFT JOIN olt_ports op 
            ON op.id = spb.olt_port_id

        WHERE ss.subscriber_id = :subscriber_id

        ORDER BY ss.id DESC
    ");

        $stmt->execute([
            ':subscriber_id' => $subscriberId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getOverview(int $subscriberId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) AS invoice_count,
                COALESCE(SUM(total_amount), 0) AS total_billed,
                COALESCE(SUM(paid_amount), 0) AS total_paid,
                COALESCE(SUM(balance_amount), 0) AS total_balance,
                COALESCE(SUM(CASE WHEN status = 'UNPAID' THEN balance_amount ELSE 0 END), 0) AS unpaid_balance,
                COALESCE(SUM(CASE WHEN status = 'PARTIAL' THEN balance_amount ELSE 0 END), 0) AS partial_balance,
                COALESCE(SUM(CASE WHEN status = 'OVERDUE' THEN balance_amount ELSE 0 END), 0) AS overdue_balance,
                COALESCE(SUM(CASE WHEN status = 'PAID' THEN total_amount ELSE 0 END), 0) AS paid_total
            FROM invoices
            WHERE subscriber_id = :subscriber_id
              AND status != 'CANCELLED'
        ");

        $stmt->execute([
            ':subscriber_id' => $subscriberId,
        ]);

        $summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $nextDueStmt = $this->db->prepare("
            SELECT
                i.id,
                i.invoice_no,
                i.service_id,
                i.billing_period_start,
                i.billing_period_end,
                i.issue_date,
                i.due_date,
                i.total_amount,
                i.paid_amount,
                i.balance_amount,
                i.status,
                p.plan_name
            FROM invoices i
            LEFT JOIN plans p ON p.id = i.plan_id
            WHERE i.subscriber_id = :subscriber_id
              AND i.status IN ('UNPAID', 'PARTIAL', 'OVERDUE')
              AND i.balance_amount > 0
            ORDER BY
                CASE WHEN i.status = 'OVERDUE' THEN 0 ELSE 1 END ASC,
                i.due_date ASC,
                i.id ASC
            LIMIT 1
        ");

        $nextDueStmt->execute([
            ':subscriber_id' => $subscriberId,
        ]);

        $latestInvoiceStmt = $this->db->prepare("
            SELECT
                i.id,
                i.invoice_no,
                i.service_id,
                i.billing_period_start,
                i.billing_period_end,
                i.issue_date,
                i.due_date,
                i.total_amount,
                i.paid_amount,
                i.balance_amount,
                i.status,
                p.plan_name
            FROM invoices i
            LEFT JOIN plans p ON p.id = i.plan_id
            WHERE i.subscriber_id = :subscriber_id
              AND i.status != 'CANCELLED'
            ORDER BY i.id DESC
            LIMIT 1
        ");

        $latestInvoiceStmt->execute([
            ':subscriber_id' => $subscriberId,
        ]);

        $serviceStmt = $this->db->prepare("
            SELECT
                COUNT(*) AS service_count,
                COALESCE(SUM(CASE WHEN status = 'ACTIVE' THEN 1 ELSE 0 END), 0) AS active_service_count,
                MIN(next_due_date) AS nearest_next_due_date
            FROM subscriber_services
            WHERE subscriber_id = :subscriber_id
        ");

        $serviceStmt->execute([
            ':subscriber_id' => $subscriberId,
        ]);

        return [
            'summary' => [
                'invoice_count' => (int)($summary['invoice_count'] ?? 0),
                'total_billed' => (float)($summary['total_billed'] ?? 0),
                'total_paid' => (float)($summary['total_paid'] ?? 0),
                'total_balance' => (float)($summary['total_balance'] ?? 0),
                'unpaid_balance' => (float)($summary['unpaid_balance'] ?? 0),
                'partial_balance' => (float)($summary['partial_balance'] ?? 0),
                'overdue_balance' => (float)($summary['overdue_balance'] ?? 0),
                'paid_total' => (float)($summary['paid_total'] ?? 0),
            ],
            'service_summary' => $serviceStmt->fetch(PDO::FETCH_ASSOC) ?: [
                'service_count' => 0,
                'active_service_count' => 0,
                'nearest_next_due_date' => null,
            ],
            'next_due_invoice' => $nextDueStmt->fetch(PDO::FETCH_ASSOC) ?: null,
            'latest_invoice' => $latestInvoiceStmt->fetch(PDO::FETCH_ASSOC) ?: null,
        ];
    }

    public function getInvoices(int $subscriberId, array $filters = []): array
    {
        $sql = "
            SELECT
                i.*,
                ss.service_number,
                ss.ppp_username,
                p.plan_name
            FROM invoices i
            LEFT JOIN subscriber_services ss ON ss.id = i.service_id
            LEFT JOIN plans p ON p.id = i.plan_id
            WHERE i.subscriber_id = :subscriber_id
        ";

        $params = [
            ':subscriber_id' => $subscriberId,
        ];

        if (!empty($filters['status'])) {
            $sql .= " AND i.status = :status";
            $params[':status'] = strtoupper((string)$filters['status']);
        }

        if (!empty($filters['search'])) {
            $sql .= "
                AND (
                    i.invoice_no LIKE :search
                    OR p.plan_name LIKE :search
                    OR ss.service_number LIKE :search
                    OR ss.ppp_username LIKE :search
                )
            ";
            $params[':search'] = '%' . trim((string)$filters['search']) . '%';
        }

        $sql .= " ORDER BY i.id DESC LIMIT :limit OFFSET :offset";

        $limit = isset($filters['limit']) ? max(1, min(100, (int)$filters['limit'])) : 50;
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

    public function countInvoices(int $subscriberId, array $filters = []): int
    {
        $sql = "
            SELECT COUNT(*)
            FROM invoices i
            LEFT JOIN subscriber_services ss ON ss.id = i.service_id
            LEFT JOIN plans p ON p.id = i.plan_id
            WHERE i.subscriber_id = :subscriber_id
        ";

        $params = [
            ':subscriber_id' => $subscriberId,
        ];

        if (!empty($filters['status'])) {
            $sql .= " AND i.status = :status";
            $params[':status'] = strtoupper((string)$filters['status']);
        }

        if (!empty($filters['search'])) {
            $sql .= "
                AND (
                    i.invoice_no LIKE :search
                    OR p.plan_name LIKE :search
                    OR ss.service_number LIKE :search
                    OR ss.ppp_username LIKE :search
                )
            ";
            $params[':search'] = '%' . trim((string)$filters['search']) . '%';
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn();
    }

    public function findInvoiceForSubscriber(int $invoiceId, int $subscriberId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                i.*,
                ss.service_number,
                ss.ppp_username,
                ss.account_type,
                ss.status AS service_status,
                p.plan_name,
                p.price AS plan_price,
                p.speed_down,
                p.speed_up,
                p.speed_mbps
            FROM invoices i
            LEFT JOIN subscriber_services ss ON ss.id = i.service_id
            LEFT JOIN plans p ON p.id = i.plan_id
            WHERE i.id = :invoice_id
              AND i.subscriber_id = :subscriber_id
            LIMIT 1
        ");

        $stmt->execute([
            ':invoice_id' => $invoiceId,
            ':subscriber_id' => $subscriberId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function getInvoiceItems(int $invoiceId): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM invoice_items
            WHERE invoice_id = :invoice_id
            ORDER BY id ASC
        ");

        $stmt->execute([
            ':invoice_id' => $invoiceId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getInvoicePayments(int $invoiceId): array
    {
        $stmt = $this->db->prepare("
        SELECT
            p.id,
            p.payment_no,
            p.invoice_id,
            i.invoice_no,
            p.subscriber_id,
            p.service_id,
            p.amount,
            p.payment_date,
            p.method,
            p.reference_no,
            p.payment_status,
            p.remarks,
            p.created_at,
            p.updated_at,
            pa.allocated_amount
        FROM payment_allocations pa
        INNER JOIN payments p ON p.id = pa.payment_id
        INNER JOIN invoices i ON i.id = pa.invoice_id
        WHERE pa.invoice_id = :invoice_id
        ORDER BY p.payment_date DESC, p.id DESC
    ");

        $stmt->execute([
            ':invoice_id' => $invoiceId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getPayments(int $subscriberId, array $filters = []): array
    {
        $sql = "
            SELECT
                p.*,
                i.invoice_no,
                i.billing_period_start,
                i.billing_period_end,
                i.status AS invoice_status,
                pa.allocated_amount
            FROM payments p
            LEFT JOIN invoices i ON i.id = p.invoice_id
            LEFT JOIN payment_allocations pa ON pa.payment_id = p.id AND pa.invoice_id = p.invoice_id
            WHERE p.subscriber_id = :subscriber_id
        ";

        $params = [
            ':subscriber_id' => $subscriberId,
        ];

        if (!empty($filters['search'])) {
            $sql .= "
                AND (
                    p.payment_no LIKE :search
                    OR p.reference_no LIKE :search
                    OR p.method LIKE :search
                    OR i.invoice_no LIKE :search
                )
            ";
            $params[':search'] = '%' . trim((string)$filters['search']) . '%';
        }

        $sql .= " ORDER BY p.id DESC LIMIT :limit OFFSET :offset";

        $limit = isset($filters['limit']) ? max(1, min(100, (int)$filters['limit'])) : 50;
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

    public function countPayments(int $subscriberId, array $filters = []): int
    {
        $sql = "
            SELECT COUNT(*)
            FROM payments p
            LEFT JOIN invoices i ON i.id = p.invoice_id
            WHERE p.subscriber_id = :subscriber_id
        ";

        $params = [
            ':subscriber_id' => $subscriberId,
        ];

        if (!empty($filters['search'])) {
            $sql .= "
                AND (
                    p.payment_no LIKE :search
                    OR p.reference_no LIKE :search
                    OR p.method LIKE :search
                    OR i.invoice_no LIKE :search
                )
            ";
            $params[':search'] = '%' . trim((string)$filters['search']) . '%';
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn();
    }

    public function findPaymentReceiptForSubscriber(int $paymentId, int $subscriberId): ?array
    {
        $stmt = $this->db->prepare("
        SELECT
            p.*,
            i.invoice_no,
            i.billing_period_start,
            i.billing_period_end,
            i.total_amount,
            i.paid_amount,
            i.balance_amount,
            i.status AS invoice_status,
            s.account_number,
            s.full_name,
            s.address,
            s.contact_number,
            s.email,
            ss.service_number,
            pl.plan_name,
            pa.allocated_amount
        FROM payments p
        LEFT JOIN invoices i ON i.id = p.invoice_id
        LEFT JOIN subscribers s ON s.id = p.subscriber_id
        LEFT JOIN subscriber_services ss ON ss.id = p.service_id
        LEFT JOIN plans pl ON pl.id = i.plan_id
        LEFT JOIN payment_allocations pa 
            ON pa.payment_id = p.id 
           AND pa.invoice_id = p.invoice_id
        WHERE p.id = :payment_id
          AND p.subscriber_id = :subscriber_id
        LIMIT 1
    ");

        $stmt->execute([
            ':payment_id' => $paymentId,
            ':subscriber_id' => $subscriberId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findUserForPasswordChange(int $userId): ?array
    {
        $stmt = $this->db->prepare("
        SELECT
            id,
            username,
            email,
            password,
            role,
            status
        FROM users
        WHERE id = :user_id
          AND role = 'SUBSCRIBER'
          AND status = 'ACTIVE'
        LIMIT 1
    ");

        $stmt->execute([
            ':user_id' => $userId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function updateUserPassword(int $userId, string $passwordHash): bool
    {
        $stmt = $this->db->prepare("
        UPDATE users
        SET
            password = :password,
            updated_at = NOW()
        WHERE id = :user_id
          AND role = 'SUBSCRIBER'
          AND status = 'ACTIVE'
        LIMIT 1
    ");

        return $stmt->execute([
            ':password' => $passwordHash,
            ':user_id' => $userId,
        ]);
    }

    public function getTickets(int $subscriberId, array $filters = []): array
    {
        $sql = "
        SELECT
            id,
            ticket_no,
            subscriber_id,
            service_id,
            category,
            subject,
            description,
            priority,
            preferred_visit_date,
            preferred_visit_time,
            preferred_visit_notes,
            status,
            assigned_user_id,
            work_order_id,
            created_by_type,
            created_by_user_id,
            resolved_at,
            closed_at,
            created_at,
            updated_at
        FROM tickets
        WHERE subscriber_id = :subscriber_id
    ";

        $params = [
            ':subscriber_id' => $subscriberId,
        ];

        if (!empty($filters['status'])) {
            $sql .= " AND status = :status";
            $params[':status'] = strtoupper((string)$filters['status']);
        }

        if (!empty($filters['search'])) {
            $sql .= "
            AND (
                ticket_no LIKE :search
                OR subject LIKE :search
                OR category LIKE :search
                OR status LIKE :search
            )
        ";
            $params[':search'] = '%' . trim((string)$filters['search']) . '%';
        }

        $sql .= " ORDER BY id DESC LIMIT :limit OFFSET :offset";

        $limit = isset($filters['limit']) ? max(1, min(100, (int)$filters['limit'])) : 50;
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

    public function countTickets(int $subscriberId, array $filters = []): int
    {
        $sql = "
        SELECT COUNT(*)
        FROM tickets
        WHERE subscriber_id = :subscriber_id
    ";

        $params = [
            ':subscriber_id' => $subscriberId,
        ];

        if (!empty($filters['status'])) {
            $sql .= " AND status = :status";
            $params[':status'] = strtoupper((string)$filters['status']);
        }

        if (!empty($filters['search'])) {
            $sql .= "
            AND (
                ticket_no LIKE :search
                OR subject LIKE :search
                OR category LIKE :search
                OR status LIKE :search
            )
        ";
            $params[':search'] = '%' . trim((string)$filters['search']) . '%';
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn();
    }

    public function createTicket(array $data): int
    {
        $stmt = $this->db->prepare("
        INSERT INTO tickets (
            ticket_no,
            subscriber_id,
            service_id,
            category,
            subject,
            description,
            priority,
            preferred_visit_date,
            preferred_visit_time,
            preferred_visit_notes,
            status,
            created_by_type,
            created_by_user_id
        ) VALUES (
            :ticket_no,
            :subscriber_id,
            :service_id,
            :category,
            :subject,
            :description,
            :priority,
            :preferred_visit_date,
            :preferred_visit_time,
            :preferred_visit_notes,
            :status,
            :created_by_type,
            :created_by_user_id
        )
    ");

        $stmt->execute([
            ':ticket_no' => $data['ticket_no'],
            ':subscriber_id' => $data['subscriber_id'],
            ':service_id' => $data['service_id'],
            ':category' => $data['category'],
            ':subject' => $data['subject'],
            ':description' => $data['description'],
            ':priority' => $data['priority'],
            ':preferred_visit_date' => $data['preferred_visit_date'] ?? null,
            ':preferred_visit_time' => $data['preferred_visit_time'] ?? null,
            ':preferred_visit_notes' => $data['preferred_visit_notes'] ?? null,
            ':status' => $data['status'],
            ':created_by_type' => $data['created_by_type'],
            ':created_by_user_id' => $data['created_by_user_id'],
        ]);

        return (int)$this->db->lastInsertId();
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

    public function generateTicketNo(): string
    {
        $prefix = 'TKT-' . date('Y') . '-';

        $stmt = $this->db->prepare("
        SELECT ticket_no
        FROM tickets
        WHERE ticket_no LIKE :prefix
        ORDER BY id DESC
        LIMIT 1
    ");

        $stmt->execute([
            ':prefix' => $prefix . '%',
        ]);

        $lastTicketNo = (string)($stmt->fetchColumn() ?: '');
        $nextNumber = 1;

        if ($lastTicketNo !== '') {
            $lastNumber = (int)substr($lastTicketNo, -6);
            $nextNumber = $lastNumber + 1;
        }

        return $prefix . str_pad((string)$nextNumber, 6, '0', STR_PAD_LEFT);
    }

    public function findTicketForSubscriber(int $ticketId, int $subscriberId): ?array
    {
        $stmt = $this->db->prepare("
        SELECT
            id,
            ticket_no,
            subscriber_id,
            service_id,
            category,
            subject,
            description,
            priority,
            preferred_visit_date,
            preferred_visit_time,
            preferred_visit_notes,
            status,
            assigned_user_id,
            work_order_id,
            created_by_type,
            created_by_user_id,
            resolved_at,
            closed_at,
            created_at,
            updated_at
        FROM tickets
        WHERE id = :ticket_id
          AND subscriber_id = :subscriber_id
        LIMIT 1
    ");

        $stmt->execute([
            ':ticket_id' => $ticketId,
            ':subscriber_id' => $subscriberId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function getTicketMessages(int $ticketId, bool $includeInternal = false): array
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
            u.username
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

    public function updateTicketVisitSchedule(
        int $ticketId,
        string $preferredVisitDate,
        string $preferredVisitTime,
        ?string $preferredVisitNotes,
        string $status = 'VISIT_SCHEDULED'
    ): bool {
        $stmt = $this->db->prepare("
        UPDATE tickets
        SET
            preferred_visit_date = :preferred_visit_date,
            preferred_visit_time = :preferred_visit_time,
            preferred_visit_notes = :preferred_visit_notes,
            status = :status,
            updated_at = NOW()
        WHERE id = :ticket_id
        LIMIT 1
    ");

        return $stmt->execute([
            ':preferred_visit_date' => $preferredVisitDate,
            ':preferred_visit_time' => $preferredVisitTime,
            ':preferred_visit_notes' => $preferredVisitNotes,
            ':status' => strtoupper($status),
            ':ticket_id' => $ticketId,
        ]);
    }
    public function updateTicketStatus(int $ticketId, string $status): bool
    {
        $stmt = $this->db->prepare("
        UPDATE tickets
        SET
            status = :status,
            updated_at = NOW()
        WHERE id = :ticket_id
        LIMIT 1
    ");

        return $stmt->execute([
            ':status' => strtoupper($status),
            ':ticket_id' => $ticketId,
        ]);
    }

    public function getVisitSlotUsageByDate(string $date): array
    {
        $stmt = $this->db->prepare("
        SELECT
            slot_time,
            SUM(booked_count) AS booked_count
        FROM (
            SELECT
                TIME_FORMAT(scheduled_time, '%H:%i') AS slot_time,
                COUNT(*) AS booked_count
            FROM work_orders
            WHERE scheduled_date = :work_order_date
              AND scheduled_time IS NOT NULL
              AND status NOT IN ('COMPLETED', 'FAILED', 'CANCELLED')
            GROUP BY TIME_FORMAT(scheduled_time, '%H:%i')

            UNION ALL

            SELECT
                TIME_FORMAT(preferred_visit_time, '%H:%i') AS slot_time,
                COUNT(*) AS booked_count
            FROM tickets
            WHERE preferred_visit_date = :ticket_date
              AND preferred_visit_time IS NOT NULL
              AND status NOT IN ('RESOLVED', 'CLOSED', 'CANCELLED')
              AND (work_order_id IS NULL OR work_order_id = 0)
            GROUP BY TIME_FORMAT(preferred_visit_time, '%H:%i')
        ) booked
        GROUP BY slot_time
        ORDER BY slot_time ASC
    ");

        $stmt->execute([
            ':work_order_date' => $date,
            ':ticket_date' => $date,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getActiveTechnicianCapacity(): int
    {
        $stmt = $this->db->query("
        SELECT COUNT(*)
        FROM users
        WHERE role = 'TECHNICIAN'
          AND status = 'ACTIVE'
    ");

        return max(1, (int)$stmt->fetchColumn());
    }

    public function updateTicketWorkOrderId(int $ticketId, int $workOrderId): bool
    {
        $stmt = $this->db->prepare("
        UPDATE tickets
        SET
            work_order_id = :work_order_id,
            updated_at = NOW()
        WHERE id = :ticket_id
        LIMIT 1
    ");

        return $stmt->execute([
            ':work_order_id' => $workOrderId,
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
                'WAITING_CUSTOMER_SCHEDULE',
                'WAITING_TECHNICIAN',
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