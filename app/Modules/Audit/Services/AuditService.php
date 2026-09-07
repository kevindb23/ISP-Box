<?php

namespace App\Modules\Audit\Services;

use App\Modules\Audit\DTOs\AuditEventDTO;
use App\Modules\Audit\DTOs\AuditFilterDTO;
use App\Modules\Audit\Entities\AuditLog;
use App\Modules\Audit\Repositories\AuditRepository;
use App\Modules\Audit\Validators\AuditEventValidator;
use Framework\SessionManager;

class AuditService
{
    private AuditRepository $repo;
    private AuditEventValidator $validator;

    public function __construct(
        AuditRepository $repo,
        AuditEventValidator $validator
    )
    {
        $this->repo = $repo;
        $this->validator = $validator;
    }

    public function latest(?AuditFilterDTO $filter = null): array
    {
        return array_map(
            static fn(array $row): array => (new AuditLog($row))->toArray(),
            $this->repo->latest(($filter ?? new AuditFilterDTO())->toArray())
        );
    }

    public function find(int $id): ?array
    {
        $row = $this->repo->find($id);
        return $row ? (new AuditLog($row))->toArray() : null;
    }

    public function securityNotifications(int $limit = 30): array
    {
        return array_map(
            static fn(array $row): array => (new AuditLog($row))->toArray(),
            $this->repo->latestSecurityNotifications($limit)
        );
    }

<<<<<<< HEAD
    public function notificationFeed(int $userId, int $limit = 30, string $role = ''): array
    {
        if (in_array(strtoupper(trim($role)), ['SUBSCRIBER', 'TECHNICIAN'], true)) {
            $items = array_map(static function (array $row): array {
                return [
                    // Maintenance IDs are deliberately namespaced above BIGINT
                    // audit IDs. Keep them as strings so JavaScript cannot round
                    // them past Number.MAX_SAFE_INTEGER before marking them read.
                    'id' => (string)($row['id'] ?? '0'),
                    'notification_type' => (string)($row['notification_type'] ?? 'MAINTENANCE'),
                    'title' => (string)($row['title'] ?? 'Maintenance notice'),
                    'description' => (string)($row['description'] ?? ''),
                    'starts_at' => $row['starts_at'] ?? null,
                    'ends_at' => $row['ends_at'] ?? null,
                    'created_at' => $row['created_at'] ?? null,
                    'is_read' => (bool)($row['is_read'] ?? false),
                ];
            }, $this->repo->maintenanceNotificationsForUser($userId, $limit));

            return [
                'items' => $items,
                'unread_count' => $this->repo->unreadMaintenanceNotificationCount($userId),
            ];
        }

        $items = array_map(static function (array $row): array {
            $item = (new AuditLog($row))->toArray();
            $item['notification_type'] = 'SECURITY';
            $item['title'] = 'Critical security event';
            $item['is_read'] = (bool)($row['is_read'] ?? false);
            return $item;
        }, $this->repo->securityNotificationsForUser($userId, $limit));

        return [
            'items' => $items,
            'unread_count' => $this->repo->unreadSecurityNotificationCount($userId),
        ];
    }

    public function markNotificationRead(int $notificationId, int $userId): void
    {
        $this->repo->markNotificationRead($notificationId, $userId);
    }

=======
>>>>>>> origin/main
    public function log(
        string $module,
        string $action,
        string $description = ''
    ): void {
        $user = $this->resolveCurrentUser();

        $this->logEvent(new AuditEventDTO(
            module: $module,
            action: $action,
            description: $description,
            userId: (int)($user['id'] ?? 0),
            username: (string)($user['username'] ?? 'SYSTEM'),
            actorRole: isset($user['role']) ? (string)$user['role'] : null,
            ipAddress: $_SERVER['REMOTE_ADDR'] ?? null
        ));
    }

    public function logEvent(AuditEventDTO $event): void
    {
        if ($event->userId === 0 && $event->username === 'SYSTEM') {
            $user = $this->resolveCurrentUser();
            $event->userId = (int)($user['id'] ?? 0);
            $event->username = (string)($user['username'] ?? 'SYSTEM');
            $event->actorRole ??= isset($user['role']) ? (string)$user['role'] : null;
        }

        $event->ipAddress ??= $_SERVER['REMOTE_ADDR'] ?? null;
        $this->validator->validate($event);
        $this->repo->create($event->toArray());
    }

    private function resolveCurrentUser(): array
    {
        $user = SessionManager::user();

        if (is_array($user) && !empty($user['username'])) {
            return $user;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        $sessionKeys = [
            'user',
            'auth_user',
            'current_user',
        ];

        foreach ($sessionKeys as $key) {
            if (!empty($_SESSION[$key]) && is_array($_SESSION[$key])) {
                return $_SESSION[$key];
            }
        }

        return [
            'id' => $_SESSION['user_id'] ?? $_SESSION['auth_user_id'] ?? 0,
            'username' => $_SESSION['username'] ?? $_SESSION['auth_username'] ?? 'SYSTEM',
        ];
    }
}
