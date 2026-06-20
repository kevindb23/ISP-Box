<?php

namespace App\Modules\TechnicianPortal\Controllers;

use App\Modules\TechnicianPortal\Repositories\TechnicianPortalRepository;
use App\Modules\TechnicianPortal\Services\TechnicianPortalService;
use Framework\ApiController;
use Framework\DatabaseConnection;
use Framework\SessionManager;
use Throwable;

class TechnicianPortalApiController extends ApiController
{
    private TechnicianPortalService $service;

    public function __construct(DatabaseConnection $database)
    {
        $repo = new TechnicianPortalRepository($database);
        $this->service = new TechnicianPortalService($repo);
    }

    public function dashboard(): void
    {
        try {
            $this->success(
                $this->service->dashboard($this->sessionUser()),
                'Technician dashboard loaded.'
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 403);
        }
    }

    public function workOrders(): void
    {
        try {
            $this->success(
                $this->service->workOrders($this->sessionUser()),
                'Assigned work orders loaded.'
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 403);
        }
    }

    public function showWorkOrder($id): void
    {
        try {
            $this->success(
                $this->service->workOrderDetails($this->sessionUser(), (int)$id),
                'Work order loaded.'
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 403);
        }
    }

    public function checkIn(): void
    {
        try {
            $result = $this->service->checkIn($this->sessionUser(), $_POST);

            $this->success(
                $result,
                $result['message'] ?? 'Checked in successfully.'
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 422);
        }
    }

    public function startWork(): void
    {
        try {
            $result = $this->service->startWork($this->sessionUser(), $_POST);

            $this->success(
                $result,
                $result['message'] ?? 'Work started successfully.'
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 422);
        }
    }

    public function completeWork(): void
    {
        try {
            $result = $this->service->completeWork($this->sessionUser(), $_POST);

            $this->success(
                $result,
                $result['message'] ?? 'Work completed successfully.'
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 422);
        }
    }

    public function addNote(): void
    {
        try {
            $result = $this->service->addNote($this->sessionUser(), $_POST);

            $this->success(
                $result,
                $result['message'] ?? 'Note added successfully.'
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 422);
        }
    }

    public function timeIn(): void
    {
        try {
            $result = $this->service->timeIn($this->sessionUser(), $_POST);

            $this->success(
                $result,
                $result['message'] ?? 'Timed in successfully.'
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 422);
        }
    }

    public function timeOut(): void
    {
        try {
            $result = $this->service->timeOut($this->sessionUser(), $_POST);

            $this->success(
                $result,
                $result['message'] ?? 'Timed out successfully.'
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 422);
        }
    }

    public function updateAttendanceStatus(): void
    {
        try {
            $result = $this->service->updateAttendanceStatus($this->sessionUser(), $_POST);

            $this->success(
                $result,
                $result['message'] ?? 'Attendance status updated.'
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 422);
        }
    }

    private function sessionUser(): array
    {
        $user = SessionManager::user();

        if (!is_array($user)) {
            $user = [];
        }

        return [
            'id' => (int)($user['id'] ?? 0),
            'user_id' => (int)($user['id'] ?? 0),
            'username' => (string)($user['username'] ?? ''),
            'full_name' => (string)($user['full_name'] ?? ''),
            'role' => strtoupper((string)($user['role'] ?? '')),
        ];
    }
}