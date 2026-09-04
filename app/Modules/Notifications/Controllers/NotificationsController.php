<?php

namespace App\Modules\Notifications\Controllers;

use Framework\Controller;
use Framework\SessionManager;

class NotificationsController extends Controller
{
    public function index()
    {
        $this->requireAdmin();

        return $this->view('Notifications/index');
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
