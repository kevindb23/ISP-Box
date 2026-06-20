<?php

namespace App\Modules\WorkOrders\Controllers;

use App\Modules\WorkOrders\Services\WorkOrdersService;
use Framework\Controller;
use Framework\SessionManager;
use Throwable;

class WorkOrdersApiController extends Controller
{
    private WorkOrdersService $service;

    public function __construct(WorkOrdersService $service)
    {
        $this->service = $service;
    }

    public function index(): void
    {
        try {
            $this->json([
                'success' => true,
                'data' => $this->service->workOrders(
                    SessionManager::user() ?? [],
                    $_GET
                ),
            ]);
        } catch (Throwable $e) {
            $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(int $id): void
    {
        try {
            $this->json([
                'success' => true,
                'data' => $this->service->workOrderDetails(
                    SessionManager::user() ?? [],
                    $id
                ),
            ]);
        } catch (Throwable $e) {
            $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function createFromTicket(): void
    {
        try {
            $this->json([
                'success' => true,
                'data' => $this->service->createFromTicket(
                    SessionManager::user() ?? [],
                    $_POST
                ),
            ]);
        } catch (Throwable $e) {
            $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function assign(): void
    {
        try {
            $this->json([
                'success' => true,
                'data' => $this->service->assign(
                    SessionManager::user() ?? [],
                    $_POST
                ),
            ]);
        } catch (Throwable $e) {
            $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function updateStatus(): void
    {
        try {
            $this->json([
                'success' => true,
                'data' => $this->service->updateStatus(
                    SessionManager::user() ?? [],
                    $_POST
                ),
            ]);
        } catch (Throwable $e) {
            $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function completeTask(): void
    {
        try {
            $this->json([
                'success' => true,
                'data' => $this->service->completeTask(
                    SessionManager::user() ?? [],
                    $_POST
                ),
            ]);
        } catch (Throwable $e) {
            $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function reopenTask(): void
    {
        try {
            $this->json([
                'success' => true,
                'data' => $this->service->reopenTask(
                    SessionManager::user() ?? [],
                    $_POST
                ),
            ]);
        } catch (Throwable $e) {
            $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function json(array $payload, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');

        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}