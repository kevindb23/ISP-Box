<?php

namespace App\Modules\PaymentGateway\Controllers;

use App\Modules\PaymentGateway\Repositories\PaymentGatewayRepository;
use App\Modules\PaymentGateway\Services\PaymentGatewayService;
use Framework\Controller;
use Throwable;

class PaymentGatewayController extends Controller
{
    private PaymentGatewayService $service;

    public function __construct(PaymentGatewayRepository $repo)
    {
        $this->service = new PaymentGatewayService($repo);
    }

    public function index()
    {
        return $this->view('PaymentGateway/index');
    }

    public function settings(): void
    {
        try {
            $this->json([
                'success' => true,
                'data' => $this->service->getSettings(),
            ]);
        } catch (Throwable $e) {
            $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function saveSettings(): void
    {
        try {
            $payload = $this->jsonInput();

            $this->json([
                'success' => true,
                'data' => $this->service->saveSettings($payload),
                'message' => 'Payment gateway settings saved.',
            ]);
        } catch (Throwable $e) {
            $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function transactions(): void
    {
        try {
            $this->json([
                'success' => true,
                'data' => [
                    'items' => $this->service->listTransactions($_GET),
                ],
            ]);
        } catch (Throwable $e) {
            $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function createCheckout(): void
    {
        try {
            $payload = $this->jsonInput();

            $this->json([
                'success' => true,
                'data' => $this->service->createPayMongoCheckout($payload),
            ]);
        } catch (Throwable $e) {
            $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function verify(): void
    {
        try {
            $payload = $this->jsonInput();

            $this->json([
                'success' => true,
                'data' => $this->service->verifyPayMongoPayment($payload),
            ]);
        } catch (Throwable $e) {
            $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
    public function webhook(): void
    {
        try {
            $payload = $this->jsonInput();

            $this->json([
                'success' => true,
                'data' => $this->service->handlePayMongoWebhook($payload),
            ]);
        } catch (Throwable $e) {
            $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    private function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');

        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function jsonInput(): array
    {
        return json_decode(file_get_contents('php://input'), true) ?: [];
    }
}