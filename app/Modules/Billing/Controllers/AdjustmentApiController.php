<?php

namespace App\Modules\Billing\Controllers;

use App\Modules\Billing\DTOs\CreateAdjustmentDTO;
use App\Modules\Billing\Services\AdjustmentService;
use App\Modules\Billing\Validators\CreateInvoicesValidator;
use Framework\ApiController;
use Throwable;

class AdjustmentApiController extends ApiController
{
    private AdjustmentService $service;

    public function __construct(AdjustmentService $service, private CreateInvoicesValidator $validator)
    {
        $this->service = $service;
    }

    public function list(): void
    {
        try {
            $query = $this->request()->query();
            $filters = [
                'invoice_id' => $query['invoice_id'] ?? null,
                'subscriber_id' => $query['subscriber_id'] ?? null,
                'service_id' => $query['service_id'] ?? null,
                'adjustment_type' => $query['adjustment_type'] ?? null,
                'status' => $query['status'] ?? null,
                'search' => $query['search'] ?? null,
                'limit' => $query['limit'] ?? 50,
                'offset' => $query['offset'] ?? 0,
            ];

            $this->success($this->service->list($filters));
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    public function show($id): void
    {
        try {
            $this->success($this->service->show((int)$id));
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 404);
        }
    }

    public function create(): void
    {
        try {
            $dto = new CreateAdjustmentDTO($this->request()->input());
            $errors = $this->validator->adjustment($dto);
            if ($errors !== []) {
                $this->error('Please correct the highlighted fields.', 422, $errors);
                return;
            }
            $payload = $dto->toArray();

            if (!isset($payload['created_by'])) {
                $payload['created_by'] = $_SESSION['user']['id'] ?? null;
            }

            $this->success(
                $this->service->create($payload),
                'Billing adjustment created.'
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 422);
        }
    }

    public function void($id): void
    {
        try {
            $payload = $this->request()->input();

            $userId = $_SESSION['user']['id'] ?? null;
            $reason = $payload['reason'] ?? $payload['void_reason'] ?? null;

            $this->success(
                $this->service->void(
                    (int)$id,
                    $userId ? (int)$userId : null,
                    $reason
                ),
                'Billing adjustment voided.'
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 422);
        }
    }

    public function byInvoice($invoiceId): void
    {
        try {
            $this->success(
                $this->service->getByInvoice((int)$invoiceId)
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 404);
        }
    }

}
