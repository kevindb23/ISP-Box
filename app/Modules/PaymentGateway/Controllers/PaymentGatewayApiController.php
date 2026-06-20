<?php

namespace App\Modules\PaymentGateway\Controllers;

use App\Modules\PaymentGateway\Repositories\PaymentGatewayRepository;
use App\Modules\PaymentGateway\Services\PaymentGatewayService;
use Framework\Controller;
use Throwable;

class PaymentGatewayApiController extends Controller
{
    private PaymentGatewayService $service;

    public function __construct(PaymentGatewayRepository $repo)
    {
        $this->service = new PaymentGatewayService($repo);
    }

    public function settings()
    {
        try {
            return $this->json([
                'success' => true,
                'data' => $this->service->getSettings(),
            ]);
        } catch (Throwable $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function saveSettings()
    {
        try {
            $payload = $this->jsonInput();

            return $this->json([
                'success' => true,
                'data' => $this->service->saveSettings($payload),
                'message' => 'Payment gateway settings saved.',
            ]);
        } catch (Throwable $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function transactions()
    {
        try {
            return $this->json([
                'success' => true,
                'data' => [
                    'items' => $this->service->listTransactions($_GET),
                ],
            ]);
        } catch (Throwable $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function createCheckout()
    {
        try {
            $payload = $this->jsonInput();

            return $this->json([
                'success' => true,
                'data' => $this->service->createPayMongoCheckout($payload),
            ]);
        } catch (Throwable $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function webhook()
    {
        try {
            $payload = $this->jsonInput();

            return $this->json([
                'success' => true,
                'data' => $this->service->handlePayMongoWebhook($payload),
            ]);
        } catch (Throwable $e) {
            return $this->json([
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