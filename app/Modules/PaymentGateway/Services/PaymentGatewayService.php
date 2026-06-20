<?php

namespace App\Modules\PaymentGateway\Services;

use App\Modules\PaymentGateway\Repositories\PaymentGatewayRepository;
use RuntimeException;

class PaymentGatewayService
{
    private PaymentGatewayRepository $repo;

    public function __construct(PaymentGatewayRepository $repo)
    {
        $this->repo = $repo;
    }

    public function getSettings(): array
    {
        return $this->repo->getSettingsMap();
    }

    public function saveSettings(array $payload): array
    {
        $allowed = [
            'paymongo_enabled',
            'paymongo_mode',
            'paymongo_public_key',
            'paymongo_secret_key',
        ];

        foreach ($allowed as $key) {
            if (array_key_exists($key, $payload)) {
                $value = is_scalar($payload[$key]) ? trim((string)$payload[$key]) : '';
                $this->repo->saveSetting($key, $value);
            }
        }

        return $this->getSettings();
    }

    public function listTransactions(array $filters = []): array
    {
        return $this->repo->listTransactions($filters);
    }

    public function createPayMongoCheckout(array $payload): array
    {
        $invoiceId = (int)($payload['invoice_id'] ?? 0);

        if ($invoiceId <= 0) {
            throw new RuntimeException('Invoice is required.');
        }

        $invoice = $this->repo->findInvoiceById($invoiceId);

        if (!$invoice) {
            throw new RuntimeException('Invoice not found.');
        }

        $balance = round((float)($invoice['balance_amount'] ?? 0), 2);

        if ($balance <= 0) {
            throw new RuntimeException('Invoice has no remaining balance.');
        }

        $existingTransaction = $this->repo->findReusablePendingTransactionByInvoiceId($invoiceId);

        if ($existingTransaction) {
            return [
                'transaction_id' => (int)$existingTransaction['id'],
                'gateway' => 'PAYMONGO',
                'reference' => $existingTransaction['gateway_reference'],
                'checkout_url' => $existingTransaction['gateway_payment_url'],
                'status' => $existingTransaction['gateway_status'],
                'amount' => (float)$existingTransaction['amount'],
                'currency' => $existingTransaction['currency'] ?: 'PHP',
                'reused' => true,
            ];
        }

        $settings = $this->getSettings();

        if ((int)($settings['paymongo_enabled'] ?? 0) !== 1) {
            throw new RuntimeException('PayMongo is disabled.');
        }

        $secretKey = trim((string)($settings['paymongo_secret_key'] ?? ''));

        if ($secretKey === '') {
            throw new RuntimeException('PayMongo secret key is not configured.');
        }

        $successUrl = $this->buildPortalUrl('/subscriber-portal?payment=success');
        $failedUrl = $this->buildPortalUrl('/subscriber-portal/invoices?payment=failed');

        $amountCentavos = (int)round($balance * 100);
        $invoiceNo = (string)($invoice['invoice_no'] ?? ('#' . $invoiceId));

        $requestPayload = [
            'data' => [
                'attributes' => [
                    'line_items' => [[
                        'currency' => 'PHP',
                        'amount' => $amountCentavos,
                        'name' => 'Invoice ' . $invoiceNo,
                        'quantity' => 1,
                    ]],
                    'payment_method_types' => [
                        'gcash',
                        'paymaya',
                        'card',
                    ],
                    'success_url' => $successUrl,
                    'cancel_url' => $failedUrl,
                    'description' => 'Payment for invoice ' . $invoiceNo,
                    'metadata' => [
                        'invoice_id' => (string)$invoiceId,
                        'invoice_no' => $invoiceNo,
                        'subscriber_id' => (string)($invoice['subscriber_id'] ?? ''),
                    ],
                ],
            ],
        ];

        $response = $this->paymongoRequest(
            'POST',
            'https://api.paymongo.com/v1/checkout_sessions',
            $secretKey,
            $requestPayload
        );

        $checkoutId = $response['data']['id'] ?? null;
        $checkoutUrl = $response['data']['attributes']['checkout_url'] ?? null;

        if (!$checkoutId || !$checkoutUrl) {
            throw new RuntimeException('Invalid PayMongo checkout response.');
        }

        $transactionId = $this->repo->createTransaction([
            'payment_id' => null,
            'invoice_id' => $invoiceId,
            'subscriber_id' => $invoice['subscriber_id'] ?? null,
            'gateway' => 'PAYMONGO',
            'gateway_reference' => $checkoutId,
            'gateway_payment_url' => $checkoutUrl,
            'gateway_status' => 'PENDING',
            'amount' => $balance,
            'currency' => 'PHP',
            'raw_request' => json_encode($requestPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'raw_response' => json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'raw_webhook' => null,
        ]);

        return [
            'transaction_id' => $transactionId,
            'gateway' => 'PAYMONGO',
            'reference' => $checkoutId,
            'checkout_url' => $checkoutUrl,
            'status' => 'PENDING',
            'amount' => $balance,
            'currency' => 'PHP',
        ];
    }

    public function verifyPayMongoPayment(array $payload = []): array
    {
        $reference = trim((string)($payload['reference'] ?? $payload['gateway_reference'] ?? ''));

        if ($reference !== '') {
            $transaction = $this->repo->findTransactionByReference($reference);
        } else {
            $transaction = $this->repo->findLatestPendingPayMongoTransaction();
        }

        if (!$transaction) {
            throw new RuntimeException('No pending PayMongo transaction found.');
        }

        $settings = $this->getSettings();
        $secretKey = trim((string)($settings['paymongo_secret_key'] ?? ''));

        if ($secretKey === '') {
            throw new RuntimeException('PayMongo secret key is not configured.');
        }

        $checkoutId = (string)$transaction['gateway_reference'];

        $response = $this->paymongoRequest(
            'GET',
            'https://api.paymongo.com/v1/checkout_sessions/' . rawurlencode($checkoutId),
            $secretKey
        );

        $attributes = $response['data']['attributes'] ?? [];

        $sessionPaidAt = $attributes['paid_at'] ?? null;
        $intentStatus = strtolower((string)($attributes['payment_intent']['attributes']['status'] ?? ''));
        $paymentStatus = strtolower((string)($attributes['payments'][0]['attributes']['status'] ?? ''));

        $isPaid =
            !empty($sessionPaidAt) ||
            $intentStatus === 'succeeded' ||
            $paymentStatus === 'paid';

        $rawVerification = json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (!$isPaid) {
            $this->repo->updateTransactionStatus(
                (int)$transaction['id'],
                'PENDING',
                $rawVerification
            );

            return [
                'success' => true,
                'paid' => false,
                'message' => 'PayMongo payment is not paid yet.',
                'gateway_reference' => $checkoutId,
                'gateway_status' => 'PENDING',
            ];
        }

        $posted = $this->repo->postPaidGatewayTransaction(
            (int)$transaction['id'],
            $rawVerification
        );

        return [
            'success' => true,
            'paid' => true,
            'message' => 'PayMongo payment verified and posted successfully.',
            'gateway_reference' => $checkoutId,
            'gateway_status' => 'PAID',
            'posting' => $posted,
        ];
    }

    public function handlePayMongoWebhook(array $payload): array
    {
        $eventType = (string)($payload['data']['attributes']['type'] ?? '');
        $resource = $payload['data']['attributes']['data'] ?? [];

        $checkoutId = $resource['id'] ?? null;

        if (!$checkoutId) {
            throw new RuntimeException('Webhook reference is missing.');
        }

        $transaction = $this->repo->findTransactionByReference($checkoutId);

        if (!$transaction) {
            throw new RuntimeException('Transaction not found for webhook reference.');
        }

        $rawWebhook = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($eventType === 'checkout_session.payment.paid') {
            $posted = $this->repo->postPaidGatewayTransaction(
                (int)$transaction['id'],
                $rawWebhook
            );

            return [
                'transaction_id' => (int)$transaction['id'],
                'gateway_reference' => $checkoutId,
                'event_type' => $eventType,
                'status' => 'PAID',
                'posting' => $posted,
            ];
        }

        $this->repo->updateTransactionWebhook(
            (int)$transaction['id'],
            'UPDATED',
            $rawWebhook
        );

        return [
            'transaction_id' => (int)$transaction['id'],
            'gateway_reference' => $checkoutId,
            'event_type' => $eventType,
            'status' => 'UPDATED',
        ];
    }

    private function buildPortalUrl(string $path): string
    {
        $scheme = 'http';

        if (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        ) {
            $scheme = 'https';
        }

        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return $scheme . '://' . $host . $path;
    }

    private function paymongoRequest(
        string $method,
        string $url,
        string $secretKey,
        array $payload = []
    ): array {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL extension is not installed or enabled.');
        }

        $ch = curl_init($url);

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Basic ' . base64_encode($secretKey . ':'),
            ],
            CURLOPT_TIMEOUT => 30,
        ];

        if (!empty($payload)) {
            $options[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        curl_setopt_array($ch, $options);

        $raw = curl_exec($ch);
        $error = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($raw === false || $error) {
            throw new RuntimeException('PayMongo connection failed: ' . $error);
        }

        $decoded = json_decode((string)$raw, true);

        if (!is_array($decoded)) {
            throw new RuntimeException('Invalid PayMongo response: ' . $raw);
        }

        if ($code < 200 || $code >= 300) {
            $message = $decoded['errors'][0]['detail']
                ?? $decoded['errors'][0]['title']
                ?? 'PayMongo request failed.';

            throw new RuntimeException($message);
        }

        return $decoded;
    }
}