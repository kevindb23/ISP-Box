<?php

namespace App\Modules\Billing\Controllers;

use App\Modules\Billing\Repositories\AdjustmentRepository;
use App\Modules\Billing\Repositories\InvoiceRepository;
use App\Modules\Billing\Services\AdjustmentService;
use Framework\ApiController;
use Framework\DatabaseConnection;
use Throwable;

class AdjustmentApiController extends ApiController
{
    private AdjustmentService $service;

    public function __construct(DatabaseConnection $database)
    {
        $db = $database->get();

        $adjustmentRepo = new AdjustmentRepository($db);
        $invoiceRepo = new InvoiceRepository($db);

        $this->service = new AdjustmentService(
            $db,
            $adjustmentRepo,
            $invoiceRepo
        );
    }

    public function list(): void
    {
        try {
            $filters = [
                'invoice_id' => $_GET['invoice_id'] ?? null,
                'subscriber_id' => $_GET['subscriber_id'] ?? null,
                'service_id' => $_GET['service_id'] ?? null,
                'adjustment_type' => $_GET['adjustment_type'] ?? null,
                'status' => $_GET['status'] ?? null,
                'search' => $_GET['search'] ?? null,
                'limit' => $_GET['limit'] ?? 50,
                'offset' => $_GET['offset'] ?? 0,
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
            $payload = $this->input();

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
            $payload = $this->input();

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

    private function input(): array
    {
        $raw = file_get_contents('php://input');
        $json = json_decode($raw ?: '', true);

        if (is_array($json)) {
            return $json;
        }

        return $_POST ?: [];
    }
}