<?php

namespace App\Modules\Tickets\Controllers;

use App\Modules\Tickets\DTOs\TicketCommandDTO;
use App\Modules\Tickets\Services\TicketsService;
use App\Modules\Tickets\Validators\TicketCommandValidator;
use Framework\ApiController;
use Framework\SessionManager;
use Throwable;

class TicketsApiController extends ApiController
{
    private TicketsService $service;

    public function __construct(TicketsService $service, private TicketCommandValidator $validator)
    {
        $this->service = $service;
    }

    public function index(): void
    {
        try {
            $query = $this->request()->query();
            $filters = [
                'status' => $query['status'] ?? null,
                'search' => $query['search'] ?? null,
                'limit' => $query['limit'] ?? 100,
                'offset' => $query['offset'] ?? 0,
            ];

            $this->success(
                $this->service->tickets($this->sessionUser(), $filters),
                'Tickets loaded.'
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 403);
        }
    }

    public function show($id): void
    {
        try {
            $this->success(
                $this->service->ticketDetails($this->sessionUser(), (int)$id),
                'Ticket loaded.'
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 403);
        }
    }

    public function reply(): void
    {
        try {
            $result = $this->service->replyTicket($this->sessionUser(), $this->command('reply'));

            $this->success(
                $result,
                $result['message'] ?? 'Reply sent successfully.'
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 422);
        }
    }

    public function internalNote(): void
    {
        try {
            $result = $this->service->internalNote($this->sessionUser(), $this->command('internal_note'));

            $this->success(
                $result,
                $result['message'] ?? 'Internal note added successfully.'
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 422);
        }
    }

    public function updateStatus(): void
    {
        try {
            $result = $this->service->updateStatus($this->sessionUser(), $this->command('status'));

            $this->success(
                $result,
                $result['message'] ?? 'Ticket status updated.'
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 422);
        }
    }

    public function updatePriority(): void
    {
        try {
            $result = $this->service->updatePriority($this->sessionUser(), $this->command('priority'));

            $this->success(
                $result,
                $result['message'] ?? 'Ticket priority updated.'
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 422);
        }
    }

    public function assign(): void
    {
        try {
            $result = $this->service->assignTicket($this->sessionUser(), $this->command('assign'));

            $this->success(
                $result,
                $result['message'] ?? 'Ticket assigned successfully.'
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 422);
        }
    }

    public function requestSchedule(): void
    {
        try {
            $dto = (new TicketCommandDTO($this->request()->input()))
                ->with('status', 'WAITING_CUSTOMER_SCHEDULE');
            $errors = $this->validator->validate($dto, 'status');
            if ($errors !== []) {
                $this->error('Please correct the highlighted fields.', 422, $errors);
                return;
            }
            $result = $this->service->updateStatus($this->sessionUser(), $dto->toArray());
            $this->success($result, 'Visit schedule requested.');
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 422);
        }
    }

    private function command(string $action): array
    {
        $dto = new TicketCommandDTO($this->request()->input());
        $errors = $this->validator->validate($dto, $action);
        if ($errors !== []) throw new \InvalidArgumentException((string)reset($errors));
        return $dto->toArray();
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
            'full_name' => (string)($user['full_name'] ?? ''),
            'role' => strtoupper((string)($user['role'] ?? '')),
        ];
    }
}
