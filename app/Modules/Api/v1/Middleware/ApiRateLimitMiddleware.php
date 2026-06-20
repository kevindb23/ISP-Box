<?php

namespace App\Modules\Api\v1\Middleware;

use App\Infrastructure\Database\DatabaseConnection;
use PDO;

class ApiRateLimitMiddleware
{
    private const LIMIT = 60;
    private const WINDOW = 60;

    private PDO $db;

    public function __construct(DatabaseConnection $connection)
    {
        $this->db = $connection->get();
    }

    public function handle()
    {
        $ip = $_SERVER['REMOTE_ADDR'];

        $stmt = $this->db->prepare(
            "SELECT * FROM api_rate_limits WHERE ip_address = :ip LIMIT 1"
        );

        $stmt->execute(["ip" => $ip]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {

            $stmt = $this->db->prepare(
                "INSERT INTO api_rate_limits (ip_address,requests,last_request)
                 VALUES (:ip,1,NOW())"
            );

            $stmt->execute(["ip"=>$ip]);
            return;

        }

        $last = strtotime($row['last_request']);

        if (time() - $last > self::WINDOW) {

            $stmt = $this->db->prepare(
                "UPDATE api_rate_limits
                 SET requests = 1, last_request = NOW()
                 WHERE ip_address = :ip"
            );

            $stmt->execute(["ip"=>$ip]);
            return;

        }

        if ($row['requests'] >= self::LIMIT) {

            http_response_code(429);

            echo json_encode([
                "success"=>false,
                "error"=>"Too many API requests"
            ]);

            exit;
        }

        $stmt = $this->db->prepare(
            "UPDATE api_rate_limits
             SET requests = requests + 1,
                 last_request = NOW()
             WHERE ip_address = :ip"
        );

        $stmt->execute(["ip"=>$ip]);
    }
}
