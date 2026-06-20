<?php

namespace App\Modules\Auth\Services;

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

    public function login($username, $password)
    {
        $ip = $_SERVER['REMOTE_ADDR'];

        if ($this->attempts->isLocked($username)) {
            return false;
        }

        $admin = $this->users->findByUsername($username);

        if (!$admin || !password_verify($password, $admin['password'])) {
            $this->attempts->recordFailure($username, $ip);
            return false;
        }

        $this->attempts->clear($username);

        $this->users->updateLastLogin((int)$admin['id']);
        $admin['last_login'] = date('Y-m-d H:i:s');

        SessionManager::login($admin);

        $this->audit->log(
            'USERS',
            'LOGIN',
            "User {$admin['username']} logged in"
        );

        unset($_SESSION['csrf_token']);

        return true;
    }
}