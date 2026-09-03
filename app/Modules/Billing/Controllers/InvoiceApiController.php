<?php

namespace App\Modules\Billing\Controllers;

use App\Modules\Billing\DTOs\CreateInvoicesDTO;
use App\Modules\Billing\Services\InvoiceService;
use App\Modules\Billing\Validators\CreateInvoicesValidator;
use Framework\ApiController;
use Throwable;

class InvoiceApiController extends ApiController
{
    private InvoiceService $service;

    public function __construct(InvoiceService $service, private CreateInvoicesValidator $validator)
    {
        $this->service = $service;
    }

    public function list(): void
    {
        try {
            $query = $this->request()->query();
            $filters = [
                'status' => $query['status'] ?? null,
                'subscriber_id' => $query['subscriber_id'] ?? null,
                'service_id' => $query['service_id'] ?? null,
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
            $dto = CreateInvoicesDTO::fromArray($this->request()->input());
            $errors = $this->validator->invoice($dto);
            if ($errors !== []) {
                $this->error('Please correct the highlighted fields.', 422, $errors);
                return;
            }
            $this->success($this->service->create($dto->toArray()), 'Invoice created.');
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 422);
        }
    }

    public function cancel($id): void
    {
        try {
            $userId = $_SESSION['user']['id'] ?? null;

            $this->success(
                $this->service->cancel((int)$id, $userId ? (int)$userId : null),
                'Invoice cancelled.'
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 422);
        }
    }

    public function recalculate($id): void
    {
        try {
            $this->success($this->service->recalculate((int)$id), 'Invoice recalculated.');
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 422);
        }
    }

    public function markOverdue(): void
    {
        try {
            $payload = $this->request()->input();
            $query = $this->request()->query();

            $asOfDate = $query['as_of_date']
                ?? $payload['as_of_date']
                ?? date('Y-m-d');

            $limit = isset($query['limit'])
                ? (int)$query['limit']
                : (int)($payload['limit'] ?? 500);

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$asOfDate)) {
                $this->error('Invalid as_of_date format. Use YYYY-MM-DD.', 422);
                return;
            }

            if ($limit <= 0) {
                $limit = 500;
            }

            $this->success(
                $this->service->markOverdueInvoices((string)$asOfDate, $limit),
                'Overdue invoices updated.'
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 500);
        }
    }

}
