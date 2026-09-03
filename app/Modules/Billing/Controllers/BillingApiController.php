<?php

namespace App\Modules\Billing\Controllers;

use App\Modules\Billing\DTOs\BillingSettingsDTO;
use App\Modules\Billing\Services\BillingCycleService;
use App\Modules\Billing\Services\BillingService;
use App\Modules\Billing\Validators\CreateInvoicesValidator;
use Framework\ApiController;
use Throwable;

class BillingApiController extends ApiController
{
    private BillingService $service;

    public function __construct(
        BillingService $service,
        private BillingCycleService $cycleService,
        private CreateInvoicesValidator $validator
    )
    {
        $this->service = $service;
    }

    public function overview(): void
    {
        try {
            $this->success($this->service->overview());
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    public function generateDueInvoices(): void
    {
        try {
            $query = $this->request()->query();
            $asOfDate = $query['as_of_date'] ?? date('Y-m-d');
            $limit = isset($query['limit']) ? (int)$query['limit'] : 200;

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $asOfDate)) {
                $this->error('Invalid as_of_date format. Use YYYY-MM-DD.', 422);
                return;
            }

            if ($limit <= 0) {
                $limit = 200;
            }

            $userId = $_SESSION['user']['id'] ?? null;

            $result = $this->cycleService->generateDueInvoices(
                $asOfDate,
                $limit,
                'MANUAL',
                $userId ? (int)$userId : null
            );

            $this->success($result, 'Due invoices generated.');
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    public function billingRuns(): void
    {
        try {
            $query = $this->request()->query();
            $filters = [
                'run_type' => $query['run_type'] ?? null,
                'status' => $query['status'] ?? null,
                'as_of_date' => $query['as_of_date'] ?? null,
                'limit' => $query['limit'] ?? 50,
                'offset' => $query['offset'] ?? 0,
            ];

            $this->success($this->cycleService->listRuns($filters));
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    public function billingRunShow($id): void
    {
        try {
            $this->success($this->cycleService->showRun((int)$id));
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 404);
        }
    }

    public function settings(): void
    {
        try {
            $this->success($this->service->settings());
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    public function saveSettings(): void
    {
        try {
            $dto = new BillingSettingsDTO($this->request()->input());
            $errors = $this->validator->settings($dto);
            if ($errors !== []) {
                $this->error('Please correct the highlighted fields.', 422, $errors);
                return;
            }
            $this->success($this->service->saveSettings($dto->toArray()), 'Billing settings saved.');
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 422);
        }
    }

    public function supportSubscribers(): void
    {
        try {
            $this->success($this->service->supportSubscribers());
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    public function supportServices(): void
    {
        try {
            $query = $this->request()->query();
            $subscriberId = isset($query['subscriber_id']) ? (int)$query['subscriber_id'] : null;
            $this->success($this->service->supportServices($subscriberId));
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    public function supportPlans(): void
    {
        try {
            $this->success($this->service->supportPlans());
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 500);
        }
    }

}
