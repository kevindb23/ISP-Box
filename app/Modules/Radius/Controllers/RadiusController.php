<?php

namespace App\Modules\Radius\Controllers;

use Framework\Controller;
use Framework\SessionManager;
use App\Modules\Radius\Services\RadiusService;
use Throwable;

class RadiusController extends Controller
{
    public function __construct(private RadiusService $service)
    {
    }

    public function index()
    {
        $this->requireNetworkAccess();

        try {
            $settings = $this->service->list();
        } catch (Throwable $e) {
            $settings = [];
        }

        return $this->view('Radius/index', [
            'title' => 'RADIUS',
            'settings' => $settings,
        ]);
    }

    public static function assertNetworkAccess(): void
    {
        $user = SessionManager::user();

        if (!is_array($user) || empty($user['id'])) {
            http_response_code(401);
            exit;
        }

        $role = strtoupper((string)($user['role'] ?? ''));
        if (!in_array($role, ['ADMIN', 'ADMINISTRATOR', 'SUPERADMIN', 'NOC'], true)) {
            http_response_code(403);
            exit;
        }
    }

    private function requireNetworkAccess(): void
    {
        $user = SessionManager::user();
        if (!is_array($user) || empty($user['id'])) {
            header('Location: /login');
            exit;
        }

        $role = strtoupper((string)($user['role'] ?? ''));

        if (!in_array($role, ['ADMIN', 'ADMINISTRATOR', 'SUPERADMIN', 'NOC'], true)) {
            http_response_code(403);
            echo 'Network access only.';
            exit;
        }
    }
}
