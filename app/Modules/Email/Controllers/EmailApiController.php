<?php

namespace App\Modules\Email\Controllers;

use App\Modules\Email\Services\EmailService;
use Framework\ApiController;
use Framework\SessionManager;
use Throwable;

class EmailApiController extends ApiController
{
    private EmailService $service;

    public function __construct(EmailService $service)
    {
        $this->service = $service;
    }

    public function index(): void
    {
        try {
            $this->requireAdmin();
            $this->success($this->service->summary(), 'Email settings loaded.');
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 403);
        }
    }

    public function save(): void
    {
        try { $this->requireAdmin(); $this->success($this->service->save($this->request()->input()), 'Email settings saved.'); }
        catch (Throwable $e) { $this->error($e->getMessage(), 422); }
    }

    public function test(): void
    {
        try { $this->requireAdmin(); $this->success($this->service->testConnection(), 'SMTP connection succeeded.'); }
        catch (Throwable $e) { $this->error($e->getMessage(), 422); }
    }

    public function testEmail(): void
    {
        try {
            $this->requireAdmin();
            $recipient = (string)($this->request()->input()['recipient'] ?? '');
            $this->success($this->service->sendTestEmail($recipient), 'Test email sent successfully.');
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
