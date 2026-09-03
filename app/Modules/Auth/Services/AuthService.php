<?php

namespace App\Modules\Auth\Services;

use App\Modules\Audit\DTOs\AuditEventDTO;
use App\Modules\Auth\DTOs\LoginDTO;
use App\Modules\Auth\Repositories\AdminRepository;
use App\Modules\Auth\Repositories\LoginAttemptRepository;
use App\Modules\Audit\Services\AuditService;
use Framework\SessionManager;

class AuthService
{
    private AdminRepository $users;
    private LoginAttemptRepository $attempts;
    private AuditService $audit;

    public function __construct(
        AdminRepository $users,
        LoginAttemptRepository $attempts,
        AuditService $audit
    ) {
        $this->users = $users;
        $this->attempts = $attempts;
        $this->audit = $audit;
    }

    public function login(LoginDTO $credentials): bool
    {
        $username = $credentials->username;
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        if ($this->attempts->isLocked($username)) {
            $this->auditAuthentication($username, 'LOGIN_LOCKED', 'DENIED', 'Login denied because the account is temporarily locked.');
            return false;
        }

        $admin = $this->users->findByUsername($username);

        if (!$admin || !$admin->isActive() || !$admin->passwordMatches($credentials->password)) {
            $this->attempts->recordFailure($username, $ip);
            $this->auditAuthentication($username, 'LOGIN_FAILED', 'FAILED', 'Login failed because the supplied credentials were invalid.');
            return false;
        }

        $this->attempts->clear($username);

        $this->users->updateLastLogin($admin->id());
        $sessionUser = $admin->toSessionArray();
        $sessionUser['last_login'] = date('Y-m-d H:i:s');

        SessionManager::login($sessionUser);

        $this->audit->log(
            'USERS',
            'LOGIN',
            "User {$admin->username()} logged in"
        );

        unset($_SESSION['csrf_token']);

        return true;
    }

    private function auditAuthentication(
        string $username,
        string $action,
        string $result,
        string $description
    ): void {
        $this->audit->logEvent(new AuditEventDTO(
            module: 'AUTH',
            action: $action,
            description: $description,
            username: $username !== '' ? $username : 'UNKNOWN',
            ipAddress: $_SERVER['REMOTE_ADDR'] ?? null,
            objectType: 'USER_ACCOUNT',
            result: $result,
            source: 'WEB',
            httpMethod: 'POST',
            route: '/login'
        ));
    }
}
