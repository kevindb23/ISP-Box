
<?php

namespace App\Modules\TechnicianPortal\Services;

use App\Modules\TechnicianPortal\Repositories\TechnicianPortalRepository;
use Exception;

class TechnicianPortalService
{
    private TechnicianPortalRepository $repo;

    public function __construct(TechnicianPortalRepository $repo)
    {
        $this->repo = $repo;
    }

    public function dashboard(array $sessionData): array
    {
        $userId = $this->requireTechnician($sessionData);

        return [
            'attendance' => $this->repo->getTodayAttendance($userId),
            'summary' => $this->repo->getWorkOrderSummary($userId),
            'work_orders' => $this->repo->getAssignedWorkOrders($userId, [
                'limit' => 10,
                'offset' => 0,
            ]),
        ];
    }

    public function workOrders(array $sessionData): array
    {
        $userId = $this->requireTechnician($sessionData);

        $filters = [
            'status' => $_GET['status'] ?? null,
            'limit' => $_GET['limit'] ?? 100,
            'offset' => $_GET['offset'] ?? 0,
        ];

        return [
            'items' => $this->repo->getAssignedWorkOrders($userId, $filters),
            'total' => $this->repo->countAssignedWorkOrders($userId, $filters),
            'summary' => $this->repo->getWorkOrderSummary($userId),
        ];
    }

    public function workOrderDetails(array $sessionData, int $workOrderId): array
    {
        $userId = $this->requireTechnician($sessionData);

        if ($workOrderId <= 0) {
            throw new Exception('Invalid work order ID.');
        }

        $workOrder = $this->repo->findAssignedWorkOrder($workOrderId, $userId);

        if (!$workOrder) {
            throw new Exception('Work order not found.');
        }

        return [
            'work_order' => $workOrder,
            'notes' => $this->repo->getWorkOrderNotes($workOrderId),
        ];
    }

    public function checkIn(array $sessionData, array $input): array
    {
        $userId = $this->requireTechnician($sessionData);

        $workOrderId = (int)($input['work_order_id'] ?? 0);
        $latitude = trim((string)($input['latitude'] ?? ''));
        $longitude = trim((string)($input['longitude'] ?? ''));

        if ($workOrderId <= 0) {
            throw new Exception('Invalid work order ID.');
        }

        if (!$this->repo->findAssignedWorkOrder($workOrderId, $userId)) {
            throw new Exception('Work order not found.');
        }

        $this->repo->checkInWorkOrder($workOrderId, $latitude ?: null, $longitude ?: null);

        $this->repo->createWorkOrderNote([
            'work_order_id' => $workOrderId,
            'user_id' => $userId,
            'note' => 'Technician checked in on site.',
            'note_type' => 'CHECK_IN',
        ]);

        return [
            'message' => 'Checked in successfully.',
            'work_order_id' => $workOrderId,
        ];
    }

    public function startWork(array $sessionData, array $input): array
    {
        $userId = $this->requireTechnician($sessionData);

        $workOrderId = (int)($input['work_order_id'] ?? 0);

        if ($workOrderId <= 0) {
            throw new Exception('Invalid work order ID.');
        }

        if (!$this->repo->findAssignedWorkOrder($workOrderId, $userId)) {
            throw new Exception('Work order not found.');
        }

        $this->repo->updateWorkOrderStatus($workOrderId, 'IN_PROGRESS');

        $this->repo->createWorkOrderNote([
            'work_order_id' => $workOrderId,
            'user_id' => $userId,
            'note' => 'Technician started work.',
            'note_type' => 'STATUS',
        ]);

        return [
            'message' => 'Work started successfully.',
            'work_order_id' => $workOrderId,
            'status' => 'IN_PROGRESS',
        ];
    }

    public function completeWork(array $sessionData, array $input): array
    {
        $userId = $this->requireTechnician($sessionData);

        $workOrderId = (int)($input['work_order_id'] ?? 0);
        $completionNotes = trim((string)($input['completion_notes'] ?? ''));

        if ($workOrderId <= 0) {
            throw new Exception('Invalid work order ID.');
        }

        if ($completionNotes === '') {
            throw new Exception('Completion notes are required.');
        }

        if (!$this->repo->findAssignedWorkOrder($workOrderId, $userId)) {
            throw new Exception('Work order not found.');
        }

        $this->repo->completeWorkOrder($workOrderId, $completionNotes);

        $this->repo->createWorkOrderNote([
            'work_order_id' => $workOrderId,
            'user_id' => $userId,
            'note' => $completionNotes,
            'note_type' => 'COMPLETION',
        ]);

        return [
            'message' => 'Work completed successfully.',
            'work_order_id' => $workOrderId,
            'status' => 'COMPLETED',
        ];
    }

    public function addNote(array $sessionData, array $input): array
    {
        $userId = $this->requireTechnician($sessionData);

        $workOrderId = (int)($input['work_order_id'] ?? 0);
        $note = trim((string)($input['note'] ?? ''));

        if ($workOrderId <= 0) {
            throw new Exception('Invalid work order ID.');
        }

        if ($note === '') {
            throw new Exception('Note is required.');
        }

        if (!$this->repo->findAssignedWorkOrder($workOrderId, $userId)) {
            throw new Exception('Work order not found.');
        }

        $noteId = $this->repo->createWorkOrderNote([
            'work_order_id' => $workOrderId,
            'user_id' => $userId,
            'note' => $note,
            'note_type' => 'NOTE',
        ]);

        return [
            'message' => 'Note added successfully.',
            'note_id' => $noteId,
            'work_order_id' => $workOrderId,
        ];
    }

    public function timeIn(array $sessionData, array $input): array
    {
        $userId = $this->requireTechnician($sessionData);

        $existing = $this->repo->getTodayAttendance($userId);

        if ($existing && !empty($existing['time_in_at']) && empty($existing['time_out_at'])) {
            throw new Exception('You are already timed in.');
        }

        $attendanceId = $this->repo->timeIn($userId, [
            'status' => 'AVAILABLE',
            'latitude' => $input['latitude'] ?? null,
            'longitude' => $input['longitude'] ?? null,
        ]);

        return [
            'message' => 'Timed in successfully.',
            'attendance_id' => $attendanceId,
            'status' => 'AVAILABLE',
        ];
    }

    public function timeOut(array $sessionData, array $input): array
    {
        $userId = $this->requireTechnician($sessionData);

        $attendance = $this->repo->getTodayAttendance($userId);

        if (!$attendance || empty($attendance['time_in_at'])) {
            throw new Exception('You have not timed in yet.');
        }

        if (!empty($attendance['time_out_at'])) {
            throw new Exception('You are already timed out.');
        }

        $this->repo->timeOut($userId);

        return [
            'message' => 'Timed out successfully.',
            'status' => 'OFF_DUTY',
        ];
    }

    public function updateAttendanceStatus(array $sessionData, array $input): array
    {
        $userId = $this->requireTechnician($sessionData);

        $status = strtoupper(trim((string)($input['status'] ?? '')));

        $allowed = [
            'AVAILABLE',
            'BUSY',
            'ON_BREAK',
            'OFF_DUTY',
        ];

        if (!in_array($status, $allowed, true)) {
            throw new Exception('Invalid attendance status.');
        }

        $attendance = $this->repo->getTodayAttendance($userId);

        if (!$attendance || empty($attendance['time_in_at']) || !empty($attendance['time_out_at'])) {
            throw new Exception('You must be timed in before changing status.');
        }

        $this->repo->updateAttendanceStatus($userId, $status);

        return [
            'message' => 'Attendance status updated.',
            'status' => $status,
        ];
    }

    private function requireTechnician(array $sessionData): int
    {
        $userId = (int)($sessionData['user_id'] ?? $sessionData['id'] ?? 0);
        $role = strtoupper((string)($sessionData['role'] ?? ''));

        if ($userId <= 0) {
            throw new Exception('You must be logged in.');
        }

        if ($role !== 'TECHNICIAN' && $role !== 'SUPERADMIN') {
            throw new Exception('Technician access only.');
        }

        return $userId;
    }
}