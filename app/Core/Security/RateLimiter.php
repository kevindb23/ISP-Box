<?php

namespace App\Core\Security;

use App\Infrastructure\Database\DatabaseConnection;
use PDO;

class RateLimiter
{

    private const MAX_REQUESTS = 10;
    private const WINDOW = 60; // seconds


    public static function check($ip)
    {

        $db = (new DatabaseConnection())->get();

        $stmt = $db->prepare("
            SELECT *
            FROM rate_limits
            WHERE ip_address = :ip
            LIMIT 1
        ");

        $stmt->execute([
            'ip' => $ip
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);


        if (!$row) {

            $stmt = $db->prepare("
                INSERT INTO rate_limits (ip_address, requests, last_request)
                VALUES (:ip, 1, NOW())
            ");

            $stmt->execute([
                'ip' => $ip
            ]);

            return true;

        }


        $lastRequest = strtotime($row['last_request']);

        if (time() - $lastRequest > self::WINDOW) {

            $stmt = $db->prepare("
                UPDATE rate_limits
                SET requests = 1,
                    last_request = NOW()
                WHERE ip_address = :ip
            ");

            $stmt->execute([
                'ip' => $ip
            ]);

            return true;

        }


        if ($row['requests'] >= self::MAX_REQUESTS) {

            return false;

        }


        $stmt = $db->prepare("
            UPDATE rate_limits
            SET requests = requests + 1,
                last_request = NOW()
            WHERE ip_address = :ip
        ");

        $stmt->execute([
            'ip' => $ip
        ]);

        return true;

    }

}
