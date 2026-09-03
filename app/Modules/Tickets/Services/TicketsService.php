<?php

namespace App\Modules\Tickets\Services;

use App\Modules\Audit\DTOs\AuditEventDTO;
use App\Modules\Audit\Services\AuditService;
use App\Modules\Tickets\Entities\Ticket;
use App\Modules\Tickets\Repositories\TicketsRepository;
use Exception;

class TicketsService
{
    private TicketsRepository $repo;

    private array $allowedStatuses = [
        'OPEN',
        'IN_PROGRESS',
        'WAITING_CUSTOMER',
        'WAITING_TECHNICIAN',
        'WAITING_CUSTOMER_SCHEDULE',
        'VISIT_SCHEDULED',
        'RESOLVED',
        'CLOSED',
        'CANCELLED',
    ];

    private array $statusTransitions = [
        'OPEN' => ['IN_PROGRESS', 'WAITING_CUSTOMER', 'WAITING_TECHNICIAN', 'WAITING_CUSTOMER_SCHEDULE', 'RESOLVED', 'CANCELLED'],
        'IN_PROGRESS' => ['WAITING_CUSTOMER', 'WAITING_TECHNICIAN', 'WAITING_CUSTOMER_SCHEDULE', 'RESOLVED', 'CANCELLED'],
        'WAITING_CUSTOMER' => ['OPEN', 'IN_PROGRESS', 'CANCELLED'],
        'WAITING_TECHNICIAN' => ['IN_PROGRESS', 'WAITING_CUSTOMER_SCHEDULE', 'VISIT_SCHEDULED', 'RESOLVED', 'CANCELLED'],
        'WAITING_CUSTOMER_SCHEDULE' => ['VISIT_SCHEDULED', 'IN_PROGRESS', 'CANCELLED'],
        'VISIT_SCHEDULED' => ['WAITING_TECHNICIAN', 'IN_PROGRESS', 'RESOLVED', 'CANCELLED'],
        'RESOLVED' => ['CLOSED', 'OPEN'],
        'CLOSED' => [],
        'CANCELLED' => [],
    ];

    public function __construct(TicketsRepository $repo, private AuditService $audit)
    {
        $this->repo = $repo;
    }

    public function tickets(array $sessionData, array $filters = []): array
    {
        $this->requireStaff($sessionData);

        $role = strtoupper((string)($sessionData['role'] ?? ''));

        $filterArray = [
            'status' => !empty($filters['status']) ? strtoupper(trim((string)$filters['status'])) : null,
            'search' => !empty($filters['search']) ? trim((string)$filters['search']) : null,
            'limit' => isset($filters['limit']) ? max(1, min(200, (int)$filters['limit'])) : 100,
            'offset' => isset($filters['offset']) ? max(0, (int)$filters['offset']) : 0,
        ];

        if (in_array($role, ['BILLING', 'NOC', 'SUPPORT'], true)) {
            $filterArray['queue_role'] = $role;
        }

        return [
            'items' => array_map(
                static fn(array $row): array => (new Ticket($row))->toArray(),
                $this->repo->getTickets($filterArray)
            ),
            'total' => $this->repo->countTickets($filterArray),
            'summary' => $this->repo->getSummary($filterArray),
            'filters' => $filterArray,
        ];
    }

    public function ticketDetails(array $sessionData, int $ticketId): array
    {
        $this->requireStaff($sessionData);

        if ($ticketId <= 0) {
            throw new Exception('Invalid ticket ID.');
        }

        $ticket = $this->repo->findTicket($ticketId);

        if (!$ticket) {
            throw new Exception('Ticket not found.');
        }

        $this->ensureTicketAccessFromRow($sessionData, $ticket, 'view');

        return [
            'ticket' => (new Ticket($ticket))->toArray(),
            'messages' => $this->repo->getTicketMessages($ticketId, true),
            'assignable_users' => $this->repo->getAssignableUsers(),
        ];
    }

    public function replyTicket(array $sessionData, array $input): array
    {
        $this->requireStaff($sessionData);

        $userId = (int)($sessionData['user_id'] ?? $sessionData['id'] ?? 0);
        $ticketId = (int)($input['ticket_id'] ?? 0);
        $message = trim((string)($input['message'] ?? ''));

        if ($ticketId <= 0) {
            throw new Exception('Invalid ticket ID.');
        }

        if ($message === '') {
            throw new Exception('Reply message is required.');
        }
        if (strlen($message) > 5000) {
            throw new Exception('Reply message must not exceed 5000 characters.');
        }

        $ticket = $this->repo->findTicket($ticketId);

        if (!$ticket) {
            throw new Exception('Ticket not found.');
        }

        $this->ensureTicketAccessFromRow($sessionData, $ticket, 'manage');

        if (in_array(strtoupper((string)$ticket['status']), ['RESOLVED', 'CLOSED', 'CANCELLED'], true)) {
            throw new Exception('Reopen the ticket before adding a reply.');
        }

        $messageId = $this->repo->createTicketMessage([
            'ticket_id' => $ticketId,
            'sender_type' => 'STAFF',
            'sender_user_id' => $userId,
            'message' => $message,
            'is_internal' => 0,
        ]);

        if (strtoupper((string)$ticket['status']) === 'OPEN') {
            $this->repo->updateTicketStatus($ticketId, 'IN_PROGRESS');
            $this->repo->createStatusLog(['ticket_id' => $ticketId, 'old_status' => 'OPEN', 'new_status' => 'IN_PROGRESS', 'changed_by_user_id' => $userId, 'note' => 'Ticket moved to in progress when staff replied.']);
        }

        $ticketNo = (string)($ticket['ticket_no'] ?? ('#' . $ticketId));
        $this->auditAction('REPLY', "Staff replied to ticket {$ticketNo}.", $ticketId, [
            'ticket_no' => $ticketNo, 'message_id' => $messageId,
        ]);

        return [
            'message' => 'Reply sent successfully.',
            'message_id' => $messageId,
            'ticket_id' => $ticketId,
        ];
    }

    public function internalNote(array $sessionData, array $input): array
    {
        $this->requireStaff($sessionData);

        $userId = (int)($sessionData['user_id'] ?? $sessionData['id'] ?? 0);
        $ticketId = (int)($input['ticket_id'] ?? 0);
        $message = trim((string)($input['message'] ?? ''));

        if ($ticketId <= 0) {
            throw new Exception('Invalid ticket ID.');
        }

        if ($message === '') {
            throw new Exception('Internal note is required.');
        }
        if (strlen($message) > 5000) {
            throw new Exception('Internal note must not exceed 5000 characters.');
        }

        $ticket = $this->repo->findTicket($ticketId);

        if (!$ticket) {
            throw new Exception('Ticket not found.');
        }

        $this->ensureTicketAccessFromRow($sessionData, $ticket, 'manage');

        $messageId = $this->repo->createTicketMessage([
            'ticket_id' => $ticketId,
            'sender_type' => 'STAFF',
            'sender_user_id' => $userId,
            'message' => $message,
            'is_internal' => 1,
        ]);

        $ticketNo = (string)($ticket['ticket_no'] ?? ('#' . $ticketId));
        $this->auditAction('INTERNAL_NOTE', "Staff added an internal note to ticket {$ticketNo}.", $ticketId, [
            'ticket_no' => $ticketNo, 'message_id' => $messageId,
        ]);

        return [
            'message' => 'Internal note added successfully.',
            'message_id' => $messageId,
            'ticket_id' => $ticketId,
        ];
    }

    public function updateStatus(array $sessionData, array $input): array
    {
        $this->requireStaff($sessionData);

        $userId = (int)($sessionData['user_id'] ?? $sessionData['id'] ?? 0);
        $ticketId = (int)($input['ticket_id'] ?? 0);
        $status = strtoupper(trim((string)($input['status'] ?? '')));
        $note = trim((string)($input['note'] ?? ''));

        if ($ticketId <= 0) {
            throw new Exception('Invalid ticket ID.');
        }

        if (!in_array($status, $this->allowedStatuses, true)) {
            throw new Exception('Invalid ticket status.');
        }

        $ticket = $this->repo->findTicket($ticketId);

        if (!$ticket) {
            throw new Exception('Ticket not found.');
        }

        $this->ensureTicketAccessFromRow($sessionData, $ticket, 'manage');

        $oldStatus = strtoupper((string)($ticket['status'] ?? ''));

        if ($oldStatus === $status) {
            return [
                'message' => 'Ticket is already ' . $this->formatLabel($status) . '.',
                'ticket_id' => $ticketId,
                'old_status' => $oldStatus,
                'new_status' => $status,
            ];
        }

        if (!in_array($status, $this->statusTransitions[$oldStatus] ?? [], true)) {
            throw new Exception("Ticket cannot move from {$this->formatLabel($oldStatus)} to {$this->formatLabel($status)}.");
        }

        if (in_array($status, ['RESOLVED', 'CLOSED'], true)
            && !empty($ticket['work_order_id'])
            && !in_array(strtoupper((string)($ticket['work_order_status'] ?? '')), ['COMPLETED', 'CANCELLED'], true)) {
            throw new Exception('Complete or cancel the linked work order before resolving this ticket.');
        }

        $this->repo->updateTicketStatus($ticketId, $status);

        $statusNote = $note !== '' ? $note : $this->defaultStatusNote($status);

        $this->repo->createStatusLog([
            'ticket_id' => $ticketId,
            'old_status' => $oldStatus,
            'new_status' => $status,
            'changed_by_user_id' => $userId,
            'note' => $statusNote,
        ]);

        if ($status === 'WAITING_CUSTOMER_SCHEDULE') {
            $this->repo->createTicketMessage([
                'ticket_id' => $ticketId,
                'sender_type' => 'STAFF',
                'sender_user_id' => $userId,
                'message' => $statusNote !== ''
                    ? $statusNote
                    : 'A technical visit is needed. Please select your preferred visit schedule from this ticket.',
                'is_internal' => 0,
            ]);
        }

        $ticketNo = (string)($ticket['ticket_no'] ?? ('#' . $ticketId));
        $this->auditAction(
            'UPDATE_STATUS', "Staff updated ticket {$ticketNo} status.", $ticketId,
            ['ticket_no' => $ticketNo, 'old_status' => $oldStatus, 'new_status' => $status]
        );

        return [
            'message' => 'Ticket status updated.',
            'ticket_id' => $ticketId,
            'old_status' => $oldStatus,
            'new_status' => $status,
        ];
    }

    public function updatePriority(array $sessionData, array $input): array
    {
        $this->requireStaff($sessionData);

        $ticketId = (int)($input['ticket_id'] ?? 0);
        $priority = strtoupper(trim((string)($input['priority'] ?? '')));

        $allowed = ['LOW', 'MEDIUM', 'HIGH', 'URGENT'];

        if ($ticketId <= 0) {
            throw new Exception('Invalid ticket ID.');
        }

        if (!in_array($priority, $allowed, true)) {
            throw new Exception('Invalid ticket priority.');
        }

        $ticket = $this->repo->findTicket($ticketId);

        if (!$ticket) {
            throw new Exception('Ticket not found.');
        }

        $this->ensureTicketAccessFromRow($sessionData, $ticket, 'manage');

        $this->repo->updateTicketPriority($ticketId, $priority);

        $ticketNo = (string)($ticket['ticket_no'] ?? ('#' . $ticketId));
        $this->auditAction(
            'UPDATE_PRIORITY', "Staff updated ticket {$ticketNo} priority.", $ticketId,
            ['ticket_no' => $ticketNo, 'old_priority' => (string)($ticket['priority'] ?? ''), 'new_priority' => $priority]
        );

        return [
            'message' => 'Ticket priority updated.',
            'ticket_id' => $ticketId,
            'priority' => $priority,
        ];
    }

    public function assignTicket(array $sessionData, array $input): array
    {
        $this->requireStaff($sessionData);

        $ticketId = (int)($input['ticket_id'] ?? 0);
        $assignedUserId = (int)($input['assigned_user_id'] ?? 0);

        if ($ticketId <= 0) {
            throw new Exception('Invalid ticket ID.');
        }

        $ticket = $this->repo->findTicket($ticketId);

        if (!$ticket) {
            throw new Exception('Ticket not found.');
        }

        $this->ensureTicketAccessFromRow($sessionData, $ticket, 'manage');

        $assignee = null;
        if ($assignedUserId > 0) {
            $assignee = $this->repo->findAssignableUser($assignedUserId);
            if (!$assignee) {
                throw new Exception('Selected assignee is not an active ticket-queue staff member.');
            }
            $expectedRole = $this->queueRoleForCategory((string)($ticket['category'] ?? ''));
            if (strtoupper((string)$assignee['role']) !== $expectedRole) {
                throw new Exception("This ticket belongs to the {$expectedRole} queue.");
            }
        }

        $this->repo->assignTicket($ticketId, $assignedUserId > 0 ? $assignedUserId : null);

        $ticketNo = (string)($ticket['ticket_no'] ?? ('#' . $ticketId));
        $this->auditAction(
            'ASSIGN', "Staff changed ticket {$ticketNo} assignment.", $ticketId,
            ['ticket_no' => $ticketNo, 'old_assigned_user_id' => $ticket['assigned_user_id'] ?? null, 'new_assigned_user_id' => $assignedUserId ?: null]
        );

        return [
            'message' => 'Ticket assigned successfully.',
            'ticket_id' => $ticketId,
            'assigned_user_id' => $assignedUserId > 0 ? $assignedUserId : null,
        ];
    }

    private function auditAction(string $action, string $description, int $ticketId, array $metadata = []): void
    {
        $this->audit->logEvent(new AuditEventDTO(
            module: 'TICKETS', action: $action, description: $description,
            objectType: 'TICKET', objectId: $ticketId, metadata: $metadata
        ));
    }

    private function ensureTicketAccessFromRow(array $sessionData, array $ticket, string $action = 'view'): void
    {
        $role = strtoupper((string)($sessionData['role'] ?? ''));

        if (!in_array($role, ['BILLING', 'NOC', 'SUPPORT'], true)) {
            return;
        }

        $assignedUserRole = strtoupper((string)($ticket['assigned_user_role'] ?? ''));
        $queueRole = $this->queueRoleForCategory((string)($ticket['category'] ?? ''));

        if (($assignedUserRole !== '' && $assignedUserRole !== $role)
            || ($assignedUserRole === '' && $queueRole !== $role)) {
            throw new Exception(
                $action === 'manage'
                    ? 'You are not allowed to manage this ticket.'
                    : 'You are not allowed to view this ticket.'
            );
        }
    }

    private function queueRoleForCategory(string $category): string
    {
        return match (strtoupper($category)) {
            'INTERNET' => 'NOC',
            'BILLING' => 'BILLING',
            default => 'SUPPORT',
        };
    }

    private function autoAssignOpenUnassignedTickets(): void
    {
        $rows = $this->repo->getTickets([
            'status' => 'OPEN',
            'limit' => 200,
            'offset' => 0,
        ]);

        foreach ($rows as $ticket) {
            if (!empty($ticket['assigned_user_id'])) {
                continue;
            }

            $this->autoAssignSingleTicket($ticket);
        }
    }

    private function autoAssignSingleTicket(array $ticket): void
    {
        $ticketId = (int)($ticket['id'] ?? 0);

        if ($ticketId <= 0) {
            return;
        }

        $assignee = $this->repo->findBestAvailableAssignee((string)($ticket['category'] ?? ''));

        if (!$assignee || empty($assignee['id'])) {
            return;
        }

        $assignedUserId = (int)$assignee['id'];
        $assigneeName = $assignee['full_name'] ?: ($assignee['username'] ?? 'staff');

        $this->repo->assignTicket($ticketId, $assignedUserId);

        $this->repo->createTicketMessage([
            'ticket_id' => $ticketId,
            'sender_type' => 'SYSTEM',
            'sender_user_id' => null,
            'message' => 'Ticket auto-assigned to ' . $assigneeName . ' based on staff availability.',
            'is_internal' => 1,
        ]);
    }

    private function defaultStatusNote(string $status): string
    {
        return match (strtoupper($status)) {
            'OPEN' => 'Ticket reopened.',
            'IN_PROGRESS' => 'Ticket is now being checked by support.',
            'WAITING_CUSTOMER' => 'Waiting for subscriber response.',
            'WAITING_TECHNICIAN' => 'Waiting for technician action.',
            'WAITING_CUSTOMER_SCHEDULE' => 'A technical visit is needed. Please select your preferred visit schedule from this ticket.',
            'VISIT_SCHEDULED' => 'Subscriber has scheduled a technical visit.',
            'RESOLVED' => 'Ticket has been resolved.',
            'CLOSED' => 'Ticket has been closed.',
            'CANCELLED' => 'Ticket has been cancelled.',
            default => 'Ticket status updated.',
        };
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
