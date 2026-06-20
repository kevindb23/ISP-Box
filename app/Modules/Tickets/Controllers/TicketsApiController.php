<?php

namespace App\Modules\Tickets\Controllers;

use App\Modules\Tickets\Repositories\TicketsRepository;
use App\Modules\Tickets\Services\TicketsService;
use Framework\ApiController;
use Framework\DatabaseConnection;
use Framework\SessionManager;
use Throwable;

class TicketsApiController extends ApiController
{
    private TicketsService $service;

    public function __construct(DatabaseConnection $database)
    {
        $repo = new TicketsRepository($database);

        $this->service = new TicketsService($repo);
    }

    public function index(): void
    {
        try {
            $filters = [
                'status' => $_GET['status'] ?? null,
                'search' => $_GET['search'] ?? null,
                'limit' => $_GET['limit'] ?? 100,
                'offset' => $_GET['offset'] ?? 0,
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
            $result = $this->service->replyTicket($this->sessionUser(), $this->input());

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
            $result = $this->service->internalNote($this->sessionUser(), $this->input());

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
            $result = $this->service->updateStatus($this->sessionUser(), $this->input());

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
            $result = $this->service->updatePriority($this->sessionUser(), $this->input());

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
            $result = $this->service->assignTicket($this->sessionUser(), $this->input());

            $this->success(
                $result,
                $result['message'] ?? 'Ticket assigned successfully.'
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 422);
        }
    }

    private function input(): array
    {
        $json = json_decode(file_get_contents('php://input'), true);

        if (is_array($json)) {
            return array_merge($_POST, $json);
        }

        return $_POST;
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