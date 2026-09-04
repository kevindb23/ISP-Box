<?php

namespace App\Modules\SystemMaintenance\Controllers;

use Framework\Controller;
use Framework\SessionManager;

class SystemMaintenanceController extends Controller
{
    public function index()
    {
        $this->requireAdmin();

        return $this->view('SystemMaintenance/index');
    }

    private function requireAdmin(): void
    {
        $user = SessionManager::user();

        if (!is_array($user) || empty($user['id'])) {
            header('Location: /login');
            exit;
        }

        $role = strtoupper((string)($user['role'] ?? ''));

        if (!in_array($role, ['ADMINISTRATOR', 'SUPERADMIN'], true)) {
            header('Location: /dashboard');
            exit;
        }
    }
}
