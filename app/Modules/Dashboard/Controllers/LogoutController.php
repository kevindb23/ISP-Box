<?php

namespace App\Modules\Dashboard\Controllers;

use App\Modules\Audit\Services\AuditService;
use Framework\SessionManager;

class LogoutController
{
    private AuditService $audit;

    public function __construct(AuditService $audit)
    {
        $this->audit = $audit;
    }

    public function logout()
    {
        $user = SessionManager::user();

        if ($user) {
            $this->audit->log(
                'USERS',
                'LOGOUT',
                "User {$user['username']} logged out"
            );
        }

        SessionManager::destroy();

        header("Location: /login");
        exit;
    }
}