<?php

namespace App\Modules\SystemMaintenance\Controllers;

use App\Modules\SystemMaintenance\Services\SystemMaintenanceService;
use Framework\ApiController;
use Framework\SessionManager;
use Throwable;

class SystemMaintenanceApiController extends ApiController
{
    private SystemMaintenanceService $service;

    public function __construct(SystemMaintenanceService $service)
    {
        $this->service = $service;
    }

    public function index(): void
    {
        try {
            $this->requireAdmin();
            $this->success($this->service->summary(), 'System maintenance settings loaded.');
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 403);
        }
    }

    public function save(): void
    {
        try {
            $this->requireAdmin();
            $this->success(
                $this->service->saveSettings($this->request()->input()),
                'System maintenance settings saved.'
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 422);
        }
    }

    private function requireAdmin(): void
    {
        $user = SessionManager::user();

        if (!is_array($user) || empty($user['id'])) {
            throw new \Exception('You must be logged in.');
        }

        $role = strtoupper((string)($user['role'] ?? ''));

        if (!in_array($role, ['ADMINISTRATOR', 'SUPERADMIN'], true)) {
            throw new \Exception('Administrator access only.');
        }
    }
}
