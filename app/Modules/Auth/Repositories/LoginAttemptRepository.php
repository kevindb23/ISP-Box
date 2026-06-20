<?php

namespace App\Modules\Auth\Repositories;

use App\Infrastructure\Database\DatabaseConnection;
use PDO;

class LoginAttemptRepository
{

    private PDO $db;

    public function __construct(DatabaseConnection $connection)
    {
        $this->db = $connection->get();
    }

    public function get($username)
    {

        $stmt = $this->db->prepare("
            SELECT *
            FROM login_attempts
            WHERE username = :username
            LIMIT 1
        ");

        $stmt->execute([
            'username' => $username
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC);

    }

    public function recordFailure($username, $ip)
    {

        $attempt = $this->get($username);

        if (!$attempt) {

            $stmt = $this->db->prepare("
                INSERT INTO login_attempts (username, ip_address, attempts)
                VALUES (:username, :ip, 1)
            ");

            $stmt->execute([
                'username' => $username,
                'ip' => $ip
            ]);

            return;
        }

        $attempts = $attempt['attempts'] + 1;

        $lockedUntil = null;

        if ($attempts >= 5) {
            $lockedUntil = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        }

        $stmt = $this->db->prepare("
            UPDATE login_attempts
            SET attempts = :attempts,
                ip_address = :ip,
                locked_until = :locked
            WHERE username = :username
        ");

        $stmt->execute([
            'attempts' => $attempts,
            'ip' => $ip,
            'locked' => $lockedUntil,
            'username' => $username
        ]);

    }

    public function clear($username)
    {

        $stmt = $this->db->prepare("
            DELETE FROM login_attempts
            WHERE username = :username
        ");

        $stmt->execute([
            'username' => $username
        ]);

    }

    public function isLocked($username)
    {

        $attempt = $this->get($username);

        if (!$attempt) {
            return false;
        }

        if (!$attempt['locked_until']) {
            return false;
        }

        return strtotime($attempt['locked_until']) > time();

    }

}
