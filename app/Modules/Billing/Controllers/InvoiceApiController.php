<?php

namespace App\Modules\Billing\Controllers;

use App\Modules\Billing\Repositories\BillingSettingsRepository;
use App\Modules\Billing\Repositories\InvoiceRepository;
use App\Modules\Billing\Repositories\PaymentRepository;
use App\Modules\Billing\Services\InvoiceService;
use Framework\ApiController;
use Framework\DatabaseConnection;
use Throwable;

class InvoiceApiController extends ApiController
{
    private InvoiceService $service;

    public function __construct(DatabaseConnection $database)
    {
        $db = $database->get();

        $invoiceRepo = new InvoiceRepository($db);
        $paymentRepo = new PaymentRepository($db);
        $settingsRepo = new BillingSettingsRepository($db);

        $this->service = new InvoiceService(
            $db,
            $invoiceRepo,
            $paymentRepo,
            $settingsRepo
        );
    }

    public function list(): void
    {
        try {
            $filters = [
                'status' => $_GET['status'] ?? null,
                'subscriber_id' => $_GET['subscriber_id'] ?? null,
                'service_id' => $_GET['service_id'] ?? null,
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
            $this->success($this->service->create($payload), 'Invoice created.');
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
            $payload = $this->input();

            $asOfDate = $_GET['as_of_date']
                ?? $payload['as_of_date']
                ?? date('Y-m-d');

            $limit = isset($_GET['limit'])
                ? (int)$_GET['limit']
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