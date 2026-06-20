<?php

namespace App\Modules\StaffAttendance\Controllers;

use App\Modules\StaffAttendance\Repositories\StaffAttendanceRepository;
use App\Modules\StaffAttendance\Services\StaffAttendanceService;
use Framework\ApiController;
use Framework\DatabaseConnection;
use Framework\SessionManager;
use Throwable;

class StaffAttendanceApiController extends ApiController
{
    private StaffAttendanceService $service;

    public function __construct(DatabaseConnection $database)
    {
        $repo = new StaffAttendanceRepository($database);
        $this->service = new StaffAttendanceService($repo);
    }

    public function today(): void
    {
        try {
            $this->success(
                $this->service->today($this->sessionUser()),
                'Attendance loaded.'
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 403);
        }
    }

    public function timeIn(): void
    {
        try {
            $this->success(
                $this->service->timeIn($this->sessionUser(), $this->requestMeta()),
                'Time in successful.'
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 422);
        }
    }

    public function timeOut(): void
    {
        try {
            $this->success(
                $this->service->timeOut($this->sessionUser(), $this->requestMeta()),
                'Time out successful.'
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 422);
        }
    }

    public function status(): void
    {
        try {
            $this->success(
                $this->service->updateStatus($this->sessionUser(), $_POST),
                'Duty status updated.'
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 422);
        }
    }

    public function availableTechnicians(): void
    {
        try {
            $this->success(
                $this->service->availableTechnicians($this->sessionUser()),
                'Available technicians loaded.'
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 403);
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
            'role' => strtoupper((string)($user['role'] ?? '')),
        ];
    }

    private function requestMeta(): array
    {
        return [
            'ip' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
            'user_agent' => (string)($_SERVER['HTTP_USER_AGENT'] ?? ''),
        ];
    }
}