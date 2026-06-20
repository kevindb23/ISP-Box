<?php

namespace App\Modules\Audit\Services;

use App\Modules\Audit\Repositories\AuditRepository;
use Framework\SessionManager;

class AuditService
{
    private AuditRepository $repo;

    public function __construct(AuditRepository $repo)
    {
        $this->repo = $repo;
    }

    public function latest(): array
    {
        return $this->repo->latest();
    }

    public function find(int $id): ?array
    {
        return $this->repo->find($id);
    }

    public function log(
        string $module,
        string $action,
        string $description = ''
    ): void {
        $user = $this->resolveCurrentUser();

        $this->repo->create([
            'user_id'     => (int)($user['id'] ?? 0),
            'username'    => (string)($user['username'] ?? 'SYSTEM'),
            'module'      => strtoupper($module),
            'action'      => strtoupper($action),
            'description' => $description,
            'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
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