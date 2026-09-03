<?php

namespace App\Modules\Billing\Services;

use App\Modules\Audit\DTOs\AuditEventDTO;
use App\Modules\Audit\Services\AuditService;
use App\Modules\Billing\Repositories\BillingSettingsRepository;
use App\Modules\Billing\Repositories\InvoiceRepository;
use App\Modules\Billing\Repositories\PaymentGatewayRepository;
use App\Modules\Billing\Repositories\PaymentRepository;
use Exception;
use PDO;

class XenditGatewayService
{
    private PDO $db;
    private BillingSettingsRepository $settings;
    private InvoiceRepository $invoices;
    private PaymentRepository $payments;
    private PaymentGatewayRepository $gatewayRepo;
    private PaymentService $paymentService;

    public function __construct(
        PDO $db,
        private AuditService $audit,
        BillingSettingsRepository $settings,
        InvoiceRepository $invoices,
        PaymentRepository $payments,
        PaymentGatewayRepository $gatewayRepo,
        PaymentService $paymentService
    )
    {
        $this->db = $db;
        $this->settings = $settings;
        $this->invoices = $invoices;
        $this->payments = $payments;
        $this->gatewayRepo = $gatewayRepo;
        $this->paymentService = $paymentService;
    }

    public function createPaymentLink(int $invoiceId): array
    {
        if (!$this->gatewayRepo->acquireInvoiceCheckoutLock($invoiceId)) {
            throw new Exception('Another payment-link request is already being processed for this invoice.');
        }

        try {
        $enabled = (string)$this->settings->get('xendit_enabled', '0');

        if ($enabled !== '1') {
            throw new Exception('Xendit payment gateway is disabled.');
        }

        $invoice = $this->invoices->find($invoiceId);

        if (!$invoice) {
            throw new Exception('Invoice not found.');
        }

        if (strtoupper((string)$invoice['status']) === 'PAID') {
            throw new Exception('Invoice is already paid.');
        }

        if (strtoupper((string)$invoice['status']) === 'CANCELLED') {
            throw new Exception('Cancelled invoice cannot be paid.');
        }

        $balance = (float)($invoice['balance_amount'] ?? 0);

        if ($balance <= 0) {
            throw new Exception('Invoice has no payable balance.');
        }

        $existing = $this->gatewayRepo->findReusableXenditTransaction($invoiceId);
        if ($existing) {
            return [
                'gateway' => 'XENDIT',
                'invoice_id' => $invoiceId,
                'invoice_no' => $invoice['invoice_no'] ?? null,
                'external_id' => $existing['gateway_reference'],
                'amount' => (float)$existing['amount'],
                'currency' => $existing['currency'] ?: 'PHP',
                'status' => $existing['gateway_status'],
                'payment_url' => $existing['gateway_payment_url'],
                'reused' => true,
            ];
        }

        $mode = (string)$this->settings->get('xendit_mode', 'test');
        $secretKey = $mode === 'live'
            ? (string)$this->settings->get('xendit_secret_key_live', '')
            : (string)$this->settings->get('xendit_secret_key_test', '');

        if ($secretKey === '') {
            throw new Exception('Xendit secret key is not configured.');
        }

        $externalId = sprintf(
            'NXB-INV-%d-%s',
            $invoiceId,
            strtoupper(substr(bin2hex(random_bytes(4)), 0, 8))
        );

        $successUrl = (string)$this->settings->get('xendit_success_redirect_url', '');
        $failureUrl = (string)$this->settings->get('xendit_failure_redirect_url', '');

        $payload = [
            'external_id' => $externalId,
            'amount' => round($balance, 2),
            'description' => 'NexusBox payment for invoice ' . ($invoice['invoice_no'] ?? '#' . $invoiceId),
            'invoice_duration' => 86400,
            'currency' => 'PHP',
            'customer' => [
                'given_names' => (string)($invoice['subscriber_name'] ?? 'Subscriber'),
                'email' => (string)($invoice['email'] ?? ''),
                'mobile_number' => (string)($invoice['contact_number'] ?? ''),
            ],
            'items' => [
                [
                    'name' => (string)($invoice['plan_name'] ?? 'Internet Service'),
                    'quantity' => 1,
                    'price' => round($balance, 2),
                ],
            ],
        ];

        if ($successUrl !== '') {
            $payload['success_redirect_url'] = $successUrl;
        }

        if ($failureUrl !== '') {
            $payload['failure_redirect_url'] = $failureUrl;
        }

        $response = $this->requestXendit(
            'POST',
            'https://api.xendit.co/v2/invoices',
            $payload,
            $secretKey
        );

        $paymentUrl = (string)($response['invoice_url'] ?? '');
        $xenditId = (string)($response['id'] ?? '');

        $transactionId = $this->gatewayRepo->createTransaction([
            'invoice_id' => $invoiceId,
            'subscriber_id' => $invoice['subscriber_id'] ?? null,
            'service_id' => $invoice['service_id'] ?? null,
            'gateway' => 'XENDIT',
            'gateway_reference' => $externalId,
            'gateway_payment_url' => $paymentUrl,
            'gateway_status' => (string)($response['status'] ?? 'PENDING'),
            'amount' => $balance,
            'currency' => 'PHP',
            'raw_request' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'raw_response' => json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);

        $this->audit->logEvent(new AuditEventDTO(
            module: 'PAYMENT_GATEWAY', action: 'CREATE_XENDIT_LINK', description: "Created Xendit payment link for invoice {$invoiceId}.",
            objectType: 'PAYMENT_GATEWAY_TRANSACTION', objectId: $transactionId,
            newValues: ['invoice_id' => $invoiceId, 'amount' => $balance, 'currency' => 'PHP', 'status' => (string)($response['status'] ?? 'PENDING')]
        ));

        return [
            'gateway' => 'XENDIT',
            'mode' => $mode,
            'invoice_id' => $invoiceId,
            'invoice_no' => $invoice['invoice_no'] ?? null,
            'external_id' => $externalId,
            'xendit_id' => $xenditId,
            'amount' => $balance,
            'currency' => 'PHP',
            'status' => $response['status'] ?? 'PENDING',
            'payment_url' => $paymentUrl,
            'raw' => $response,
        ];
        } finally {
            $this->gatewayRepo->releaseInvoiceCheckoutLock($invoiceId);
        }
    }

    public function handleWebhook(array $payload, ?string $callbackToken = null): array
    {
        $mode = (string)$this->settings->get('xendit_mode', 'test');
        $expectedToken = $mode === 'live'
            ? (string)$this->settings->get('xendit_webhook_token_live', '')
            : (string)$this->settings->get('xendit_webhook_token_test', '');

        if ($expectedToken === '') {
            throw new Exception('Xendit webhook token is not configured.');
        }

        if (!is_string($callbackToken) || !hash_equals($expectedToken, $callbackToken)) {
            throw new Exception('Invalid Xendit webhook token.');
        }

        $externalId = (string)($payload['external_id'] ?? '');
        $status = strtoupper((string)($payload['status'] ?? ''));

        if ($externalId === '') {
            throw new Exception('Webhook external_id is missing.');
        }

        if (!in_array($status, ['PAID', 'SETTLED'], true)) {
            $transaction = $this->gatewayRepo->findByGatewayReference('XENDIT', $externalId);

            if (!$transaction) {
                throw new Exception('Gateway transaction not found.');
            }

            $this->gatewayRepo->updateByGatewayReference('XENDIT', $externalId, [
                'gateway_status' => $status ?: 'UNKNOWN',
                'raw_webhook' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]);

            $this->audit->logEvent(new AuditEventDTO(
                module: 'PAYMENT_GATEWAY', action: 'PROCESS_XENDIT_WEBHOOK', description: 'Processed non-paid Xendit webhook.',
                objectType: 'PAYMENT_GATEWAY_TRANSACTION', objectId: (int)$transaction['id'],
                metadata: ['external_id' => $externalId],
                oldValues: ['status' => (string)($transaction['gateway_status'] ?? '')],
                newValues: ['status' => $status ?: 'UNKNOWN']
            ));

            return [
                'processed' => false,
                'reason' => 'Webhook status is not paid.',
                'status' => $status,
                'external_id' => $externalId,
            ];
        }

        $this->db->beginTransaction();

        try {
            $transaction = $this->gatewayRepo->findByGatewayReferenceForUpdate('XENDIT', $externalId);

            if (!$transaction) {
                throw new Exception('Gateway transaction not found.');
            }

            if (!empty($transaction['payment_id'])) {
                $this->db->commit();

                return [
                    'processed' => false,
                    'reason' => 'Payment already posted for this gateway transaction.',
                    'payment_id' => (int)$transaction['payment_id'],
                    'external_id' => $externalId,
                ];
            }

        $invoiceId = (int)($transaction['invoice_id'] ?? 0);
        $amount = (float)($payload['paid_amount'] ?? $payload['amount'] ?? $transaction['amount'] ?? 0);
        $expectedAmount = (float)($transaction['amount'] ?? 0);

        if ($invoiceId <= 0 || $amount <= 0) {
            throw new Exception('Invalid invoice or amount from gateway transaction.');
        }

        if ($expectedAmount <= 0 || abs($amount - $expectedAmount) > 0.01) {
            throw new Exception('Xendit payment amount does not match the gateway transaction.');
        }

            $payment = $this->paymentService->create([
            'invoice_id' => $invoiceId,
            'amount' => $amount,
            'method' => 'XENDIT',
            'reference_no' => $externalId,
            'payment_date' => date('Y-m-d H:i:s'),
            'remarks' => 'Posted automatically from Xendit webhook.',
        ]);

            $paymentId = (int)($payment['payment']['id'] ?? $payment['id'] ?? 0);

            $this->gatewayRepo->updateByGatewayReference('XENDIT', $externalId, [
            'payment_id' => $paymentId > 0 ? $paymentId : null,
            'gateway_status' => $status,
            'raw_webhook' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);

            $this->db->commit();

            $this->audit->logEvent(new AuditEventDTO(
                module: 'PAYMENT_GATEWAY', action: 'POST_XENDIT_PAYMENT', description: 'Posted payment from verified Xendit webhook.',
                objectType: 'PAYMENT', objectId: $paymentId,
                metadata: ['transaction_id' => (int)$transaction['id'], 'external_id' => $externalId],
                newValues: ['invoice_id' => $invoiceId, 'amount' => $amount, 'status' => $status]
            ));

            return [
                'processed' => true,
                'external_id' => $externalId,
                'status' => $status,
                'invoice_id' => $invoiceId,
                'payment_id' => $paymentId,
                'amount' => $amount,
            ];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }

    private function requestXendit(string $method, string $url, array $payload, string $secretKey): array
    {
        $ch = curl_init($url);

        if (!$ch) {
            throw new Exception('Unable to initialize cURL.');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Basic ' . base64_encode($secretKey . ':'),
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => 30,
        ]);

        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($errno) {
            throw new Exception('Xendit cURL error: ' . $error);
        }

        $decoded = json_decode((string)$raw, true);

        if (!is_array($decoded)) {
            throw new Exception('Invalid Xendit response: ' . (string)$raw);
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new Exception('Xendit API error: ' . ($decoded['message'] ?? (string)$raw));
        }

        return $decoded;
    }
}
