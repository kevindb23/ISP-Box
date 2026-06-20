<?php

namespace App\Modules\Dashboard\Controllers;

use Framework\Controller;
use Framework\SessionManager;

class DashboardController extends Controller
{
    public function index()
    {
        $user = SessionManager::user();
        $role = strtoupper((string)($user['role'] ?? ''));

        /*
         * Subscribers should not access the admin dashboard.
         * Their landing page is the subscriber portal.
         */
        if ($role === 'SUBSCRIBER') {
            header('Location: /subscriber-portal');
            exit;
        }

        return $this->view(
            'Dashboard/index',
            [],
            'app'
        );
    }
}