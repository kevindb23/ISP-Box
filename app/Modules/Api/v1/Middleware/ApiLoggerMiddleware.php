<?php

namespace App\Modules\Api\v1\Middleware;

use App\Infrastructure\Database\DatabaseConnection;
use PDO;

class ApiLoggerMiddleware
{
    private PDO $db;

    public function __construct(DatabaseConnection $connection)
    {
        $this->db = $connection->get();
    }

    public function handle(): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO api_logs(endpoint,method,ip_address,status,created_at)
             VALUES (:endpoint,:method,:ip,NULL,NOW())"
        );

        $stmt->execute([
            "endpoint" => (string)($_SERVER['REQUEST_URI'] ?? ''),
            "method" => (string)($_SERVER['REQUEST_METHOD'] ?? ''),
            "ip" => (string)($_SERVER['REMOTE_ADDR'] ?? '')
        ]);

        $logId = (int)$this->db->lastInsertId();
        $db = $this->db;
        register_shutdown_function(static function () use ($db, $logId): void {
            try {
                $status = http_response_code();
                if (!is_int($status) || $status < 100) $status = 200;
                $update = $db->prepare('UPDATE api_logs SET status = :status WHERE id = :id');
                $update->execute(['status' => $status, 'id' => $logId]);
            } catch (\Throwable $e) {
                error_log('[API logger] Unable to finalize response status: ' . $e->getMessage());
            }
        });
    }
}
