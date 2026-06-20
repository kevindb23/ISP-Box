<?php

namespace App\Modules\StaffAttendance\Controllers;

use Framework\Controller;
use Framework\SessionManager;

class StaffAttendanceController extends Controller
{
    public function index()
    {
        $this->requireStaff();

        return $this->view('StaffAttendance/index');
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
            header('Location: /logout');
            exit;
        }
    }
}