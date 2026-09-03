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
