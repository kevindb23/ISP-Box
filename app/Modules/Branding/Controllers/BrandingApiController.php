<?php

namespace App\Modules\Branding\Controllers;

use App\Modules\Branding\Services\BrandingService;
use Framework\ApiController;
use Framework\SessionManager;
use Throwable;

class BrandingApiController extends ApiController
{
    private BrandingService $service;

    public function __construct(BrandingService $service)
    {
        $this->service = $service;
    }

    public function show(): void
    {
        try {
            $this->requireAdmin();
            $this->success($this->service->getBranding(), 'Branding loaded.');
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 403);
        }
    }

    public function update(): void
    {
        try {
            $this->requireAdmin();

            $input = $this->request()->input();

            $files = $this->request()->files();

            $result = $this->service->updateBranding($input, $files);

            $this->success($result['branding'], (string)$result['message']);
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 400);
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
