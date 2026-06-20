<?php

namespace App\Modules\TechnicianManagement\Services;

use App\Modules\TechnicianManagement\Repositories\TechnicianManagementRepository;
use Exception;

class TechnicianManagementService
{
    private TechnicianManagementRepository $repo;

    private array $allowedStatuses = [
        'OFFLINE',
        'AVAILABLE',
        'BUSY',
        'ON_BREAK',
        'ON_SITE',
        'TRAVELING',
    ];

    public function __construct(TechnicianManagementRepository $repo)
    {
        $this->repo = $repo;
    }

    public function dashboard(array $sessionData, array $filters = []): array
    {
        $this->requireStaff($sessionData);

        $filterArray = [
            'status' => !empty($filters['status']) ? strtoupper(trim((string)$filters['status'])) : null,
            'search' => !empty($filters['search']) ? trim((string)$filters['search']) : null,
        ];

        return [
            'technicians' => $this->repo->getTechnicians($filterArray),
            'summary' => $this->repo->getSummary(),
            'work_order_summary' => $this->repo->getWorkOrderSummary(),
        ];
    }

    public function technicianDetails(array $sessionData, int $id): array
    {
        $this->requireStaff($sessionData);

        if ($id <= 0) {
            throw new Exception('Invalid technician ID.');
        }

        $technician = $this->repo->findTechnician($id);

        if (!$technician) {
            throw new Exception('Technician not found.');
        }

        return [
            'technician' => $technician,
            'work_orders' => $this->repo->getTechnicianWorkOrders($id),
            'attendance_logs' => $this->repo->getAttendanceLogs($id),
        ];
    }

    public function updateStatus(array $sessionData, array $input): array
    {
        $this->requireStaff($sessionData);

        $userId = (int)($input['user_id'] ?? 0);
        $status = strtoupper(trim((string)($input['status'] ?? '')));
        $note = trim((string)($input['note'] ?? ''));

        if ($userId <= 0) {
            throw new Exception('Technician is required.');
        }

        if (!in_array($status, $this->allowedStatuses, true)) {
            throw new Exception('Invalid technician status.');
        }

        $technician = $this->repo->findTechnician($userId);

        if (!$technician) {
            throw new Exception('Technician not found.');
        }

        return $this->repo->updateTodayStatus($userId, $status, $note);
    }

    public function updateProfile(array $sessionData, array $input): array
    {
        $this->requireStaff($sessionData);

        $userId = (int)($input['user_id'] ?? 0);

        if ($userId <= 0) {
            throw new Exception('Technician is required.');
        }

        $technician = $this->repo->findTechnician($userId);

        if (!$technician) {
            throw new Exception('Technician not found.');
        }

        $profile = [
            'user_id' => $userId,
            'employee_no' => trim((string)($input['employee_no'] ?? '')),
            'mobile_number' => trim((string)($input['mobile_number'] ?? '')),
            'service_area' => trim((string)($input['service_area'] ?? '')),
            'skill_level' => strtoupper(trim((string)($input['skill_level'] ?? 'JUNIOR'))),
            'vehicle' => trim((string)($input['vehicle'] ?? '')),
            'vehicle_plate' => trim((string)($input['vehicle_plate'] ?? '')),
            'emergency_contact' => trim((string)($input['emergency_contact'] ?? '')),
            'emergency_number' => trim((string)($input['emergency_number'] ?? '')),
            'notes' => trim((string)($input['notes'] ?? '')),
        ];

        if (!in_array($profile['skill_level'], ['JUNIOR', 'SENIOR', 'LEAD'], true)) {
            $profile['skill_level'] = 'JUNIOR';
        }

        return $this->repo->upsertProfile($profile);
    }

    public function dispatchBoard(array $sessionData): array
    {
        $this->requireStaff($sessionData);

        return [
            'unassigned_work_orders' => $this->repo->getUnassignedWorkOrders(),
            'available_technicians' => $this->repo->getAvailableTechnicians(),
        ];
    }

    public function assignWorkOrder(array $sessionData, array $input): array
    {
        $this->requireStaff($sessionData);

        $workOrderId = (int)($input['work_order_id'] ?? 0);
        $technicianId = (int)($input['technician_id'] ?? 0);

        if ($workOrderId <= 0) {
            throw new Exception('Work order is required.');
        }

        if ($technicianId <= 0) {
            throw new Exception('Technician is required.');
        }

        $technician = $this->repo->findTechnician($technicianId);

        if (!$technician) {
            throw new Exception('Technician not found.');
        }

        $changedBy = $this->currentUserId($sessionData);

        return $this->repo->assignWorkOrderToTechnician($workOrderId, $technicianId, $changedBy);
    }

    public function updateWorkOrderStatus(array $sessionData, array $input): array
    {
        $this->requireStaff($sessionData);

        $workOrderId = (int)($input['work_order_id'] ?? 0);
        $status = strtoupper(trim((string)($input['status'] ?? '')));
        $note = trim((string)($input['note'] ?? ''));

        $allowed = [
            'IN_PROGRESS',
            'ON_SITE',
            'COMPLETED',
            'FAILED',
        ];

        if ($workOrderId <= 0) {
            throw new Exception('Work order is required.');
        }

        if (!in_array($status, $allowed, true)) {
            throw new Exception('Invalid work order status.');
        }

        $changedBy = $this->currentUserId($sessionData);

        $result = $this->repo->updateWorkOrderStatus($workOrderId, $status, $changedBy, $note);

        $technicianId = (int)($result['technician_id'] ?? 0);

        if ($technicianId > 0) {
            $technicianStatus = null;

            if ($status === 'IN_PROGRESS') {
                $technicianStatus = 'BUSY';
            }

            if ($status === 'ON_SITE') {
                $technicianStatus = 'ON_SITE';
            }

            if (in_array($status, ['COMPLETED', 'FAILED'], true)) {
                $technicianStatus = 'AVAILABLE';
            }

            if ($technicianStatus !== null) {
                $this->repo->updateTodayStatus(
                    $technicianId,
                    $technicianStatus,
                    'Auto-updated from work order status: ' . $status
                );

                $result['technician_status'] = $technicianStatus;
            }
        }

        return $result;
    }

    private function currentUserId(array $sessionData): int
    {
        return (int)(
            $sessionData['id']
            ?? $sessionData['user_id']
            ?? $_SESSION['user']['id']
            ?? $_SESSION['auth_user']['id']
            ?? 0
        );
    }

    private function requireStaff(array $sessionData): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        $userId = (int)(
            $sessionData['user_id']
            ?? $sessionData['id']
            ?? $_SESSION['user_id']
            ?? $_SESSION['auth_user_id']
            ?? $_SESSION['user']['id']
            ?? $_SESSION['auth_user']['id']
            ?? 0
        );

        $role = strtoupper((string)(
            $sessionData['role']
            ?? $_SESSION['role']
            ?? $_SESSION['user']['role']
            ?? $_SESSION['auth_user']['role']
            ?? ''
        ));

        if ($userId <= 0) {
            throw new Exception('You must be logged in.');
        }

        if ($role === 'SUBSCRIBER') {
            throw new Exception('Staff access only.');
        }
    }
}