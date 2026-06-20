<?php

namespace App\Modules\Billing\Services;

use App\Modules\Audit\Services\AuditService;
use App\Modules\Billing\Repositories\BillingSettingsRepository;
use PDO;
use Throwable;

class BillingService
{
    private PDO $db;
    private BillingSettingsRepository $settings;
    private ?AuditService $audit;

    public function __construct(
        PDO $db,
        BillingSettingsRepository $settings,
        ?AuditService $audit = null
    ) {
        $this->db = $db;
        $this->settings = $settings;
        $this->audit = $audit;
    }

    public function overview(): array
    {
        return [
            'stats' => [
                'total_invoices' => $this->scalar("
                    SELECT COUNT(*)
                    FROM invoices
                    WHERE status != 'CANCELLED'
                "),

                'unpaid_invoices' => $this->scalar("
                    SELECT COUNT(*)
                    FROM invoices
                    WHERE status IN ('UNPAID','PARTIAL','OVERDUE')
                      AND balance_amount > 0
                "),

                'paid_invoices' => $this->scalar("
                    SELECT COUNT(*)
                    FROM invoices
                    WHERE status = 'PAID'
                "),

                'overdue_invoices' => $this->scalar("
                    SELECT COUNT(*)
                    FROM invoices
                    WHERE status IN ('UNPAID','PARTIAL','OVERDUE')
                      AND balance_amount > 0
                      AND due_date IS NOT NULL
                      AND due_date != '0000-00-00'
                      AND due_date < CURDATE()
                "),

                'total_billed' => $this->money("
                    SELECT COALESCE(SUM(total_amount), 0)
                    FROM invoices
                    WHERE status != 'CANCELLED'
                "),

                'total_collected' => $this->money("
                    SELECT COALESCE(SUM(amount), 0)
                    FROM payments
                    WHERE payment_status = 'POSTED'
                "),

                'total_balance' => $this->money("
                    SELECT COALESCE(SUM(balance_amount), 0)
                    FROM invoices
                    WHERE status != 'CANCELLED'
                      AND balance_amount > 0
                "),

                'today_collected' => $this->money("
                    SELECT COALESCE(SUM(amount), 0)
                    FROM payments
                    WHERE payment_status = 'POSTED'
                      AND DATE(payment_date) = CURDATE()
                "),
            ],

            'recent_invoices' => $this->recentInvoices(),
            'recent_payments' => $this->recentPayments(),
        ];
    }

    public function settings(): array
    {
        return [
            'rows' => $this->settings->listRows(),
            'map' => $this->settings->all(),
        ];
    }

    public function saveSettings(array $payload): array
    {
        $allowed = [
            'invoice_prefix',
            'payment_prefix',
            'adjustment_prefix',
            'default_due_days',
            'currency',
            'tax_enabled',
            'tax_rate',
            'grace_period_days',
            'auto_suspend_enabled',
        ];

        $changed = [];

        foreach ($allowed as $key) {
            if (array_key_exists($key, $payload)) {
                $this->settings->save($key, $payload[$key]);
                $changed[] = $key;
            }
        }

        if (!empty($changed)) {
            $this->safeAudit(
                'BILLING',
                'UPDATE_SETTINGS',
                'Updated billing settings: ' . implode(', ', $changed)
            );
        }

        return $this->settings();
    }

    public function supportSubscribers(): array
    {
        $stmt = $this->db->query("
            SELECT
                id,
                account_number,
                full_name,
                contact_number,
                email,
                status
            FROM subscribers
            WHERE deleted_at IS NULL
            ORDER BY full_name ASC
            LIMIT 500
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function supportServices(?int $subscriberId = null): array
    {
        $sql = "
            SELECT
                ss.id,
                ss.subscriber_id,
                ss.service_number,
                ss.ppp_username,
                ss.account_type,
                ss.status,
                ss.next_due_date,
                ss.expires_at,
                s.full_name AS subscriber_name,
                s.account_number,
                p.id AS plan_id,
                p.plan_name,
                p.price
            FROM subscriber_services ss
            INNER JOIN subscribers s ON s.id = ss.subscriber_id
            LEFT JOIN plans p ON p.id = ss.plan_id
            WHERE 1 = 1
        ";

        $params = [];

        if ($subscriberId) {
            $sql .= " AND ss.subscriber_id = :subscriber_id";
            $params[':subscriber_id'] = $subscriberId;
        }

        $sql .= " ORDER BY s.full_name ASC, ss.id DESC LIMIT 500";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function supportPlans(): array
    {
        $stmt = $this->db->query("
            SELECT
                id,
                plan_name,
                price,
                plan_type,
                validity_days,
                speed_down,
                speed_up,
                is_active
            FROM plans
            WHERE is_active = 1
            ORDER BY plan_name ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function recentInvoices(): array
    {
        $stmt = $this->db->query("
            SELECT
                i.id,
                i.invoice_no,
                i.total_amount,
                i.paid_amount,
                i.balance_amount,
                i.due_date,
                i.status,
                s.full_name AS subscriber_name,

                CASE
                    WHEN i.status IN ('UNPAID','PARTIAL','OVERDUE')
                         AND i.balance_amount > 0
                         AND i.due_date IS NOT NULL
                         AND i.due_date != '0000-00-00'
                         AND i.due_date < CURDATE()
                    THEN DATEDIFF(CURDATE(), i.due_date)
                    ELSE 0
                END AS days_overdue,

                CASE
                    WHEN i.status IN ('UNPAID','PARTIAL','OVERDUE')
                         AND i.balance_amount > 0
                         AND i.due_date IS NOT NULL
                         AND i.due_date != '0000-00-00'
                         AND i.due_date < CURDATE()
                    THEN 1
                    ELSE 0
                END AS is_overdue
            FROM invoices i
            LEFT JOIN subscribers s ON s.id = i.subscriber_id
            WHERE i.status != 'CANCELLED'
            ORDER BY i.id DESC
            LIMIT 10
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function recentPayments(): array
    {
        $stmt = $this->db->query("
            SELECT
                p.id,
                p.payment_no,
                p.amount,
                p.payment_date,
                p.method,
                p.reference_no,
                p.payment_status,
                i.invoice_no,
                s.full_name AS subscriber_name
            FROM payments p
            LEFT JOIN invoices i ON i.id = p.invoice_id
            LEFT JOIN subscribers s ON s.id = p.subscriber_id
            ORDER BY p.id DESC
            LIMIT 10
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function scalar(string $sql): int
    {
        return (int)$this->db->query($sql)->fetchColumn();
    }

    private function money(string $sql): float
    {
        return round((float)$this->db->query($sql)->fetchColumn(), 2);
    }

    private function safeAudit(string $module, string $action, string $description): void
    {
        if (!$this->audit) {
            return;
        }

        try {
            $this->audit->log($module, $action, $description);
        } catch (Throwable $e) {
        }
    }
}