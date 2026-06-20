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

    public function handle()
    {
        $stmt = $this->db->prepare(
            "INSERT INTO api_logs(endpoint,method,ip_address,status,created_at)
             VALUES (:endpoint,:method,:ip,200,NOW())"
        );

        $stmt->execute([
            "endpoint"=>$_SERVER['REQUEST_URI'],
            "method"=>$_SERVER['REQUEST_METHOD'],
            "ip"=>$_SERVER['REMOTE_ADDR']
        ]);
    }
}
