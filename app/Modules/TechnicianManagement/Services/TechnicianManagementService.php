<?php

namespace App\Modules\TechnicianManagement\Services;

use App\Modules\Audit\DTOs\AuditEventDTO;
use App\Modules\Audit\Services\AuditService;
use App\Modules\TechnicianManagement\Entities\Technician;
use App\Modules\TechnicianManagement\Repositories\TechnicianManagementRepository;
use App\Modules\WorkOrders\Services\WorkOrdersService;
use Exception;

class TechnicianManagementService
{
    private TechnicianManagementRepository $repo;

    private array $allowedStatuses = [
        'AVAILABLE',
        'BUSY',
        'ON_BREAK',
        'ON_SITE',
        'TRAVELING',
    ];

    public function __construct(
        TechnicianManagementRepository $repo,
        private AuditService $audit,
        private WorkOrdersService $workOrders
    )
    {
        $this->repo = $repo;
    }

    public function dashboard(array $sessionData, array $filters = []): array
    {
        $this->requireManager($sessionData);

        $filterArray = [
            'status' => !empty($filters['status']) ? strtoupper(trim((string)$filters['status'])) : null,
            'search' => !empty($filters['search']) ? trim((string)$filters['search']) : null,
        ];

        return [
            'technicians' => array_map(
                static fn(array $row): array => (new Technician($row))->toArray(),
                $this->repo->getTechnicians($filterArray)
            ),
            'summary' => $this->repo->getSummary(),
            'work_order_summary' => $this->repo->getWorkOrderSummary(),
        ];
    }

    public function technicianDetails(array $sessionData, int $id): array
    {
        $this->requireManager($sessionData);

        if ($id <= 0) {
            throw new Exception('Invalid technician ID.');
        }

        $technician = $this->repo->findTechnician($id);

        if (!$technician) {
            throw new Exception('Technician not found.');
        }

        return [
            'technician' => (new Technician($technician))->toArray(),
            'work_orders' => $this->repo->getTechnicianWorkOrders($id),
            'attendance_logs' => $this->repo->getAttendanceLogs($id),
        ];
    }

    public function updateStatus(array $sessionData, array $input): array
    {
        $this->requireManager($sessionData);

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

        $result = $this->repo->updateTodayStatus($userId, $status, $note);
        $this->auditAction('UPDATE_STATUS', 'Updated technician status.', 'TECHNICIAN', $userId, ['status' => $status]);
        return $result;
    }

    public function updateProfile(array $sessionData, array $input): array
    {
        $this->requireManager($sessionData);

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
            throw new Exception('Invalid technician skill level.');
        }
        $this->validateProfile($profile);

        $result = $this->repo->upsertProfile($profile);
        $this->auditAction('UPDATE_PROFILE', 'Updated technician profile.', 'TECHNICIAN', $userId);
        return $result;
    }

    public function dispatchBoard(array $sessionData): array
    {
        $this->requireManager($sessionData);

        return [
            'unassigned_work_orders' => $this->repo->getUnassignedWorkOrders(),
            'available_technicians' => $this->repo->getAvailableTechnicians(),
        ];
    }

    public function assignWorkOrder(array $sessionData, array $input): array
    {
        $this->requireManager($sessionData);

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
        if (strtoupper((string)($technician['account_status'] ?? '')) !== 'ACTIVE') {
            throw new Exception('Inactive technicians cannot receive work orders.');
        }
        if (strtoupper((string)($technician['availability_status'] ?? '')) !== 'AVAILABLE') {
            throw new Exception('Only clocked-in, available technicians can receive work orders.');
        }

        $changedBy = $this->currentUserId($sessionData);

        $result = $this->workOrders->assign($sessionData, [
            'work_order_id' => $workOrderId,
            'assigned_user_id' => $technicianId,
        ]);
        $this->auditAction('ASSIGN_WORK_ORDER', 'Assigned work order to technician.', 'WORK_ORDER', $workOrderId, [
            'technician_id' => $technicianId,
        ]);
        return $result;
    }

    public function updateWorkOrderStatus(array $sessionData, array $input): array
    {
        $this->requireManager($sessionData);

        $workOrderId = (int)($input['work_order_id'] ?? 0);
        $status = strtoupper(trim((string)($input['status'] ?? '')));
        $note = trim((string)($input['note'] ?? ''));

        if ($workOrderId <= 0) {
            throw new Exception('Work order is required.');
        }
        if (in_array($status, ['COMPLETED', 'FAILED', 'CANCELLED'], true) && $note === '') {
            throw new Exception('A completion, failure, or cancellation note is required.');
        }
        if (strlen($note) > 2000) {
            throw new Exception('Work order note must not exceed 2000 characters.');
        }

        $result = $this->workOrders->updateStatus($sessionData, [
            'work_order_id' => $workOrderId,
            'status' => $status,
            'note' => $note,
        ]);

        $workOrder = $this->repo->findWorkOrderAssignment($workOrderId);
        $technicianId = (int)($workOrder['assigned_user_id'] ?? 0);

        if ($technicianId > 0) {
            $technicianStatus = null;

            if ($status === 'IN_PROGRESS') {
                $technicianStatus = 'BUSY';
            }

            if ($status === 'ON_SITE') {
                $technicianStatus = 'ON_SITE';
            }

            if (in_array($status, ['COMPLETED', 'FAILED', 'CANCELLED'], true)) {
                $technicianStatus = $this->repo->hasActiveFieldWork($technicianId) ? 'BUSY' : 'AVAILABLE';
            }

            if ($technicianStatus !== null && $this->repo->hasActiveAttendance($technicianId)) {
                $this->repo->updateTodayStatus(
                    $technicianId,
                    $technicianStatus,
                    'Auto-updated from work order status: ' . $status
                );

                $result['technician_status'] = $technicianStatus;
            }
        }

        $this->auditAction('UPDATE_WORK_ORDER_STATUS', 'Updated work order status.', 'WORK_ORDER', $workOrderId, [
            'status' => $status, 'technician_id' => $technicianId ?: null,
        ]);
        return $result;
    }

    private function auditAction(
        string $action,
        string $description,
        string $objectType,
        int $objectId,
        array $metadata = []
    ): void {
        $this->audit->logEvent(new AuditEventDTO(
            module: 'TECHNICIAN_MANAGEMENT', action: $action, description: $description,
            objectType: $objectType, objectId: $objectId, metadata: $metadata
        ));
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

    private function validateProfile(array $profile): void
    {
        $limits = ['employee_no'=>50, 'mobile_number'=>30, 'service_area'=>150, 'vehicle'=>100, 'vehicle_plate'=>30, 'emergency_contact'=>150, 'emergency_number'=>30, 'notes'=>2000];
        foreach ($limits as $field => $limit) {
            if (strlen((string)($profile[$field] ?? '')) > $limit) {
                throw new Exception(ucwords(str_replace('_', ' ', $field)) . " must not exceed {$limit} characters.");
            }
        }
    }

    private function requireManager(array $sessionData): void
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

        if (!in_array($role, ['SUPERADMIN', 'NOC', 'SUPPORT'], true)) {
            throw new Exception('Technician management access is restricted to operations managers.');
        }
    }
}
