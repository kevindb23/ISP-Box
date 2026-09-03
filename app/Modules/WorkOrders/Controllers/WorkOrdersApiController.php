<?php

namespace App\Modules\WorkOrders\Controllers;

use App\Modules\WorkOrders\DTOs\WorkOrderCommandDTO;
use App\Modules\WorkOrders\Services\WorkOrdersService;
use App\Modules\WorkOrders\Validators\WorkOrderCommandValidator;
use Framework\ApiController;
use Framework\SessionManager;
use Throwable;

class WorkOrdersApiController extends ApiController
{
    private WorkOrdersService $service;

    public function __construct(WorkOrdersService $service, private WorkOrderCommandValidator $validator)
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
                    $this->request()->query()
                ),
            ]);
        } catch (Throwable $e) { $this->respondException($e); }
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
        } catch (Throwable $e) { $this->respondException($e); }
    }

    public function createFromTicket(): void
    {
        try {
            $this->json([
                'success' => true,
                'data' => $this->service->createFromTicket(
                    SessionManager::user() ?? [],
                    $this->command('create')
                ),
            ]);
        } catch (Throwable $e) { $this->respondException($e); }
    }

    public function assign(): void
    {
        try {
            $this->json([
                'success' => true,
                'data' => $this->service->assign(
                    SessionManager::user() ?? [],
                    $this->command('assign')
                ),
            ]);
        } catch (Throwable $e) { $this->respondException($e); }
    }

    public function updateStatus(): void
    {
        try {
            $this->json([
                'success' => true,
                'data' => $this->service->updateStatus(
                    SessionManager::user() ?? [],
                    $this->command('status')
                ),
            ]);
        } catch (Throwable $e) { $this->respondException($e); }
    }

    public function completeTask(): void
    {
        try {
            $this->json([
                'success' => true,
                'data' => $this->service->completeTask(
                    SessionManager::user() ?? [],
                    $this->command('complete_task')
                ),
            ]);
        } catch (Throwable $e) { $this->respondException($e); }
    }

    public function reopenTask(): void
    {
        try {
            $this->json([
                'success' => true,
                'data' => $this->service->reopenTask(
                    SessionManager::user() ?? [],
                    $this->command('reopen_task')
                ),
            ]);
        } catch (Throwable $e) {
            $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function command(string $action): array
    {
        $dto = new WorkOrderCommandDTO($this->request()->input());
        $errors = $this->validator->validate($dto, $action);
        if ($errors !== []) throw new \InvalidArgumentException((string)reset($errors));
        return $dto->toArray();
    }

    private function respondException(Throwable $e): void
    {
        $message=$e->getMessage(); $status=$e instanceof \InvalidArgumentException ? 422 : 400;
        if (str_contains(strtolower($message),'not found')) $status=404;
        if (str_contains(strtolower($message),'access') || str_contains(strtolower($message),'logged in')) $status=403;
        $this->json(['success'=>false,'error'=>$message],$status);
    }
}
