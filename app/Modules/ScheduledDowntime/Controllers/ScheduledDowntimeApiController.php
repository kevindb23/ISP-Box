<?php

namespace App\Modules\ScheduledDowntime\Controllers;

use App\Modules\ScheduledDowntime\Services\ScheduledDowntimeService;
use Framework\ApiController;
use Framework\SessionManager;
use Throwable;

class ScheduledDowntimeApiController extends ApiController
{
    private ScheduledDowntimeService $service;

    public function __construct(ScheduledDowntimeService $service)
    {
        $this->service = $service;
    }

    public function index(): void
    {
        try {
            $this->requireAdmin();
            $this->success(['items' => $this->service->list()], 'Scheduled downtime loaded.');
        } catch (Throwable $e) {
            $this->error($e->getMessage(), $this->errorStatus($e, 500));
        }
    }

    public function store(): void
    {
        try {
            $user = $this->requireAdmin();
            $this->success(
                $this->service->create($this->request()->input(), (int)$user['id']),
                'Scheduled downtime created.',
                201
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), $this->errorStatus($e, 422));
        }
    }

    public function update($id): void
    {
        try {
            $this->requireAdmin();
            $this->success($this->service->update((int)$id, $this->request()->input()), 'Scheduled downtime updated.');
        } catch (Throwable $e) {
            $this->error($e->getMessage(), $this->errorStatus($e, 422));
        }
    }

    public function delete($id): void
    {
        try {
            $this->requireAdmin();
            $this->service->delete((int)$id);
            $this->success([], 'Scheduled downtime deleted.');
        } catch (Throwable $e) {
            $this->error($e->getMessage(), $this->errorStatus($e, 422));
        }
    }

    public function toggle($id): void
    {
        try {
            $this->requireAdmin();
            $enabled = filter_var($this->request()->value('enabled', null), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($enabled === null) {
                throw new \InvalidArgumentException('Enabled must be a boolean value.');
            }
            $this->success($this->service->toggle((int)$id, $enabled), 'Scheduled downtime status updated.');
        } catch (Throwable $e) {
            $this->error($e->getMessage(), $this->errorStatus($e, 422));
        }
    }

    private function requireAdmin(): array
    {
        $user = SessionManager::user();

        if (!is_array($user) || empty($user['id'])) {
            throw new \Exception('You must be logged in.');
        }

        $role = strtoupper((string)($user['role'] ?? ''));

        if (!in_array($role, ['ADMINISTRATOR', 'SUPERADMIN'], true)) {
            throw new \Exception('Administrator access only.');
        }

        return $user;
    }

    private function errorStatus(Throwable $e, int $fallback): int
    {
        return match ($e->getMessage()) {
            'You must be logged in.' => 401,
            'Administrator access only.' => 403,
            default => $fallback,
        };
    }
}
