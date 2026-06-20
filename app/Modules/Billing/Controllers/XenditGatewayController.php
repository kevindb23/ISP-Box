<?php

namespace App\Modules\Billing\Controllers;

use App\Modules\Billing\Services\XenditGatewayService;
use Framework\Controller;
use Framework\DatabaseConnection;
use Throwable;

class XenditGatewayController extends Controller
{
    private XenditGatewayService $service;

    public function __construct(DatabaseConnection $database)
    {
        $this->service = new XenditGatewayService($database->get());
    }

    public function createPaymentLink(): void
    {
        try {
            $payload = $this->input();
            $invoiceId = (int)($payload['invoice_id'] ?? $_POST['invoice_id'] ?? 0);

            if ($invoiceId <= 0) {
                throw new \Exception('Invoice ID is required.');
            }

            $this->respond([
                'success' => true,
                'message' => 'Xendit payment link created.',
                'data' => $this->service->createPaymentLink($invoiceId),
            ]);
        } catch (Throwable $e) {
            http_response_code(422);

            $this->respond([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function webhook(): void
    {
        try {
            $payload = $this->input();
            $callbackToken = $_SERVER['HTTP_X_CALLBACK_TOKEN'] ?? null;

            $result = $this->service->handleWebhook($payload, $callbackToken);

            $this->respond([
                'success' => true,
                'message' => 'Webhook received.',
                'data' => $result,
            ]);
        } catch (Throwable $e) {
            http_response_code(400);

            $this->respond([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
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

    private function respond(array $payload): void
    {
        header('Content-Type: application/json');
        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}