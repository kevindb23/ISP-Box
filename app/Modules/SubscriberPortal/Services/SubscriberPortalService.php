<?php

namespace App\Modules\SubscriberPortal\Services;

use App\Modules\SubscriberPortal\DTOs\SubscriberInvoiceFilterDTO;
use App\Modules\SubscriberPortal\DTOs\SubscriberPortalSessionDTO;
use App\Modules\SubscriberPortal\Entities\SubscriberPortalInvoice;
use App\Modules\SubscriberPortal\Entities\SubscriberPortalPayment;
use App\Modules\SubscriberPortal\Entities\SubscriberPortalProfile;
use App\Modules\SubscriberPortal\Entities\SubscriberPortalServiceAccount;
use App\Modules\SubscriberPortal\Repositories\SubscriberPortalRepository;
use App\Modules\SubscriberPortal\Validators\SubscriberPortalAccessValidator;
use DateTime;
use Exception;
use Throwable;

class SubscriberPortalService
{
    private SubscriberPortalRepository $repo;
    private SubscriberPortalAccessValidator $validator;

    public function __construct(
        SubscriberPortalRepository $repo,
        ?SubscriberPortalAccessValidator $validator = null
    ) {
        $this->repo = $repo;
        $this->validator = $validator ?: new SubscriberPortalAccessValidator();
    }

    public function getSessionSubscriber(array $sessionData): array
    {
        $session = new SubscriberPortalSessionDTO($sessionData);
        $this->validator->validateSession($session);

        $subscriber = $this->repo->findSubscriberByUserId($session->user_id);

        if (!$subscriber) {
            throw new Exception('Subscriber account is not linked to this user.');
        }

        $subscriberId = (int)($subscriber['id'] ?? 0);
        $this->validator->validateSubscriberId($subscriberId);

        return [
            'session' => $session->toArray(),
            'subscriber' => (new SubscriberPortalProfile($subscriber))->toArray(),
        ];
    }

    public function dashboard(array $sessionData): array
    {
        $context = $this->getSessionSubscriber($sessionData);
        $subscriberId = (int)$context['subscriber']['id'];

        return [
            'profile' => $context['subscriber'],
            'overview' => $this->repo->getOverview($subscriberId),
            'services' => array_map(
                fn(array $row) => (new SubscriberPortalServiceAccount($row))->toArray(),
                $this->repo->getServices($subscriberId)
            ),
        ];
    }

    public function profile(array $sessionData): array
    {
        $context = $this->getSessionSubscriber($sessionData);

        return [
            'profile' => $context['subscriber'],
        ];
    }

    public function services(array $sessionData): array
    {
        $context = $this->getSessionSubscriber($sessionData);
        $subscriberId = (int)$context['subscriber']['id'];

        $services = $this->repo->getServices($subscriberId);

        return [
            'items' => array_map(
                fn(array $row) => (new SubscriberPortalServiceAccount($row))->toArray(),
                $services
            ),
            'total' => count($services),
        ];
    }

    public function invoices(array $sessionData, array $filters = []): array
    {
        $context = $this->getSessionSubscriber($sessionData);
        $subscriberId = (int)$context['subscriber']['id'];

        $dto = new SubscriberInvoiceFilterDTO($filters);
        $filterArray = $dto->toArray();

        $rows = $this->repo->getInvoices($subscriberId, $filterArray);

        return [
            'items' => array_map(
                fn(array $row) => (new SubscriberPortalInvoice($row))->toArray(),
                $rows
            ),
            'total' => $this->repo->countInvoices($subscriberId, $filterArray),
            'filters' => $filterArray,
        ];
    }

    public function invoiceDetails(array $sessionData, int $invoiceId): array
    {
        if ($invoiceId <= 0) {
            throw new Exception('Invalid invoice ID.');
        }

        $context = $this->getSessionSubscriber($sessionData);
        $subscriberId = (int)$context['subscriber']['id'];

        $invoice = $this->repo->findInvoiceForSubscriber($invoiceId, $subscriberId);
        $this->validator->validateInvoiceOwnership($invoice ?: [], $subscriberId);

        return [
            'invoice' => (new SubscriberPortalInvoice($invoice))->toArray(),
            'items' => $this->repo->getInvoiceItems($invoiceId),
            'payments' => array_map(
                fn(array $row) => (new SubscriberPortalPayment($row))->toArray(),
                $this->repo->getInvoicePayments($invoiceId)
            ),
        ];
    }

    public function payments(array $sessionData, array $filters = []): array
    {
        $context = $this->getSessionSubscriber($sessionData);
        $subscriberId = (int)$context['subscriber']['id'];

        $dto = new SubscriberInvoiceFilterDTO($filters);
        $filterArray = $dto->toArray();

        $rows = $this->repo->getPayments($subscriberId, $filterArray);

        return [
            'items' => array_map(
                fn(array $row) => (new SubscriberPortalPayment($row))->toArray(),
                $rows
            ),
            'total' => $this->repo->countPayments($subscriberId, $filterArray),
            'filters' => $filterArray,
        ];
    }

    public function changePassword(array $sessionData, array $input): array
    {
        $session = new SubscriberPortalSessionDTO($sessionData);
        $this->validator->validateSession($session);

        $userId = (int)$session->user_id;

        if ($userId <= 0) {
            throw new Exception('Invalid subscriber session.');
        }

        $currentPassword = (string)($input['current_password'] ?? '');
        $newPassword = (string)($input['new_password'] ?? '');
        $confirmPassword = (string)($input['confirm_password'] ?? '');

        if (trim($currentPassword) === '') {
            throw new Exception('Current password is required.');
        }

        if (trim($newPassword) === '') {
            throw new Exception('New password is required.');
        }

        if (strlen($newPassword) < 8) {
            throw new Exception('New password must be at least 8 characters.');
        }

        if ($newPassword !== $confirmPassword) {
            throw new Exception('New password and confirm password do not match.');
        }

        if ($currentPassword === $newPassword) {
            throw new Exception('New password must be different from your current password.');
        }

        $user = $this->repo->findUserForPasswordChange($userId);

        if (!$user) {
            throw new Exception('Subscriber portal user account was not found.');
        }

        if (!password_verify($currentPassword, (string)($user['password'] ?? ''))) {
            throw new Exception('Current password is incorrect.');
        }

        $ok = $this->repo->updateUserPassword(
            $userId,
            password_hash($newPassword, PASSWORD_DEFAULT)
        );

        if (!$ok) {
            throw new Exception('Failed to update portal password.');
        }

        return [
            'message' => 'Portal password changed successfully.',
        ];
    }

    public function tickets(array $sessionData, array $filters = []): array
    {
        $context = $this->getSessionSubscriber($sessionData);
        $subscriberId = (int)$context['subscriber']['id'];

        $filterArray = [
            'status' => !empty($filters['status']) ? strtoupper(trim((string)$filters['status'])) : null,
            'search' => !empty($filters['search']) ? trim((string)$filters['search']) : null,
            'limit' => isset($filters['limit']) ? max(1, min(100, (int)$filters['limit'])) : 50,
            'offset' => isset($filters['offset']) ? max(0, (int)$filters['offset']) : 0,
        ];

        return [
            'items' => $this->repo->getTickets($subscriberId, $filterArray),
            'total' => $this->repo->countTickets($subscriberId, $filterArray),
            'filters' => $filterArray,
        ];
    }

    public function visitSlots(array $sessionData, array $input): array
    {
        $this->getSessionSubscriber($sessionData);

        $date = trim((string)($input['date'] ?? ''));

        if ($date === '') {
            throw new Exception('Visit date is required.');
        }

        if (!$this->isValidDate($date)) {
            throw new Exception('Invalid visit date.');
        }

        if ($date < date('Y-m-d')) {
            throw new Exception('Visit date cannot be in the past.');
        }

        $capacity = $this->repo->getActiveTechnicianCapacity();
        $usageRows = $this->repo->getVisitSlotUsageByDate($date);

        $usage = [];

        foreach ($usageRows as $row) {
            $slotTime = (string)($row['slot_time'] ?? '');
            $bookedCount = (int)($row['booked_count'] ?? 0);

            if ($slotTime !== '') {
                $usage[$slotTime] = $bookedCount;
            }
        }

        $slots = [];
        $start = strtotime($date . ' 08:00:00');
        $end = strtotime($date . ' 17:00:00');

        if ($date === date('Y-m-d')) {
            $minimumTime = time() + (2 * 60 * 60);
            $minutes = (int)date('i', $minimumTime);
            $remainder = $minutes % 15;

            if ($remainder > 0) {
                $minimumTime += (15 - $remainder) * 60;
            }

            $minimumTime = strtotime(date('Y-m-d H:i:00', $minimumTime));

            if ($minimumTime > $start) {
                $start = $minimumTime;
            }
        }

        for ($time = $start; $time <= $end; $time += 15 * 60) {
            $value = date('H:i', $time);
            $booked = (int)($usage[$value] ?? 0);
            $available = max(0, $capacity - $booked);

            if ($available <= 0) {
                continue;
            }

            $slots[] = [
                'value' => $value,
                'label' => date('h:i A', $time),
                'available' => $available,
                'capacity' => $capacity,
                'booked' => $booked,
            ];
        }

        return [
            'date' => $date,
            'capacity' => $capacity,
            'slots' => $slots,
        ];
    }

    public function createTicket(array $sessionData, array $input): array
    {
        $context = $this->getSessionSubscriber($sessionData);

        $subscriberId = (int)$context['subscriber']['id'];
        $userId = (int)($context['session']['user_id'] ?? 0);

        $category = strtoupper(trim((string)($input['category'] ?? 'INTERNET')));
        $subject = trim((string)($input['subject'] ?? ''));
        $description = trim((string)($input['description'] ?? ''));

        $allowedCategories = [
            'INTERNET',
            'BILLING',
            'ACCOUNT',
            'OTHERS',
        ];

        if (!in_array($category, $allowedCategories, true)) {
            $category = 'OTHERS';
        }

        if ($subject === '') {
            throw new Exception('Subject is required.');
        }

        if (strlen($subject) > 255) {
            throw new Exception('Subject must not exceed 255 characters.');
        }

        if ($description === '') {
            throw new Exception('Description is required.');
        }

        $ticketNo = $this->repo->generateTicketNo();

        $ticketId = $this->repo->createTicket([
            'ticket_no' => $ticketNo,
            'subscriber_id' => $subscriberId,
            'service_id' => null,
            'category' => $category,
            'subject' => $subject,
            'description' => $description,
            'priority' => $category === 'INTERNET' ? 'HIGH' : 'MEDIUM',
            'preferred_visit_date' => null,
            'preferred_visit_time' => null,
            'preferred_visit_notes' => null,
            'status' => 'OPEN',
            'created_by_type' => 'SUBSCRIBER',
            'created_by_user_id' => $userId,
        ]);

        if ($ticketId <= 0) {
            throw new Exception('Failed to create ticket.');
        }

        $this->repo->createTicketMessage([
            'ticket_id' => $ticketId,
            'sender_type' => 'SUBSCRIBER',
            'sender_user_id' => $userId,
            'message' => $description,
            'is_internal' => 0,
        ]);

        $autoAssignment = $this->autoAssignTicketAfterCreation($ticketId, $category);

        return [
            'message' => "Ticket {$ticketNo} created successfully.",
            'ticket_id' => $ticketId,
            'ticket_no' => $ticketNo,
            'preferred_visit_date' => null,
            'preferred_visit_time' => null,
            'work_order' => null,
            'auto_assignment' => $autoAssignment,
        ];
    }

    public function scheduleVisit(array $sessionData, array $input): array
    {
        $context = $this->getSessionSubscriber($sessionData);

        $subscriberId = (int)$context['subscriber']['id'];
        $userId = (int)($context['session']['user_id'] ?? 0);

        $ticketId = (int)($input['ticket_id'] ?? 0);
        $date = trim((string)($input['preferred_visit_date'] ?? ''));
        $time = trim((string)($input['preferred_visit_time'] ?? ''));
        $notes = trim((string)($input['preferred_visit_notes'] ?? ''));

        if ($ticketId <= 0) {
            throw new Exception('Invalid ticket ID.');
        }

        if ($date === '') {
            throw new Exception('Preferred visit date is required.');
        }

        if (!$this->isValidDate($date)) {
            throw new Exception('Invalid preferred visit date.');
        }

        if ($date < date('Y-m-d')) {
            throw new Exception('Preferred visit date cannot be in the past.');
        }

        if ($time === '') {
            throw new Exception('Preferred visit time is required.');
        }

        if (!$this->isValidTime($time)) {
            throw new Exception('Invalid preferred visit time.');
        }

        if (!$this->isAllowedVisitTime($time)) {
            throw new Exception('Preferred visit time must be between 08:00 AM and 05:00 PM in 15-minute intervals.');
        }

        if ($date === date('Y-m-d') && strtotime($date . ' ' . $time . ':00') <= time()) {
            throw new Exception('Selected visit time has already passed. Please choose another time.');
        }

        if (strlen($notes) > 1000) {
            throw new Exception('Visit notes must not exceed 1000 characters.');
        }

        $ticket = $this->repo->findTicketForSubscriber($ticketId, $subscriberId);

        if (!$ticket) {
            throw new Exception('Ticket not found.');
        }

        $status = strtoupper((string)($ticket['status'] ?? ''));

        if ($status !== 'WAITING_CUSTOMER_SCHEDULE') {
            throw new Exception('This ticket is not waiting for visit schedule.');
        }

        if (!empty($ticket['work_order_id'])) {
            throw new Exception('This ticket already has a work order.');
        }

        $availableSlots = $this->visitSlots($sessionData, [
            'date' => $date,
        ]);

        $slotValues = array_map(
            fn(array $slot) => (string)$slot['value'],
            $availableSlots['slots'] ?? []
        );

        if (!in_array($time, $slotValues, true)) {
            throw new Exception('Selected visit time is no longer available. Please choose another time.');
        }

        $ok = $this->repo->updateTicketVisitSchedule(
            $ticketId,
            $date,
            $time,
            $notes !== '' ? $notes : null,
            'VISIT_SCHEDULED'
        );

        if (!$ok) {
            throw new Exception('Failed to save visit schedule.');
        }

        $message = "Preferred technician visit schedule submitted:\nDate: {$date}\nTime: {$time}";

        if ($notes !== '') {
            $message .= "\nNotes: {$notes}";
        }

        $this->repo->createTicketMessage([
            'ticket_id' => $ticketId,
            'sender_type' => 'SUBSCRIBER',
            'sender_user_id' => $userId,
            'message' => $message,
            'is_internal' => 0,
        ]);

        $workOrder = $this->createWorkOrderFromTicket($ticketId, $userId);
        $workOrderId = (int)($workOrder['work_order_id'] ?? $workOrder['id'] ?? 0);

        if ($workOrderId <= 0) {
            throw new Exception('Visit schedule was saved, but work order creation failed.');
        }

        $this->repo->updateTicketWorkOrderId($ticketId, $workOrderId);

        return [
            'message' => 'Visit schedule submitted successfully. Work order has been created.',
            'ticket_id' => $ticketId,
            'scheduled_date' => $date,
            'scheduled_time' => $time,
            'work_order_id' => $workOrderId,
            'work_order' => $workOrder,
        ];
    }

    public function ticketDetails(array $sessionData, int $ticketId): array
    {
        if ($ticketId <= 0) {
            throw new Exception('Invalid ticket ID.');
        }

        $context = $this->getSessionSubscriber($sessionData);
        $subscriberId = (int)$context['subscriber']['id'];

        $ticket = $this->repo->findTicketForSubscriber($ticketId, $subscriberId);

        if (!$ticket) {
            throw new Exception('Ticket not found.');
        }

        return [
            'ticket' => $ticket,
            'messages' => $this->repo->getTicketMessages($ticketId, false),
        ];
    }

    public function replyTicket(array $sessionData, array $input): array
    {
        $context = $this->getSessionSubscriber($sessionData);

        $subscriberId = (int)$context['subscriber']['id'];
        $userId = (int)($context['session']['user_id'] ?? 0);

        $ticketId = (int)($input['ticket_id'] ?? 0);
        $message = trim((string)($input['message'] ?? ''));

        if ($ticketId <= 0) {
            throw new Exception('Invalid ticket ID.');
        }

        if ($message === '') {
            throw new Exception('Reply message is required.');
        }

        $ticket = $this->repo->findTicketForSubscriber($ticketId, $subscriberId);

        if (!$ticket) {
            throw new Exception('Ticket not found.');
        }

        $status = strtoupper((string)($ticket['status'] ?? ''));

        if (in_array($status, ['CLOSED', 'CANCELLED'], true)) {
            throw new Exception('This ticket is already closed and cannot be replied to.');
        }

        $messageId = $this->repo->createTicketMessage([
            'ticket_id' => $ticketId,
            'sender_type' => 'SUBSCRIBER',
            'sender_user_id' => $userId,
            'message' => $message,
            'is_internal' => 0,
        ]);

        if ($status === 'WAITING_CUSTOMER') {
            $this->repo->updateTicketStatus($ticketId, 'OPEN');
        }

        return [
            'message' => 'Reply sent successfully.',
            'message_id' => $messageId,
            'ticket_id' => $ticketId,
            'ticket_no' => $ticket['ticket_no'] ?? null,
        ];
    }

    private function autoAssignTicketAfterCreation(int $ticketId, string $category): ?array
    {
        try {
            $assignee = $this->repo->findBestAvailableAssignee($category);

            if (!$assignee || empty($assignee['id'])) {
                $this->repo->createTicketMessage([
                    'ticket_id' => $ticketId,
                    'sender_type' => 'SYSTEM',
                    'sender_user_id' => null,
                    'message' => 'No available staff found for auto-assignment. Ticket is waiting for manual assignment.',
                    'is_internal' => 1,
                ]);

                return null;
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

            return [
                'assigned_user_id' => $assignedUserId,
                'assigned_user_name' => $assigneeName,
                'role' => $assignee['role'] ?? null,
            ];
        } catch (\Throwable $e) {
            $this->repo->createTicketMessage([
                'ticket_id' => $ticketId,
                'sender_type' => 'SYSTEM',
                'sender_user_id' => null,
                'message' => 'Auto-assignment failed: ' . $e->getMessage(),
                'is_internal' => 1,
            ]);

            return null;
        }
    }

    private function createWorkOrderFromTicket(int $ticketId, int $userId): ?array
    {
        $workOrdersRepo = new \App\Modules\WorkOrders\Repositories\WorkOrdersRepository(
            new \Framework\DatabaseConnection()
        );

        $workOrdersService = new \App\Modules\WorkOrders\Services\WorkOrdersService($workOrdersRepo);

        return $workOrdersService->createFromTicket([
            'id' => $userId,
            'user_id' => $userId,
            'username' => 'subscriber_portal',
            'role' => 'SUPPORT',
        ], [
            'ticket_id' => $ticketId,
        ]);
    }

    private function isValidDate(string $date): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }

        $parsed = DateTime::createFromFormat('Y-m-d', $date);

        return $parsed && $parsed->format('Y-m-d') === $date;
    }

    private function isValidTime(string $time): bool
    {
        return preg_match('/^\d{2}:\d{2}$/', $time) === 1;
    }

    private function isAllowedVisitTime(string $time): bool
    {
        if (!$this->isValidTime($time)) {
            return false;
        }

        [$hour, $minute] = array_map('intval', explode(':', $time));

        if ($hour < 8 || $hour > 17) {
            return false;
        }

        if ($hour === 17 && $minute > 0) {
            return false;
        }

        return in_array($minute, [0, 15, 30, 45], true);
    }
}