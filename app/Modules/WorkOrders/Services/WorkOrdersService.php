<?php

namespace App\Modules\WorkOrders\Services;

use App\Modules\Audit\DTOs\AuditEventDTO;
use App\Modules\Audit\Services\AuditService;
use App\Modules\Tickets\Repositories\TicketsRepository;
use App\Modules\SystemSettings\Repositories\SystemConfigRepository;
use App\Modules\WorkOrders\Entities\WorkOrder;
use App\Modules\WorkOrders\Repositories\WorkOrdersRepository;
use Exception;

class WorkOrdersService
{
    private WorkOrdersRepository $repo;

    private array $allowedStatuses = [
        'OPEN',
        'ASSIGNED',
        'IN_PROGRESS',
        'ON_SITE',
        'COMPLETED',
        'CANCELLED',
        'FAILED',
    ];

    private array $statusTransitions = [
        'OPEN' => ['ASSIGNED', 'IN_PROGRESS', 'CANCELLED', 'FAILED'],
        'ASSIGNED' => ['OPEN', 'IN_PROGRESS', 'CANCELLED', 'FAILED'],
        'IN_PROGRESS' => ['ON_SITE', 'COMPLETED', 'CANCELLED', 'FAILED'],
        'ON_SITE' => ['IN_PROGRESS', 'COMPLETED', 'CANCELLED', 'FAILED'],
        'FAILED' => ['OPEN', 'ASSIGNED', 'CANCELLED'],
        'COMPLETED' => [],
        'CANCELLED' => [],
    ];

    public function __construct(
        WorkOrdersRepository $repo,
        private TicketsRepository $tickets,
        private AuditService $audit,
        private SystemConfigRepository $systemConfig
    )
    {
        $this->repo = $repo;
    }

    public function workOrders(array $sessionData, array $filters = []): array
    {
        $this->requireStaff($sessionData);

        $filterArray = [
            'status' => !empty($filters['status']) ? strtoupper(trim((string)$filters['status'])) : null,
            'work_order_type' => !empty($filters['work_order_type']) ? strtoupper(trim((string)$filters['work_order_type'])) : null,
            'search' => !empty($filters['search']) ? trim((string)$filters['search']) : null,
            'limit' => isset($filters['limit']) ? max(1, min(200, (int)$filters['limit'])) : 100,
            'offset' => isset($filters['offset']) ? max(0, (int)$filters['offset']) : 0,
        ];

        return [
            'items' => array_map(
                static fn(array $row): array => (new WorkOrder($row))->toArray(),
                $this->repo->getWorkOrders($filterArray)
            ),
            'total' => $this->repo->countWorkOrders($filterArray),
            'summary' => $this->repo->getSummary(),
            'filters' => $filterArray,
        ];
    }

    public function workOrderDetails(array $sessionData, int $workOrderId): array
    {
        $this->requireStaff($sessionData);

        if ($workOrderId <= 0) {
            throw new Exception('Invalid work order ID.');
        }

        $workOrder = $this->repo->findWorkOrder($workOrderId);

        if (!$workOrder) {
            throw new Exception('Work order not found.');
        }

        return [
            'work_order' => (new WorkOrder($workOrder))->toArray(),
            'tasks' => $this->repo->getTasks($workOrderId),
            'status_logs' => $this->repo->getStatusLogs($workOrderId),
            'assignable_technicians' => $this->repo->getAssignableTechnicians(),
        ];
    }

    public function createFromTicket(array $sessionData, array $input): array
    {
        $this->requireStaff($sessionData);

        return $this->createFromTicketInternal($sessionData, $input);
    }

    public function createFromSubscriberTicket(array $sessionData, array $input): array
    {
        $userId = (int)($sessionData['user_id'] ?? $sessionData['id'] ?? 0);
        if ($userId <= 0 || strtoupper((string)($sessionData['role'] ?? '')) !== 'SUBSCRIBER') {
            throw new Exception('Subscriber access only.');
        }

        return $this->createFromTicketInternal($sessionData, $input, true);
    }

    private function createFromTicketInternal(array $sessionData, array $input, bool $subscriberInitiated = false): array
    {

        $userId = (int)($sessionData['user_id'] ?? $sessionData['id'] ?? 0);
        $ticketId = (int)($input['ticket_id'] ?? 0);

        if ($ticketId <= 0) {
            throw new Exception('Invalid ticket ID.');
        }

        $this->repo->acquireSourceLock('TICKET', $ticketId);
        try {
            $existing = $this->repo->findBySource('TICKET', $ticketId, null);
            if ($existing) {
                return [
                    'created' => false,
                    'message' => 'A work order already exists for this ticket.',
                    'work_order_id' => (int)$existing['id'],
                    'work_order_no' => (string)$existing['work_order_no'],
                    'status' => (string)$existing['status'],
                ];
            }

        $ticket = $this->tickets->findTicket($ticketId);

        if (!$ticket) {
            throw new Exception('Ticket not found.');
        }

        if ($subscriberInitiated && (int)($ticket['subscriber_user_id'] ?? 0) !== $userId) {
            throw new Exception('You are not allowed to create a work order for this ticket.');
        }

        if (strtoupper((string)($ticket['category'] ?? '')) !== 'INTERNET') {
            throw new Exception('Work orders can only be created from Internet-support tickets.');
        }

        if (in_array(strtoupper((string)($ticket['status'] ?? '')), ['RESOLVED', 'CLOSED', 'CANCELLED'], true)) {
            throw new Exception('A work order cannot be created from a terminal ticket.');
        }

        $workOrderType = $this->mapTicketCategoryToWorkOrderType((string)($ticket['category'] ?? ''));
        $assignee = $this->findAvailableTechnician();

        $assignedUserId = $assignee ? (int)$assignee['id'] : null;
        $status = $assignedUserId ? 'ASSIGNED' : 'OPEN';

        $preferredVisitDate = $this->normalizeDate($ticket['preferred_visit_date'] ?? null);
        $preferredVisitTime = $this->normalizeTime($ticket['preferred_visit_time'] ?? null);
        $preferredVisitNotes = trim((string)($ticket['preferred_visit_notes'] ?? ''));

        $description = trim((string)($ticket['description'] ?? ''));

        if ($preferredVisitDate || $preferredVisitTime || $preferredVisitNotes !== '') {
            $description .= "\n\nPreferred Visit Schedule:";
            $description .= "\nDate: " . ($preferredVisitDate ?: '-');
            $description .= "\nTime: " . ($preferredVisitTime ?: '-');

            if ($preferredVisitNotes !== '') {
                $description .= "\nNotes: " . $preferredVisitNotes;
            }
        }

        $created = $this->repo->createWithTasks([
            'source_type' => 'TICKET',
            'source_id' => $ticketId,
            'ticket_id' => $ticketId,
            'subscriber_id' => (int)$ticket['subscriber_id'],
            'service_id' => !empty($ticket['service_id']) ? (int)$ticket['service_id'] : null,
            'work_order_type' => $workOrderType,
            'title' => $ticket['subject'] ?: 'Work Order from Ticket',
            'description' => $description,
            'priority' => strtoupper((string)($ticket['priority'] ?? 'MEDIUM')),
            'status' => $status,
            'assigned_user_id' => $assignedUserId,
            'scheduled_date' => $preferredVisitDate,
            'scheduled_time' => $preferredVisitTime,
            'location' => $ticket['address'] ?? '',
            'contact_name' => $ticket['subscriber_name'] ?? '',
            'contact_number' => $ticket['contact_number'] ?? '',
            'created_by_user_id' => $userId,
        ], [
            'old_status' => null,
            'new_status' => $status,
            'changed_by_user_id' => $userId,
            'note' => $assignedUserId
                ? 'Work order created from ticket and auto-assigned.'
                : 'Work order created from ticket. Waiting for available technician.',
        ], $this->defaultTasksForType($workOrderType));
        $workOrderId = (int)$created['work_order_id'];
        $workOrderNo = (string)$created['work_order_no'];

        $this->auditAction('CREATE_FROM_TICKET', "Created work order {$workOrderNo} from ticket.", $workOrderId, [
            'work_order_no' => $workOrderNo, 'ticket_id' => $ticketId,
            'assigned_user_id' => $assignedUserId, 'status' => $status,
        ]);

        return [
            'created' => true,
            'message' => $assignedUserId
                ? 'Work order created and assigned successfully.'
                : 'Work order created. Waiting for available technician.',
            'work_order_id' => $workOrderId,
            'work_order_no' => $workOrderNo,
            'assigned_user_id' => $assignedUserId,
            'scheduled_date' => $preferredVisitDate,
            'scheduled_time' => $preferredVisitTime,
        ];
        } finally {
            $this->repo->releaseSourceLock('TICKET', $ticketId);
        }
    }

    public function createFromProvisioning(array $job): array
    {
        $jobId = (int)($job['id'] ?? 0);
        $serviceId = (int)($job['service_id'] ?? 0);
        $subscriberId = (int)($job['subscriber_id'] ?? 0);
        if ($jobId <= 0 || $serviceId <= 0 || $subscriberId <= 0) {
            throw new Exception('Provisioning job context is incomplete for work order generation.');
        }

        $this->repo->acquireSourceLock('SERVICE_PROVISIONING', $jobId);
        try {
            $existing = $this->repo->findBySource('SERVICE_PROVISIONING', $jobId, 'ONT_INSTALLATION');
            if ($existing) {
                return [
                    'created' => false,
                    'work_order_id' => (int)$existing['id'],
                    'work_order_no' => (string)$existing['work_order_no'],
                    'status' => (string)$existing['status'],
                    'message' => 'Installation work order already exists.',
                ];
            }

            $assignmentMode = strtoupper($this->systemConfig->value('installation_assignment_mode', 'MANUAL'));
            $latitude = isset($job['network_box_latitude']) ? (float)$job['network_box_latitude'] : null;
            $longitude = isset($job['network_box_longitude']) ? (float)$job['network_box_longitude'] : null;
            $assignee = null;
            if (
                $assignmentMode === 'AUTO_NEAREST'
                && $latitude !== null && $longitude !== null
                && $latitude >= -90 && $latitude <= 90
                && $longitude >= -180 && $longitude <= 180
            ) {
                $assignee = $this->repo->findNearestAvailableTechnician($latitude, $longitude);
            }
            $assignedUserId = $assignee ? (int)$assignee['id'] : null;
            $status = $assignedUserId ? 'ASSIGNED' : 'OPEN';
            $description = sprintf(
                "Confirm physical installation for provisioning job %s.\nONT: %s\nOLT/PON: %s\nNAP: %s\nSplitter: 1:%s, output port %s\nVLAN: C-%s / S-%s",
                (string)($job['job_no'] ?? ('#' . $jobId)),
                (string)($job['ont_serial'] ?? '-'),
                (string)($job['olt_port_label'] ?? '-'),
                (string)($job['network_box_name'] ?? '-'),
                (string)($job['splitter_ratio'] ?? '-'),
                (string)($job['splitter_output_port_number'] ?? '-'),
                (string)($job['cvlan'] ?? '-'),
                (string)($job['svlan'] ?? '-')
            );

            $created = $this->repo->createWithTasks([
                'source_type' => 'SERVICE_PROVISIONING',
                'source_id' => $jobId,
                'ticket_id' => null,
                'subscriber_id' => $subscriberId,
                'service_id' => $serviceId,
                'work_order_type' => 'ONT_INSTALLATION',
                'title' => 'Confirm installation - ' . (string)($job['subscriber_name'] ?? ('Subscriber #' . $subscriberId)),
                'description' => $description,
                'priority' => 'MEDIUM',
                'status' => $status,
                'assigned_user_id' => $assignedUserId,
                'scheduled_date' => null,
                'scheduled_time' => null,
                'location' => (string)($job['network_box_location'] ?? ''),
                'contact_name' => (string)($job['subscriber_name'] ?? ''),
                'contact_number' => (string)($job['subscriber_contact_number'] ?? ''),
                'created_by_user_id' => null,
            ], [
                'old_status' => null,
                'new_status' => $status,
                'changed_by_user_id' => null,
                'note' => $assignedUserId
                    ? sprintf(
                        'Automatically generated after successful provisioning and assigned to the nearest available technician%s.',
                        isset($assignee['distance_km']) ? sprintf(' (%.2f km)', (float)$assignee['distance_km']) : ''
                    )
                    : ($assignmentMode === 'AUTO_NEAREST'
                        ? 'Automatically generated after successful provisioning. No location-qualified technician was available; manual dispatch required.'
                        : 'Automatically generated after successful provisioning for manual dispatch.'),
            ], $this->defaultTasksForType('ONT_INSTALLATION'));
            $workOrderId = (int)$created['work_order_id'];
            $workOrderNo = (string)$created['work_order_no'];

            $this->auditAction('CREATE_FROM_PROVISIONING', "Created installation work order {$workOrderNo} from provisioning job.", $workOrderId, [
                'work_order_no' => $workOrderNo,
                'provisioning_job_id' => $jobId,
                'service_id' => $serviceId,
                'assigned_user_id' => $assignedUserId,
                'status' => $status,
                'assignment_mode' => $assignmentMode,
                'distance_km' => isset($assignee['distance_km']) ? round((float)$assignee['distance_km'], 2) : null,
            ]);

            return [
                'created' => true,
                'work_order_id' => $workOrderId,
                'work_order_no' => $workOrderNo,
                'assigned_user_id' => $assignedUserId,
                'status' => $status,
                'assignment_mode' => $assignmentMode,
                'distance_km' => isset($assignee['distance_km']) ? round((float)$assignee['distance_km'], 2) : null,
                'message' => 'Installation confirmation work order created.',
            ];
        } finally {
            $this->repo->releaseSourceLock('SERVICE_PROVISIONING', $jobId);
        }
    }

    public function assign(array $sessionData, array $input): array
    {
        $this->requireStaff($sessionData);

        $userId = (int)($sessionData['user_id'] ?? $sessionData['id'] ?? 0);
        $workOrderId = (int)($input['work_order_id'] ?? 0);
        $assignedUserId = (int)($input['assigned_user_id'] ?? 0);

        if ($workOrderId <= 0) {
            throw new Exception('Invalid work order ID.');
        }

        $workOrder = $this->repo->findWorkOrder($workOrderId);

        if (!$workOrder) {
            throw new Exception('Work order not found.');
        }

        $oldStatus = strtoupper((string)($workOrder['status'] ?? 'OPEN'));

        if (in_array($oldStatus, ['IN_PROGRESS', 'ON_SITE', 'COMPLETED', 'CANCELLED', 'FAILED'], true)) {
            throw new Exception('An active or terminal work order cannot be reassigned.');
        }
        if ($assignedUserId > 0 && !$this->repo->findDispatchableTechnician($assignedUserId)) {
            throw new Exception('Selected technician must be active, clocked in, and available.');
        }
        $newAssignedUserId = $assignedUserId > 0 ? $assignedUserId : null;
        $newStatus = $newAssignedUserId && $oldStatus === 'OPEN' ? 'ASSIGNED' : ($newAssignedUserId ? $oldStatus : 'OPEN');

        $this->repo->transaction(function () use ($workOrderId, $newAssignedUserId, $oldStatus, $newStatus, $userId): void {
            if (!$this->repo->assignWorkOrder($workOrderId, $newAssignedUserId)) throw new Exception('Failed to assign work order.');
            $this->repo->createStatusLog([
                'work_order_id' => $workOrderId, 'old_status' => $oldStatus, 'new_status' => $newStatus,
                'changed_by_user_id' => $userId,
                'note' => $newAssignedUserId ? 'Work order assigned to technician.' : 'Work order unassigned.',
            ]);
        });

        $workOrderNo = (string)($workOrder['work_order_no'] ?? ('#' . $workOrderId));
        $this->auditAction('ASSIGN', "Changed assignment for work order {$workOrderNo}.", $workOrderId, [
            'work_order_no' => $workOrderNo,
            'old_assigned_user_id' => $workOrder['assigned_user_id'] ?? null,
            'new_assigned_user_id' => $newAssignedUserId,
        ]);

        return [
            'message' => $newAssignedUserId
                ? 'Work order assigned successfully.'
                : 'Work order unassigned successfully.',
            'work_order_id' => $workOrderId,
            'assigned_user_id' => $newAssignedUserId,
            'status' => $newStatus,
        ];
    }

    public function updateStatus(array $sessionData, array $input): array
    {
        $this->requireStaff($sessionData);

        $userId = (int)($sessionData['user_id'] ?? $sessionData['id'] ?? 0);
        $workOrderId = (int)($input['work_order_id'] ?? 0);
        $status = strtoupper(trim((string)($input['status'] ?? '')));
        $note = trim((string)($input['note'] ?? ''));

        if ($workOrderId <= 0) {
            throw new Exception('Invalid work order ID.');
        }

        if (!in_array($status, $this->allowedStatuses, true)) {
            throw new Exception('Invalid work order status.');
        }
        if (in_array($status, ['COMPLETED', 'FAILED', 'CANCELLED'], true) && $note === '') {
            throw new Exception('A completion, failure, or cancellation note is required.');
        }
        if (strlen($note) > 2000) throw new Exception('Work order note must not exceed 2000 characters.');

        $workOrder = $this->repo->findWorkOrder($workOrderId);

        if (!$workOrder) {
            throw new Exception('Work order not found.');
        }

        $oldStatus = strtoupper((string)($workOrder['status'] ?? 'OPEN'));

        if ($oldStatus === $status) {
            return [
                'message' => 'Work order is already ' . $this->formatLabel($status) . '.',
                'work_order_id' => $workOrderId,
                'status' => $status,
            ];
        }

        if (!in_array($status, $this->statusTransitions[$oldStatus] ?? [], true)) {
            throw new Exception("Work order cannot move from {$this->formatLabel($oldStatus)} to {$this->formatLabel($status)}.");
        }
        if (in_array($status, ['IN_PROGRESS', 'ON_SITE', 'COMPLETED'], true) && empty($workOrder['assigned_user_id'])) {
            throw new Exception('Assign the work order to an available technician before starting field work.');
        }

        if ($status === 'COMPLETED' && !$this->repo->allRequiredTasksCompleted($workOrderId)) {
            throw new Exception('Complete all required tasks before completing the work order.');
        }

        $this->repo->transaction(function () use ($workOrderId, $status, $userId, $note, $oldStatus): void {
            if (!$this->repo->updateWorkOrderStatus($workOrderId, $status, $userId, $note)) throw new Exception('Failed to update work order status.');
            $this->repo->createStatusLog([
                'work_order_id'=>$workOrderId, 'old_status'=>$oldStatus, 'new_status'=>$status,
                'changed_by_user_id'=>$userId, 'note'=>$note !== '' ? $note : 'Work order status updated.',
            ]);
        });

        $workOrderNo = (string)($workOrder['work_order_no'] ?? ('#' . $workOrderId));
        $this->auditAction('UPDATE_STATUS', "Updated work order {$workOrderNo} status.", $workOrderId, [
            'work_order_no' => $workOrderNo, 'ticket_id' => $workOrder['ticket_id'] ?? null,
            'old_status' => $oldStatus, 'new_status' => $status,
        ]);

        return [
            'message' => 'Work order status updated.',
            'work_order_id' => $workOrderId,
            'old_status' => $oldStatus,
            'new_status' => $status,
        ];
    }

    public function completeTask(array $sessionData, array $input): array
    {
        $this->requireStaff($sessionData);

        $userId = (int)($sessionData['user_id'] ?? $sessionData['id'] ?? 0);
        $taskId = (int)($input['task_id'] ?? 0);
        $notes = trim((string)($input['notes'] ?? ''));

        if ($taskId <= 0) {
            throw new Exception('Invalid task ID.');
        }

        $task = $this->repo->findTask($taskId);

        if (!$task) {
            throw new Exception('Task not found.');
        }

        $workOrder = $this->repo->findWorkOrder((int)$task['work_order_id']);
        if (!$workOrder || empty($workOrder['assigned_user_id']) || !in_array(strtoupper((string)$workOrder['status']), ['ASSIGNED', 'IN_PROGRESS', 'ON_SITE'], true)) {
            throw new Exception('Tasks can only be completed on an assigned or active work order.');
        }

        $ok = $this->repo->completeTask($taskId, $userId, $notes);

        if (!$ok) {
            throw new Exception('Failed to complete task.');
        }

        $workOrderNo = (string)($workOrder['work_order_no'] ?? ('#' . (int)$task['work_order_id']));
        $this->auditAction('COMPLETE_TASK', "Completed a task for work order {$workOrderNo}.", (int)$task['work_order_id'], [
            'work_order_no' => $workOrderNo, 'task_id' => $taskId,
        ]);

        return [
            'message' => 'Task marked as completed.',
            'task_id' => $taskId,
            'work_order_id' => (int)$task['work_order_id'],
        ];
    }

    public function reopenTask(array $sessionData, array $input): array
    {
        $this->requireStaff($sessionData);

        $taskId = (int)($input['task_id'] ?? 0);

        if ($taskId <= 0) {
            throw new Exception('Invalid task ID.');
        }

        $task = $this->repo->findTask($taskId);

        if (!$task) {
            throw new Exception('Task not found.');
        }

        $workOrder = $this->repo->findWorkOrder((int)$task['work_order_id']);
        if (!$workOrder || in_array(strtoupper((string)$workOrder['status']), ['COMPLETED', 'CANCELLED'], true)) {
            throw new Exception('Tasks on a terminal work order cannot be reopened.');
        }

        $ok = $this->repo->reopenTask($taskId);

        if (!$ok) {
            throw new Exception('Failed to reopen task.');
        }

        $workOrderNo = (string)($workOrder['work_order_no'] ?? ('#' . (int)$task['work_order_id']));
        $this->auditAction('REOPEN_TASK', "Reopened a task for work order {$workOrderNo}.", (int)$task['work_order_id'], [
            'work_order_no' => $workOrderNo, 'task_id' => $taskId,
        ]);

        return [
            'message' => 'Task reopened.',
            'task_id' => $taskId,
            'work_order_id' => (int)$task['work_order_id'],
        ];
    }

    private function auditAction(string $action, string $description, int $workOrderId, array $metadata = []): void
    {
        $this->audit->logEvent(new AuditEventDTO(
            module: 'WORK_ORDERS', action: $action, description: $description,
            objectType: 'WORK_ORDER', objectId: $workOrderId, metadata: $metadata
        ));
    }

    private function mapTicketCategoryToWorkOrderType(string $category): string
    {
        $category = strtoupper(trim($category));

        return match ($category) {
            'INSTALLATION' => 'ONT_INSTALLATION',
            'TECHNICAL_VISIT' => 'TECHNICAL_VISIT',
            'INTERNET' => 'NO_INTERNET',
            default => 'TECHNICAL_VISIT',
        };
    }

    private function findAvailableTechnician(): ?array
    {
        $rows = $this->repo->findAvailableTechnicians();

        return $rows[0] ?? null;
    }

    private function defaultTasksForType(string $type): array
    {
        return match (strtoupper($type)) {
            'ONT_INSTALLATION' => [
                'Verify subscriber identity',
                'Install ONT at customer premises',
                'Check optical signal level',
                'Configure ONT service',
                'Test internet connectivity',
                'Educate subscriber on basic troubleshooting',
                'Capture installation notes',
            ],
            'NO_INTERNET', 'LOS' => [
                'Check ONT power and LOS status',
                'Check drop cable and patch cord',
                'Check NAP port',
                'Verify optical signal level',
                'Restore service',
                'Confirm internet connectivity with subscriber',
            ],
            'TECHNICAL_VISIT' => [
                'Contact subscriber before visit',
                'Validate reported concern',
                'Perform onsite troubleshooting',
                'Apply required fix',
                'Confirm service restoration',
                'Capture visit notes',
            ],
            default => [
                'Validate work order details',
                'Perform assigned field task',
                'Confirm completion with subscriber',
                'Capture completion notes',
            ],
        };
    }

    private function normalizeDate(?string $value): ?string
    {
        $value = trim((string)$value);

        if ($value === '') {
            return null;
        }

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }

    private function normalizeTime(?string $value): ?string
    {
        $value = trim((string)$value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $value)) {
            return substr($value, 0, 5);
        }

        return preg_match('/^\d{2}:\d{2}$/', $value) ? $value : null;
    }

    private function formatLabel(string $value): string
    {
        return ucwords(strtolower(str_replace('_', ' ', $value)));
    }

    private function requireStaff(array $sessionData): void
    {
        $userId = (int)($sessionData['user_id'] ?? $sessionData['id'] ?? 0);
        $role = strtoupper((string)($sessionData['role'] ?? ''));

        if ($userId <= 0) {
            throw new Exception('You must be logged in.');
        }

        if ($role === 'SUBSCRIBER') {
            throw new Exception('Staff access only.');
        }
    }
}
