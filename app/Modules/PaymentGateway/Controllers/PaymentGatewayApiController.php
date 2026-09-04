<?php

namespace App\Modules\PaymentGateway\Controllers;

use App\Modules\PaymentGateway\DTOs\GatewaySettingsDTO;
use App\Modules\PaymentGateway\DTOs\PaymentGatewayCommandDTO;
use App\Modules\PaymentGateway\DTOs\PayMongoWebhookDTO;
use App\Modules\PaymentGateway\Services\PaymentGatewayService;
use Framework\ApiController;
use App\Modules\SystemMaintenance\Services\SystemMaintenanceService;
use Framework\SessionManager;
use Throwable;

class PaymentGatewayApiController extends ApiController
{
    private PaymentGatewayService $service;

    public function __construct(PaymentGatewayService $service, private SystemMaintenanceService $systemMaintenance)
    {
        $this->service = $service;
    }

    public function settings()
    {
        try {
            $this->success($this->service->getSettings(), 'Payment gateway settings loaded.');
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    public function saveSettings()
    {
        try {
            $payload = GatewaySettingsDTO::fromArray($this->request()->input());

            $this->success($this->service->saveSettings($payload), 'Payment gateway settings saved.');
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 422);
        }
    }

    public function transactions()
    {
        try {
            $this->success(['items' => $this->service->listTransactions($this->request()->query())], 'Transactions loaded.');
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    public function createCheckout()
    {
        try {
            $this->ensureSubscriberPortalOperational();
            $payload = PaymentGatewayCommandDTO::fromArray($this->request()->input());

            $this->success($this->service->createPayMongoCheckout($payload), 'PayMongo checkout created.');
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 422);
        }
    }

    public function webhook()
    {
        try {
            $request = $this->request();
            $rawPayload = $request->rawBody();
            $payload = new PayMongoWebhookDTO(
                $request->input(),
                $rawPayload,
                (string)$request->header('Paymongo-Signature', '')
            );

            $this->success($this->service->handlePayMongoWebhook($payload), 'Webhook processed.');
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 422);
        }
    }

    public function verify()
    {
        try {
            $this->ensureSubscriberPortalOperational();
            $this->success(
                $this->service->verifyPayMongoPayment(
                    PaymentGatewayCommandDTO::fromArray($this->request()->input())
                ),
                'Payment verification completed.'
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 422);
        }
    }

    private function ensureSubscriberPortalOperational(): void
    {
        $user = SessionManager::user();
        if (is_array($user) && strtoupper((string)($user['role'] ?? '')) === 'SUBSCRIBER' && $this->systemMaintenance->activeState()['active']) {
            throw new \RuntimeException('The subscriber portal is temporarily unavailable while system maintenance is in progress.');
        }
    }
}
