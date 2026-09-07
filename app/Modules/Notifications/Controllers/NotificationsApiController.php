<?php

namespace App\Modules\Notifications\Controllers;

use App\Modules\Notifications\DTOs\CreateNotificationsDTO;
use App\Modules\Notifications\Services\NotificationsService;
use App\Modules\Notifications\Validators\CreateNotificationsValidator;
use Framework\ApiController;
use Framework\SessionManager;
use Throwable;

class NotificationsApiController extends ApiController
{
    private NotificationsService $service;

    public function __construct(NotificationsService $service, private CreateNotificationsValidator $validator)
    {
        $this->service = $service;
    }

    public function index(): void
    {
        try {
            $this->requireAdmin();
            $this->success($this->service->summary(), 'Notification settings loaded.');
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 403);
        }
    }

    public function save(): void
    {
        try {
            $this->requireAdmin();
            $input = CreateNotificationsDTO::fromArray($this->request()->input())->toArray();
            $errors = $this->validator->validate($input);
            if ($errors !== []) {
                $this->error('Please correct the highlighted fields.', 422, $errors);
                return;
            }
            $this->success($this->service->save($input), 'Notification settings saved.');
        }
        catch (Throwable $e) { $this->error($e->getMessage(), 422); }
    }

    public function test(): void
    {
        try { $this->requireAdmin(); $this->success($this->service->testTelegram(), 'Telegram test notification sent.'); }
        catch (Throwable $e) { $this->error($e->getMessage(), 422); }
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
