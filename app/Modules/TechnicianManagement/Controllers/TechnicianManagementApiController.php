<?php

namespace App\Modules\TechnicianManagement\Controllers;

use App\Modules\TechnicianManagement\DTOs\TechnicianCommandDTO;
use App\Modules\TechnicianManagement\Services\TechnicianManagementService;
use App\Modules\TechnicianManagement\Validators\TechnicianCommandValidator;
use Framework\ApiController;
use Framework\SessionManager;
use Throwable;

class TechnicianManagementApiController extends ApiController
{
    private TechnicianManagementService $service;

    public function __construct(TechnicianManagementService $service, private TechnicianCommandValidator $validator)
    {
        $this->service = $service;
    }

    public function index(): void
    {
        try {
            $this->json([
                'success' => true,
                'data' => $this->service->dashboard(SessionManager::user() ?? [], $this->request()->query()),
            ]);
        } catch (Throwable $e) { $this->respondException($e); }
    }

    public function show(int $id): void
    {
        try {
            $this->json([
                'success' => true,
                'data' => $this->service->technicianDetails(SessionManager::user() ?? [], $id),
            ]);
        } catch (Throwable $e) { $this->respondException($e); }
    }

    public function updateStatus(): void
    {
        try {
            $this->json([
                'success' => true,
                'data' => $this->service->updateStatus(SessionManager::user() ?? [], $this->command('status')),
            ]);
        } catch (Throwable $e) { $this->respondException($e); }
    }

    public function updateProfile(): void
    {
        try {
            $this->json([
                'success' => true,
                'data' => $this->service->updateProfile(SessionManager::user() ?? [], $this->command('profile')),
            ]);
        } catch (Throwable $e) { $this->respondException($e); }
    }

    public function dispatch(): void
    {
        try {
            $this->json([
                'success' => true,
                'data' => $this->service->dispatchBoard(SessionManager::user() ?? []),
            ]);
        } catch (Throwable $e) { $this->respondException($e); }
    }

    public function assignWorkOrder(): void
    {
        try {
            $this->json([
                'success' => true,
                'data' => $this->service->assignWorkOrder(SessionManager::user() ?? [], $this->command('assign')),
            ]);
        } catch (Throwable $e) { $this->respondException($e); }
    }

    public function updateWorkOrderStatus(): void
    {
        try {
            $this->json([
                'success' => true,
                'data' => $this->service->updateWorkOrderStatus(SessionManager::user() ?? [], $this->command('work_order_status')),
            ]);
        } catch (Throwable $e) { $this->respondException($e); }
    }

    private function command(string $action): array
    {
        $dto = new TechnicianCommandDTO($this->request()->input());
        $errors = $this->validator->validate($dto, $action);
        if ($errors !== []) throw new \InvalidArgumentException((string)reset($errors));
        return $dto->toArray();
    }

    private function respondException(Throwable $e): void
    {
        $message = $e->getMessage();
        $status = $e instanceof \InvalidArgumentException ? 422 : 400;
        if (str_contains(strtolower($message), 'not found')) $status = 404;
        if (str_contains(strtolower($message), 'access is restricted') || str_contains(strtolower($message), 'logged in')) $status = 403;
        $this->json(['success' => false, 'error' => $message], $status);
    }
}
