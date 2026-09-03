<?php

namespace App\Modules\Billing\Repositories;

use PDO;
use Throwable;

class PaymentGatewayRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function createTransaction(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO payment_gateway_transactions (
                payment_id,
                invoice_id,
                subscriber_id,
                service_id,
                gateway,
                gateway_reference,
                gateway_payment_url,
                gateway_status,
                amount,
                currency,
                raw_request,
                raw_response,
                raw_webhook
            ) VALUES (
                :payment_id,
                :invoice_id,
                :subscriber_id,
                :service_id,
                :gateway,
                :gateway_reference,
                :gateway_payment_url,
                :gateway_status,
                :amount,
                :currency,
                :raw_request,
                :raw_response,
                :raw_webhook
            )
        ");

        $stmt->execute([
            ':payment_id' => $data['payment_id'] ?? null,
            ':invoice_id' => $data['invoice_id'] ?? null,
            ':subscriber_id' => $data['subscriber_id'] ?? null,
            ':service_id' => $data['service_id'] ?? null,
            ':gateway' => $data['gateway'] ?? 'XENDIT',
            ':gateway_reference' => $data['gateway_reference'] ?? null,
            ':gateway_payment_url' => $data['gateway_payment_url'] ?? null,
            ':gateway_status' => $data['gateway_status'] ?? 'PENDING',
            ':amount' => $data['amount'] ?? 0,
            ':currency' => $data['currency'] ?? 'PHP',
            ':raw_request' => $data['raw_request'] ?? null,
            ':raw_response' => $data['raw_response'] ?? null,
            ':raw_webhook' => $data['raw_webhook'] ?? null,
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function findByGatewayReference(string $gateway, string $reference): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM payment_gateway_transactions
            WHERE gateway = :gateway
              AND gateway_reference = :gateway_reference
            LIMIT 1
        ");

        $stmt->execute([
            ':gateway' => $gateway,
            ':gateway_reference' => $reference,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findReusableXenditTransaction(int $invoiceId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM payment_gateway_transactions
            WHERE invoice_id = :invoice_id AND gateway = 'XENDIT'
              AND gateway_status IN ('PENDING','UPDATED')
              AND gateway_payment_url IS NOT NULL
              AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY id DESC LIMIT 1");
        $stmt->execute(['invoice_id' => $invoiceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function acquireInvoiceCheckoutLock(int $invoiceId, int $timeoutSeconds = 5): bool
    {
        $stmt = $this->db->prepare('SELECT GET_LOCK(:name, :timeout)');
        $stmt->bindValue(':name', 'nexusbox-xendit-invoice-' . $invoiceId);
        $stmt->bindValue(':timeout', max(0, $timeoutSeconds), PDO::PARAM_INT);
        $stmt->execute();
        return (int)$stmt->fetchColumn() === 1;
    }

    public function releaseInvoiceCheckoutLock(int $invoiceId): void
    {
        try {
            $stmt = $this->db->prepare('SELECT RELEASE_LOCK(:name)');
            $stmt->execute(['name' => 'nexusbox-xendit-invoice-' . $invoiceId]);
        } catch (Throwable $e) {
            error_log('[Xendit] Unable to release checkout lock: ' . $e->getMessage());
        }
    }

    public function findByGatewayReferenceForUpdate(string $gateway, string $reference): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM payment_gateway_transactions
            WHERE gateway = :gateway
              AND gateway_reference = :gateway_reference
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute([
            ':gateway' => $gateway,
            ':gateway_reference' => $reference,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function updateByGatewayReference(string $gateway, string $reference, array $data): void
    {
        $stmt = $this->db->prepare("
            UPDATE payment_gateway_transactions
            SET
                payment_id = COALESCE(:payment_id, payment_id),
                gateway_payment_url = COALESCE(:gateway_payment_url, gateway_payment_url),
                gateway_status = :gateway_status,
                raw_response = COALESCE(:raw_response, raw_response),
                raw_webhook = COALESCE(:raw_webhook, raw_webhook)
            WHERE gateway = :gateway
              AND gateway_reference = :gateway_reference
        ");

        $stmt->execute([
            ':payment_id' => $data['payment_id'] ?? null,
            ':gateway_payment_url' => $data['gateway_payment_url'] ?? null,
            ':gateway_status' => $data['gateway_status'] ?? 'PENDING',
            ':raw_response' => $data['raw_response'] ?? null,
            ':raw_webhook' => $data['raw_webhook'] ?? null,
            ':gateway' => $gateway,
            ':gateway_reference' => $reference,
        ]);
    }

    public function list(array $filters = []): array
    {
        $stmt = $this->db->query("
            SELECT
                pgt.*,
                i.invoice_no,
                s.full_name AS subscriber_name,
                s.account_number
            FROM payment_gateway_transactions pgt
            LEFT JOIN invoices i ON i.id = pgt.invoice_id
            LEFT JOIN subscribers s ON s.id = pgt.subscriber_id
            ORDER BY pgt.id DESC
            LIMIT 100
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
