<?php

namespace App\Modules\PaymentGateway\Services;

use App\Modules\Audit\DTOs\AuditEventDTO;
use App\Modules\Audit\Services\AuditService;
use App\Modules\PaymentGateway\DTOs\GatewaySettingsDTO;
use App\Modules\PaymentGateway\DTOs\PaymentGatewayCommandDTO;
use App\Modules\PaymentGateway\DTOs\PayMongoWebhookDTO;
use App\Modules\PaymentGateway\Entities\GatewayTransaction;
use App\Modules\PaymentGateway\Repositories\PaymentGatewayRepository;
use App\Modules\PaymentGateway\Validators\PaymentGatewayValidator;
use RuntimeException;
use Framework\SessionManager;

class PaymentGatewayService
{
    private PaymentGatewayRepository $repo;

    public function __construct(
        PaymentGatewayRepository $repo,
        private PaymentGatewayValidator $validator,
        private AuditService $audit
    )
    {
        $this->repo = $repo;
    }

    public function getSettings(bool $includeSecrets = false): array
    {
        $settings = $this->repo->getSettingsMap();

        if (!$includeSecrets) {
            $settings['paymongo_secret_key_configured'] =
                trim((string)($settings['paymongo_secret_key'] ?? '')) !== '';
            unset($settings['paymongo_secret_key']);
            $settings['paymongo_webhook_secret_configured'] =
                trim((string)($settings['paymongo_webhook_secret'] ?? '')) !== '';
            unset($settings['paymongo_webhook_secret']);
        }

        return $settings;
    }

    public function saveSettings(GatewaySettingsDTO $settings): array
    {
        $errors = $this->validator->settings($settings);
        if ($errors !== []) throw new RuntimeException((string)reset($errors));

        $old = $this->getSettings();
        foreach ($settings->toPersistenceArray() as $key => $value) {
            $this->repo->saveSetting($key, $value);
        }

        $updated = $this->getSettings();
        $this->audit->logEvent(new AuditEventDTO(
            module: 'PAYMENT_GATEWAY', action: 'UPDATE_SETTINGS', description: 'Updated PayMongo gateway settings.',
            objectType: 'PAYMENT_GATEWAY_SETTINGS', oldValues: $old, newValues: $settings->toAuditArray()
        ));
        return $updated;
    }

    public function listTransactions(array $filters = []): array
    {
        return array_map(
            static fn(array $row): array => (new GatewayTransaction($row))->toArray(),
            $this->repo->listTransactions($filters)
        );
    }

    public function createPayMongoCheckout(PaymentGatewayCommandDTO $command): array
    {
        $errors = $this->validator->checkout($command);
        if ($errors !== []) throw new RuntimeException((string)reset($errors));
        $invoiceId = $command->invoiceId;

        if (!$this->repo->acquireCheckoutLock($invoiceId)) {
            throw new RuntimeException('Another checkout request is already being processed for this invoice.');
        }

        try {
        $invoice = $this->repo->findInvoiceById($invoiceId);

        if (!$invoice) {
            throw new RuntimeException('Invoice not found.');
        }

        $this->assertSubscriberOwnsInvoice($invoice);

        $balance = round((float)($invoice['balance_amount'] ?? 0), 2);

        if ($balance <= 0) {
            throw new RuntimeException('Invoice has no remaining balance.');
        }

        $existingTransaction = $this->repo->findReusablePendingTransactionByInvoiceId($invoiceId);

        if ($existingTransaction && abs((float)$existingTransaction['amount'] - $balance) < 0.005) {
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

        $settings = $this->getSettings(true);

        if ((int)($settings['paymongo_enabled'] ?? 0) !== 1) {
            throw new RuntimeException('PayMongo is disabled.');
        }

        $secretKey = trim((string)($settings['paymongo_secret_key'] ?? ''));

        if ($secretKey === '') {
            throw new RuntimeException('PayMongo secret key is not configured.');
        }

        $successUrl = $this->buildPortalUrl('/subscriber-portal?payment=success&invoice_id=' . $invoiceId);
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

        $this->audit->logEvent(new AuditEventDTO(
            module: 'PAYMENT_GATEWAY', action: 'CREATE_CHECKOUT', description: "Created PayMongo checkout for invoice {$invoiceId}.",
            objectType: 'PAYMENT_GATEWAY_TRANSACTION', objectId: $transactionId,
            newValues: ['invoice_id' => $invoiceId, 'amount' => $balance, 'currency' => 'PHP', 'status' => 'PENDING']
        ));

        return [
            'transaction_id' => $transactionId,
            'gateway' => 'PAYMONGO',
            'reference' => $checkoutId,
            'checkout_url' => $checkoutUrl,
            'status' => 'PENDING',
            'amount' => $balance,
            'currency' => 'PHP',
        ];
        } finally {
            $this->repo->releaseCheckoutLock($invoiceId);
        }
    }

    public function verifyPayMongoPayment(PaymentGatewayCommandDTO $command): array
    {
        $reference = $command->reference;

        if ($reference !== '') {
            $transaction = $this->repo->findTransactionByReference($reference);
        } elseif ($command->invoiceId > 0) {
            $subscriberId = $this->currentSubscriberId();
            $transaction = $this->repo->findPendingPayMongoTransactionForInvoice($command->invoiceId, $subscriberId);
        } else {
            $subscriberId = $this->currentSubscriberId();
            $transaction = $this->repo->findLatestPendingPayMongoTransaction($subscriberId);
        }

        if (!$transaction) {
            throw new RuntimeException('No pending PayMongo transaction found.');
        }

        if ($transaction) {
            $this->assertSubscriberOwnsTransaction($transaction);
        }

        $settings = $this->getSettings(true);
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

        if ($isPaid) {
            $expectedCentavos = (int)round((float)$transaction['amount'] * 100);
            $paidCentavos = $this->verifiedPayMongoAmount($attributes);
            $currency = strtoupper((string)(
                $attributes['payments'][0]['attributes']['currency']
                ?? $attributes['payment_intent']['attributes']['currency']
                ?? $attributes['currency']
                ?? ''
            ));
            $metadataInvoiceId = (int)($attributes['metadata']['invoice_id'] ?? 0);

            if ($paidCentavos === null || $paidCentavos !== $expectedCentavos) {
                throw new RuntimeException('Verified PayMongo amount does not match the gateway transaction.');
            }
            if ($currency !== 'PHP') {
                throw new RuntimeException('Verified PayMongo currency does not match the gateway transaction.');
            }
            if ($metadataInvoiceId > 0 && $metadataInvoiceId !== (int)$transaction['invoice_id']) {
                throw new RuntimeException('Verified PayMongo invoice metadata does not match the gateway transaction.');
            }
        }

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

        $this->audit->logEvent(new AuditEventDTO(
            module: 'PAYMENT_GATEWAY', action: 'VERIFY_PAYMENT', description: 'Verified and posted PayMongo payment.',
            objectType: 'PAYMENT_GATEWAY_TRANSACTION', objectId: (int)$transaction['id'],
            oldValues: ['status' => (string)($transaction['gateway_status'] ?? '')],
            newValues: ['status' => 'PAID', 'reference' => $checkoutId]
        ));

        return [
            'success' => true,
            'paid' => true,
            'message' => 'PayMongo payment verified and posted successfully.',
            'gateway_reference' => $checkoutId,
            'gateway_status' => 'PAID',
            'posting' => $posted,
        ];
    }

    public function handlePayMongoWebhook(PayMongoWebhookDTO $webhook): array
    {
        $this->assertValidPayMongoSignature($webhook);
        $errors = $this->validator->webhook($webhook);
        if ($errors !== []) throw new RuntimeException((string)reset($errors));
        $eventType = $webhook->eventType;
        $checkoutId = $webhook->checkoutId;

        if ($eventType === 'checkout_session.payment.paid') {
            // Never trust payment state supplied by the callback alone. Confirm
            // it directly with PayMongo before posting the internal payment.
            return $this->verifyPayMongoPayment(new PaymentGatewayCommandDTO(reference: $checkoutId));
        }

        $transaction = $this->repo->findTransactionByReference($checkoutId);

        if (!$transaction) {
            throw new RuntimeException('Transaction not found for webhook reference.');
        }

        $this->audit->logEvent(new AuditEventDTO(
            module: 'PAYMENT_GATEWAY', action: 'IGNORE_UNVERIFIED_WEBHOOK', description: "Ignored non-payment PayMongo webhook {$eventType}.",
            objectType: 'PAYMENT_GATEWAY_TRANSACTION', objectId: (int)$transaction['id'],
            metadata: ['event_type' => $eventType, 'reference' => $checkoutId],
            oldValues: ['status' => (string)($transaction['gateway_status'] ?? '')]
        ));

        return [
            'transaction_id' => (int)$transaction['id'],
            'gateway_reference' => $checkoutId,
            'event_type' => $eventType,
            'status' => (string)($transaction['gateway_status'] ?? 'PENDING'),
            'ignored' => true,
        ];
    }

    private function buildPortalUrl(string $path): string
    {
        $config = require BASE_PATH . '/config/app.php';
        $baseUrl = rtrim((string)($config['app_url'] ?? ''), '/');

        if ($baseUrl === '') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
                ? 'https'
                : 'http';
            $host = (string)($_SERVER['SERVER_NAME'] ?? 'localhost');

            if (!preg_match('/^[a-z0-9.-]+$/i', $host)) {
                throw new RuntimeException('Unable to determine a safe application URL.');
            }

            $baseUrl = $scheme . '://' . $host;
        }

        return $baseUrl . '/' . ltrim($path, '/');
    }

    private function verifiedPayMongoAmount(array $attributes): ?int
    {
        $candidates = [
            $attributes['payments'][0]['attributes']['amount'] ?? null,
            $attributes['payment_intent']['attributes']['amount'] ?? null,
            $attributes['amount'] ?? null,
        ];
        foreach ($candidates as $candidate) {
            if (is_int($candidate) || (is_string($candidate) && ctype_digit($candidate))) {
                return (int)$candidate;
            }
        }

        if (is_array($attributes['line_items'] ?? null)) {
            $total = 0;
            foreach ($attributes['line_items'] as $item) {
                $itemAttributes = is_array($item['attributes'] ?? null) ? $item['attributes'] : $item;
                $amount = $itemAttributes['amount'] ?? null;
                $quantity = $itemAttributes['quantity'] ?? 1;
                if (!is_numeric($amount) || !is_numeric($quantity)) return null;
                $total += (int)$amount * (int)$quantity;
            }
            return $total > 0 ? $total : null;
        }

        return null;
    }

    private function currentSubscriberId(): ?int
    {
        if (strtoupper((string)SessionManager::role()) !== 'SUBSCRIBER') {
            return null;
        }

        $userId = (int)(SessionManager::id() ?? 0);
        return $userId > 0 ? $this->repo->findSubscriberIdByUserId($userId) : null;
    }

    private function assertValidPayMongoSignature(PayMongoWebhookDTO $webhook): void
    {
        $settings = $this->getSettings(true);
        $secret = trim((string)($settings['paymongo_webhook_secret'] ?? ''));
        if ($secret === '') throw new RuntimeException('PayMongo webhook secret is not configured.');
        if ($webhook->rawPayload === '' || $webhook->signature === '') {
            throw new RuntimeException('PayMongo webhook signature is missing.');
        }

        $parts = [];
        foreach (explode(',', $webhook->signature) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($key !== '' && $value !== '') $parts[$key] = $value;
        }
        $timestamp = (int)($parts['t'] ?? 0);
        $mode = strtolower((string)($settings['paymongo_mode'] ?? 'test'));
        $provided = (string)($parts[$mode === 'live' ? 'li' : 'te'] ?? '');
        if ($timestamp <= 0 || abs(time() - $timestamp) > 300 || $provided === '') {
            throw new RuntimeException('PayMongo webhook signature is invalid or expired.');
        }
        $expected = hash_hmac('sha256', $timestamp . '.' . $webhook->rawPayload, $secret);
        if (!hash_equals($expected, $provided)) throw new RuntimeException('PayMongo webhook signature is invalid.');
    }

    private function assertSubscriberOwnsInvoice(array $invoice): void
    {
        $subscriberId = $this->currentSubscriberId();

        if ($subscriberId !== null && (int)($invoice['subscriber_id'] ?? 0) !== $subscriberId) {
            throw new RuntimeException('You are not allowed to pay this invoice.');
        }
    }

    private function assertSubscriberOwnsTransaction(array $transaction): void
    {
        $subscriberId = $this->currentSubscriberId();

        if ($subscriberId !== null && (int)($transaction['subscriber_id'] ?? 0) !== $subscriberId) {
            throw new RuntimeException('You are not allowed to verify this payment.');
        }
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
