<?php

namespace App\Modules\Billing\Controllers;

use App\Modules\Billing\Repositories\BillingSettingsRepository;
use App\Modules\Billing\Services\BillingCycleService;
use App\Modules\Billing\Services\BillingService;
use Framework\ApiController;
use Framework\DatabaseConnection;
use PDO;
use Throwable;

class BillingApiController extends ApiController
{
    private PDO $db;
    private BillingService $service;

    public function __construct(DatabaseConnection $database)
    {
        $this->db = $database->get();

        $settings = new BillingSettingsRepository($this->db);
        $this->service = new BillingService($this->db, $settings);
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
            $asOfDate = $_GET['as_of_date'] ?? date('Y-m-d');
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 200;

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $asOfDate)) {
                $this->error('Invalid as_of_date format. Use YYYY-MM-DD.', 422);
                return;
            }

            if ($limit <= 0) {
                $limit = 200;
            }

            $userId = $_SESSION['user']['id'] ?? null;

            $cycleService = new BillingCycleService($this->db);
            $result = $cycleService->generateDueInvoices(
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
            $filters = [
                'run_type' => $_GET['run_type'] ?? null,
                'status' => $_GET['status'] ?? null,
                'as_of_date' => $_GET['as_of_date'] ?? null,
                'limit' => $_GET['limit'] ?? 50,
                'offset' => $_GET['offset'] ?? 0,
            ];

            $cycleService = new BillingCycleService($this->db);

            $this->success($cycleService->listRuns($filters));
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    public function billingRunShow($id): void
    {
        try {
            $cycleService = new BillingCycleService($this->db);

            $this->success($cycleService->showRun((int)$id));
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
            $payload = $this->input();
            $this->success($this->service->saveSettings($payload), 'Billing settings saved.');
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
            $subscriberId = isset($_GET['subscriber_id']) ? (int)$_GET['subscriber_id'] : null;
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