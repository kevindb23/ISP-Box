<?php

namespace App\Modules\TechnicianPortal\Controllers;

use Framework\Controller;
use Framework\SessionManager;

class TechnicianPortalController extends Controller
{
    public function index()
    {
        $this->requireTechnician();

        return $this->view('TechnicianPortal/index');
    }

    public function workOrders()
    {
        $this->requireTechnician();

        // The work-order list and detail modal are rendered by the shared
        // technician portal screen. There is no separate work-orders view;
        // resolving the old path caused an uncaught view-not-found exception
        // and HTTP 500 on the dedicated route.
        return $this->view('TechnicianPortal/index');
    }

    public function workOrderDetails($id)
    {
        $this->requireTechnician();

        return $this->view('TechnicianPortal/index');
    }

    private function requireTechnician(): void
    {
        $user = SessionManager::user();

        $userId = (int)($user['id'] ?? 0);
        $role = strtoupper((string)($user['role'] ?? ''));

        if ($userId <= 0) {
            header('Location: /login');
            exit;
        }

        if ($role !== 'TECHNICIAN' && $role !== 'SUPERADMIN') {
            http_response_code(403);
            echo 'Technician access only.';
            exit;
        }
    }
}
