<?php

namespace App\Modules\Billing\Controllers;

use App\Modules\Billing\Repositories\BillingSettingsRepository;
use App\Modules\Billing\Repositories\InvoiceRepository;
use App\Modules\Billing\Repositories\PaymentRepository;
use App\Modules\Billing\Services\PaymentService;
use Framework\ApiController;
use Framework\DatabaseConnection;
use Throwable;

class PaymentApiController extends ApiController
{
    private PaymentService $service;

    public function __construct(DatabaseConnection $database)
    {
        $db = $database->get();

        $paymentRepo = new PaymentRepository($db);
        $invoiceRepo = new InvoiceRepository($db);
        $settingsRepo = new BillingSettingsRepository($db);

        $this->service = new PaymentService(
            $db,
            $paymentRepo,
            $invoiceRepo,
            $settingsRepo
        );
    }

    public function list(): void
    {
        try {
            $filters = [
                'payment_status' => $_GET['payment_status'] ?? null,
                'invoice_id' => $_GET['invoice_id'] ?? null,
                'subscriber_id' => $_GET['subscriber_id'] ?? null,
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

            if (!isset($payload['received_by'])) {
                $payload['received_by'] = $_SESSION['user']['id'] ?? null;
            }

            $this->success($this->service->create($payload), 'Payment recorded.');
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 422);
        }
    }

    public function byInvoice($invoiceId): void
    {
        try {
            $this->success($this->service->getByInvoice((int)$invoiceId));
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 404);
        }
    }

    public function void($id): void
    {
        try {
            $payload = $this->input();

            $userId = $_SESSION['user']['id'] ?? null;
            $reason = trim((string)($payload['reason'] ?? $payload['void_reason'] ?? ''));

            $this->success(
                $this->service->void(
                    (int)$id,
                    $userId ? (int)$userId : null,
                    $reason !== '' ? $reason : null
                ),
                'Payment voided.'
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 422);
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