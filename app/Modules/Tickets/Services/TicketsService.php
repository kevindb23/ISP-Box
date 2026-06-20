<?php

namespace App\Modules\Tickets\Services;

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

    public function __construct(TicketsRepository $repo)
    {
        $this->repo = $repo;
    }

    public function tickets(array $sessionData, array $filters = []): array
    {
        $this->requireStaff($sessionData);

        $this->autoAssignOpenUnassignedTickets();

        $filterArray = [
            'status' => !empty($filters['status']) ? strtoupper(trim((string)$filters['status'])) : null,
            'search' => !empty($filters['search']) ? trim((string)$filters['search']) : null,
            'limit' => isset($filters['limit']) ? max(1, min(200, (int)$filters['limit'])) : 100,
            'offset' => isset($filters['offset']) ? max(0, (int)$filters['offset']) : 0,
        ];

        return [
            'items' => $this->repo->getTickets($filterArray),
            'total' => $this->repo->countTickets($filterArray),
            'summary' => $this->repo->getSummary(),
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

        if (empty($ticket['assigned_user_id']) && strtoupper((string)$ticket['status']) === 'OPEN') {
            $this->autoAssignSingleTicket($ticket);
            $ticket = $this->repo->findTicket($ticketId) ?: $ticket;
        }

        return [
            'ticket' => $ticket,
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

        $ticket = $this->repo->findTicket($ticketId);

        if (!$ticket) {
            throw new Exception('Ticket not found.');
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
        }

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

        if (!$this->repo->findTicket($ticketId)) {
            throw new Exception('Ticket not found.');
        }

        $messageId = $this->repo->createTicketMessage([
            'ticket_id' => $ticketId,
            'sender_type' => 'STAFF',
            'sender_user_id' => $userId,
            'message' => $message,
            'is_internal' => 1,
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

        $oldStatus = strtoupper((string)($ticket['status'] ?? ''));

        if ($oldStatus === $status) {
            return [
                'message' => 'Ticket is already ' . $this->formatLabel($status) . '.',
                'ticket_id' => $ticketId,
                'old_status' => $oldStatus,
                'new_status' => $status,
            ];
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

        if (!$this->repo->findTicket($ticketId)) {
            throw new Exception('Ticket not found.');
        }

        $this->repo->updateTicketPriority($ticketId, $priority);

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

        if (!$this->repo->findTicket($ticketId)) {
            throw new Exception('Ticket not found.');
        }

        $this->repo->assignTicket($ticketId, $assignedUserId > 0 ? $assignedUserId : null);

        return [
            'message' => 'Ticket assigned successfully.',
            'ticket_id' => $ticketId,
            'assigned_user_id' => $assignedUserId > 0 ? $assignedUserId : null,
        ];
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