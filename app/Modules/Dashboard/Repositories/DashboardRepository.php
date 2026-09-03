<?php

namespace App\Modules\Dashboard\Repositories;

use App\Infrastructure\Database\DatabaseConnection;
use PDO;
use Throwable;

class DashboardRepository
{
    private PDO $db;

    public function __construct(DatabaseConnection $connection)
    {
        $this->db = $connection->get();
    }

    public function getStats(): array
    {
        return [
            'subscribers' => $this->count('subscribers'),
            'active_services' => $this->countWhere('subscriber_services', "status = 'ACTIVE'"),
            'suspended_services' => $this->countWhere('subscriber_services', "status = 'SUSPENDED'"),
            'unpaid_invoices' => $this->countWhere('invoices', "status IN ('UNPAID','OVERDUE')"),
            'unpaid_subscribers' => $this->countUnpaidSubscribers(),
            'paid_invoices' => $this->countWhere('invoices', "status = 'PAID'"),
            'today_revenue' => $this->sumTodayRevenue(),
            'active_sessions' => $this->countWhere('radius_accounting', 'session_stop IS NULL'),
            'online_onts' => $this->countWhere('ont_acs', "status = 'ONLINE'"),
            'offline_onts' => $this->countWhere('ont_devices', "status = 'OFFLINE'"),
            'available_nap_ports' => $this->countWhere('splitter_output_ports', "status = 'AVAILABLE'"),
            'used_nap_ports' => $this->countWhere('splitter_output_ports', "status = 'USED'"),
            'provisioning_success' => $this->countWhere('service_provisioning_jobs', "job_status = 'SUCCESS'"),
            'provisioning_failed' => $this->countWhere('service_provisioning_jobs', "job_status = 'FAILED'"),
            'provisioning_in_progress' => $this->countWhere(
                'service_provisioning_jobs',
                "job_status IN ('VALIDATING','READY','PROVISIONING','VERIFYING')"
            ),
        ];
    }

    private function count(string $table): int
    {
        $sql = "SELECT COUNT(*) FROM `{$table}`";
        $stmt = $this->db->query($sql);
        return (int)$stmt->fetchColumn();
    }

    private function countWhere(string $table, string $where): int
    {
        $sql = "SELECT COUNT(*) FROM `{$table}` WHERE {$where}";
        $stmt = $this->db->query($sql);
        return (int)$stmt->fetchColumn();
    }

    private function countUnpaidSubscribers(): int
    {
        $stmt = $this->db->query(
            "SELECT COUNT(DISTINCT subscriber_id)
             FROM invoices
             WHERE subscriber_id IS NOT NULL
               AND status IN ('UNPAID', 'OVERDUE', 'PARTIAL')"
        );

        return (int)$stmt->fetchColumn();
    }

    private function sumTodayRevenue(): float
    {
        try {
            $stmt = $this->db->query("
                SELECT COALESCE(SUM(amount), 0)
                FROM `payments`
                WHERE DATE(payment_date) = CURDATE()
            ");
            return (float)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return 0.0;
        }
    }
}
