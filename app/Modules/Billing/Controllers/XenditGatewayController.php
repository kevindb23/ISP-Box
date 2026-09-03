<?php

namespace App\Modules\Billing\Controllers;

use App\Modules\Billing\Services\XenditGatewayService;
use Framework\ApiController;
use Throwable;

class XenditGatewayController extends ApiController
{
    private XenditGatewayService $service;

    public function __construct(XenditGatewayService $service)
    {
        $this->service = $service;
    }

    public function createPaymentLink(): void
    {
        try {
            $payload = $this->request()->input();
            $invoiceId = (int)($payload['invoice_id'] ?? 0);

            if ($invoiceId <= 0) {
                throw new \Exception('Invoice ID is required.');
            }

            $this->success($this->service->createPaymentLink($invoiceId), 'Xendit payment link created.');
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 422);
        }
    }

    public function webhook(): void
    {
        try {
            $payload = $this->request()->input();
            $callbackToken = $this->request()->header('X-Callback-Token');

            $result = $this->service->handleWebhook($payload, $callbackToken);

            $this->success($result, 'Webhook received.');
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 400);
        }
    }
}
