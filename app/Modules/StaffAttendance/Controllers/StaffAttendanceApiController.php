<?php

namespace App\Modules\StaffAttendance\Controllers;

use App\Modules\StaffAttendance\DTOs\AttendanceCommandDTO;
use App\Modules\StaffAttendance\Services\StaffAttendanceService;
use Framework\ApiController;
use Framework\SessionManager;
use Throwable;

class StaffAttendanceApiController extends ApiController
{
    private StaffAttendanceService $service;

    public function __construct(StaffAttendanceService $service)
    {
        $this->service = $service;
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

    public function history(): void
    {
        try { $this->success($this->service->history($this->sessionUser(),$this->request()->query()),'Attendance history loaded.'); }
        catch (Throwable $e) { $this->error($e->getMessage(),str_contains(strtolower($e->getMessage()),'restricted')?403:422); }
    }

    public function timeIn(): void
    {
        try {
            $this->success(
                $this->service->timeIn($this->sessionUser(), AttendanceCommandDTO::fromRequest()),
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
                $this->service->timeOut($this->sessionUser(), AttendanceCommandDTO::fromRequest()),
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
                $this->service->updateStatus(
                    $this->sessionUser(),
                    AttendanceCommandDTO::fromRequest($this->request()->input())
                ),
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

}
