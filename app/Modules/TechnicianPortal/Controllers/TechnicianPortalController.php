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

        return $this->view('TechnicianPortal/work-orders');
    }

    public function workOrderDetails($id)
    {
        $this->requireTechnician();

        return $this->view('TechnicianPortal/work-order-details');
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