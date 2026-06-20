<?php

namespace App\Modules\Tickets\Controllers;

use Framework\Controller;
use Framework\SessionManager;

class TicketsController extends Controller
{
    public function index()
    {
        $this->requireStaff();

        return $this->view('Tickets/index');
    }

    private function requireStaff(): void
    {
        $user = SessionManager::user();

        if (!is_array($user) || empty($user['id'])) {
            header('Location: /login');
            exit;
        }

        $role = strtoupper((string)($user['role'] ?? ''));

        if ($role === 'SUBSCRIBER') {
            header('Location: /subscriber-portal');
            exit;
        }
    }
}