<?php

namespace App\Modules\WorkOrders\Services;

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

    public function __construct(WorkOrdersRepository $repo)
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
            'items' => $this->repo->getWorkOrders($filterArray),
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
            'work_order' => $workOrder,
            'tasks' => $this->repo->getTasks($workOrderId),
            'status_logs' => $this->repo->getStatusLogs($workOrderId),
            'assignable_technicians' => $this->repo->getAssignableTechnicians(),
        ];
    }

    public function createFromTicket(array $sessionData, array $input): array
    {
        $this->requireStaff($sessionData);

        $userId = (int)($sessionData['user_id'] ?? $sessionData['id'] ?? 0);
        $ticketId = (int)($input['ticket_id'] ?? 0);

        if ($ticketId <= 0) {
            throw new Exception('Invalid ticket ID.');
        }

        $ticketRepo = new \App\Modules\Tickets\Repositories\TicketsRepository(
            new \Framework\DatabaseConnection()
        );

        $ticket = $ticketRepo->findTicket($ticketId);

        if (!$ticket) {
            throw new Exception('Ticket not found.');
        }

        $workOrderType = $this->mapTicketCategoryToWorkOrderType((string)($ticket['category'] ?? ''));
        $assignee = $this->findAvailableTechnician();

        $assignedUserId = $assignee ? (int)$assignee['id'] : null;
        $status = $assignedUserId ? 'ASSIGNED' : 'OPEN';

        $workOrderNo = $this->repo->generateWorkOrderNo();

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

        $workOrderId = $this->repo->createWorkOrder([
            'work_order_no' => $workOrderNo,
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
        ]);

        $this->repo->createStatusLog([
            'work_order_id' => $workOrderId,
            'old_status' => null,
            'new_status' => $status,
            'changed_by_user_id' => $userId,
            'note' => $assignedUserId
                ? 'Work order created from ticket and auto-assigned.'
                : 'Work order created from ticket. Waiting for available technician.',
        ]);

        foreach ($this->defaultTasksForType($workOrderType) as $taskName) {
            $this->repo->createTask($workOrderId, $taskName, true);
        }

        return [
            'message' => $assignedUserId
                ? 'Work order created and assigned successfully.'
                : 'Work order created. Waiting for available technician.',
            'work_order_id' => $workOrderId,
            'work_order_no' => $workOrderNo,
            'assigned_user_id' => $assignedUserId,
            'scheduled_date' => $preferredVisitDate,
            'scheduled_time' => $preferredVisitTime,
        ];
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
        $newAssignedUserId = $assignedUserId > 0 ? $assignedUserId : null;
        $newStatus = $newAssignedUserId && $oldStatus === 'OPEN' ? 'ASSIGNED' : ($newAssignedUserId ? $oldStatus : 'OPEN');

        $ok = $this->repo->assignWorkOrder($workOrderId, $newAssignedUserId);

        if (!$ok) {
            throw new Exception('Failed to assign work order.');
        }

        $this->repo->createStatusLog([
            'work_order_id' => $workOrderId,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'changed_by_user_id' => $userId,
            'note' => $newAssignedUserId
                ? 'Work order assigned to technician.'
                : 'Work order unassigned.',
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

        if ($status === 'COMPLETED' && !$this->repo->allRequiredTasksCompleted($workOrderId)) {
            throw new Exception('Complete all required tasks before completing the work order.');
        }

        $ok = $this->repo->updateWorkOrderStatus($workOrderId, $status);

        if (!$ok) {
            throw new Exception('Failed to update work order status.');
        }

        $this->repo->createStatusLog([
            'work_order_id' => $workOrderId,
            'old_status' => $oldStatus,
            'new_status' => $status,
            'changed_by_user_id' => $userId,
            'note' => $note !== '' ? $note : 'Work order status updated.',
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

        $ok = $this->repo->completeTask($taskId, $userId, $notes);

        if (!$ok) {
            throw new Exception('Failed to complete task.');
        }

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

        $ok = $this->repo->reopenTask($taskId);

        if (!$ok) {
            throw new Exception('Failed to reopen task.');
        }

        return [
            'message' => 'Task reopened.',
            'task_id' => $taskId,
            'work_order_id' => (int)$task['work_order_id'],
        ];
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