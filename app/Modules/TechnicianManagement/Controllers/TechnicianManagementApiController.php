<?php

namespace App\Modules\TechnicianManagement\Controllers;

use App\Modules\TechnicianManagement\Services\TechnicianManagementService;
use Framework\Controller;
use Framework\SessionManager;
use Throwable;

class TechnicianManagementApiController extends Controller
{
    private TechnicianManagementService $service;

    public function __construct(TechnicianManagementService $service)
    {
        $this->service = $service;
    }

    public function index(): void
    {
        try {
            $this->json([
                'success' => true,
                'data' => $this->service->dashboard(SessionManager::user() ?? [], $_GET),
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
                'data' => $this->service->technicianDetails(SessionManager::user() ?? [], $id),
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
                'data' => $this->service->updateStatus(SessionManager::user() ?? [], $_POST),
            ]);
        } catch (Throwable $e) {
            $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function updateProfile(): void
    {
        try {
            $this->json([
                'success' => true,
                'data' => $this->service->updateProfile(SessionManager::user() ?? [], $_POST),
            ]);
        } catch (Throwable $e) {
            $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function dispatch(): void
    {
        try {
            $this->json([
                'success' => true,
                'data' => $this->service->dispatchBoard(SessionManager::user() ?? []),
            ]);
        } catch (Throwable $e) {
            $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function assignWorkOrder(): void
    {
        try {
            $this->json([
                'success' => true,
                'data' => $this->service->assignWorkOrder(SessionManager::user() ?? [], $_POST),
            ]);
        } catch (Throwable $e) {
            $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function updateWorkOrderStatus(): void
    {
        try {
            $this->json([
                'success' => true,
                'data' => $this->service->updateWorkOrderStatus(SessionManager::user() ?? [], $_POST),
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