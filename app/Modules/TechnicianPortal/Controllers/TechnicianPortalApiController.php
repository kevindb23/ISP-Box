<?php

namespace App\Modules\TechnicianPortal\Controllers;

use App\Modules\TechnicianPortal\DTOs\TechnicianPortalCommandDTO;
use App\Modules\TechnicianPortal\Services\TechnicianPortalService;
use App\Modules\TechnicianPortal\Validators\TechnicianPortalCommandValidator;
use Framework\ApiController;
use Framework\SessionManager;
use Throwable;

class TechnicianPortalApiController extends ApiController
{
    private TechnicianPortalService $service;

    public function __construct(TechnicianPortalService $service, private TechnicianPortalCommandValidator $validator)
    {
        $this->service = $service;
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
            $result = $this->service->checkIn($this->sessionUser(), $this->command('check_in'));

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
            $result = $this->service->startWork($this->sessionUser(), $this->command('start'));

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
            $result = $this->service->completeWork($this->sessionUser(), $this->command('complete'));

            $this->success(
                $result,
                $result['message'] ?? 'Work completed successfully.'
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 422);
        }
    }

    public function completeTask(): void
    {
        try {
            $result = $this->service->completeTask($this->sessionUser(), $this->command('complete_task'));

            $this->success(
                $result,
                $result['message'] ?? 'Task marked as completed.'
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 422);
        }
    }

    public function addNote(): void
    {
        try {
            $result = $this->service->addNote($this->sessionUser(), $this->command('note'));

            $this->success(
                $result,
                $result['message'] ?? 'Note added successfully.'
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 422);
        }
    }

    public function uploadPhoto(): void
    {
        try {
            $result = $this->service->uploadPhoto($this->sessionUser(), $this->command('upload'), $this->request()->files());

            $this->success(
                $result,
                $result['message'] ?? 'Photo uploaded successfully.'
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 422);
        }
    }

    public function downloadPhoto($id): void
    {
        try {
            $file = $this->service->downloadPhoto($this->sessionUser(), (int)$id);
            header('Content-Type: ' . $file['mime']);
            header('Content-Length: ' . $file['size']);
            header('Content-Disposition: inline; filename="' . addcslashes($file['name'], "\"\\") . '"');
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: private, no-store');
            readfile($file['path']);
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 404);
        }
    }

    public function deletePhoto(): void
    {
        try {
            $result = $this->service->deletePhoto($this->sessionUser(), $this->command('delete_photo'));

            $this->success(
                $result,
                $result['message'] ?? 'Photo deleted successfully.'
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 422);
        }
    }

    public function timeIn(): void
    {
        try {
            $result = $this->service->timeIn($this->sessionUser(), $this->command('time_in'));

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
            $result = $this->service->timeOut($this->sessionUser(), $this->command('time_out'));

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
            $result = $this->service->updateAttendanceStatus($this->sessionUser(), $this->command('attendance_status'));

            $this->success(
                $result,
                $result['message'] ?? 'Attendance status updated.'
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 422);
        }
    }

    private function command(string $action): array
    {
        $dto = new TechnicianPortalCommandDTO($this->request()->input());
        $errors = $this->validator->validate($dto, $action);
        if ($errors !== []) throw new \InvalidArgumentException((string)reset($errors));
        return $dto->toArray();
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
