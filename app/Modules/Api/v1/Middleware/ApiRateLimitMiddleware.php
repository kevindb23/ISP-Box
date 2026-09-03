<?php

namespace App\Modules\Api\v1\Middleware;

use App\Infrastructure\Database\DatabaseConnection;
use PDO;
use RuntimeException;
use Throwable;

final class ApiRateLimitMiddleware
{
    private const LIMIT = 60;
    private const WINDOW = 60;
    private PDO $db;

    public function __construct(DatabaseConnection $connection) { $this->db = $connection->get(); }

    public function handle(): void
    {
        $key = $this->clientKey();
        $lockName = 'nexusbox-rate-' . hash('sha256', $key);
        $locked = false;
        try {
            $lock = $this->db->prepare('SELECT GET_LOCK(:name, 2)');
            $lock->execute(['name' => $lockName]);
            $locked = (int)$lock->fetchColumn() === 1;
            if (!$locked) throw new RuntimeException('Rate limiter is busy.');

            $stmt = $this->db->prepare('SELECT id, requests, last_request FROM api_rate_limits WHERE ip_address = :key ORDER BY id ASC LIMIT 1');
            $stmt->execute(['key' => $key]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $now = time();
            if (!$row) {
                $insert = $this->db->prepare('INSERT INTO api_rate_limits (ip_address, requests, last_request) VALUES (:key, 1, NOW())');
                $insert->execute(['key' => $key]);
                return;
            }

            $windowStarted = strtotime((string)$row['last_request']) ?: 0;
            if (($now - $windowStarted) >= self::WINDOW) {
                $reset = $this->db->prepare('UPDATE api_rate_limits SET requests = 1, last_request = NOW() WHERE id = :id');
                $reset->execute(['id' => (int)$row['id']]);
                return;
            }

            if ((int)$row['requests'] >= self::LIMIT) {
                $this->release($lockName);
                $locked = false;
                header('Content-Type: application/json; charset=utf-8');
                header('Retry-After: ' . max(1, self::WINDOW - ($now - $windowStarted)));
                http_response_code(429);
                echo json_encode(['ok' => false, 'success' => false, 'message' => 'Too many API requests.']);
                exit;
            }

            $increment = $this->db->prepare('UPDATE api_rate_limits SET requests = requests + 1 WHERE id = :id');
            $increment->execute(['id' => (int)$row['id']]);
        } catch (Throwable $e) {
            error_log('[API rate limit] ' . $e->getMessage());
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(503);
            echo json_encode(['ok' => false, 'success' => false, 'message' => 'API protection is temporarily unavailable.']);
            exit;
        } finally {
            if ($locked) $this->release($lockName);
        }
    }

    private function clientKey(): string
    {
        $authorization = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? '');
        if (preg_match('/^Bearer\s+(.+)$/i', trim($authorization), $matches)) {
            return 'token:' . substr(hash('sha256', trim($matches[1])), 0, 39);
        }
        return substr('ip:' . (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 45);
    }

    private function release(string $lockName): void
    {
        try {
            $stmt = $this->db->prepare('SELECT RELEASE_LOCK(:name)');
            $stmt->execute(['name' => $lockName]);
        } catch (Throwable $e) {
            error_log('[API rate limit] Unable to release lock: ' . $e->getMessage());
        }
    }
}
