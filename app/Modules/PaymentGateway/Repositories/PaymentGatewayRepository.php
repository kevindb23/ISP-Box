<?php

namespace App\Modules\PaymentGateway\Repositories;

use App\Infrastructure\Database\DatabaseConnection;
use App\Infrastructure\Security\SecretCipher;
use PDO;
use RuntimeException;
use Throwable;

class PaymentGatewayRepository
{
    private PDO $db;

    public function __construct(DatabaseConnection $connection, private SecretCipher $secrets)
    {
        $this->db = $connection->get();
    }

    public function getSettingsMap(): array
    {
        $stmt = $this->db->query("
            SELECT setting_key, setting_value
            FROM payment_gateway_settings
        ");

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $map = [];

        foreach ($rows as $row) {
            $key = (string)$row['setting_key'];
            $map[$key] = $this->isSensitiveSetting($key)
                ? $this->secrets->decrypt($row['setting_value'])
                : $row['setting_value'];
        }

        return $map;
    }

    public function saveSetting(string $key, string $value): void
    {
        if ($this->isSensitiveSetting($key)) $value = (string)$this->secrets->encrypt($value);
        $stmt = $this->db->prepare("
            INSERT INTO payment_gateway_settings
                (setting_key, setting_value)
            VALUES
                (:setting_key, :setting_value)
            ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                updated_at = CURRENT_TIMESTAMP
        ");

        $stmt->execute([
            'setting_key' => $key,
            'setting_value' => $value,
        ]);
    }

    private function isSensitiveSetting(string $key): bool
    {
        return in_array($key, ['paymongo_secret_key', 'paymongo_webhook_secret'], true);
    }

    public function findInvoiceById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                i.*,
                s.full_name AS subscriber_name,
                s.account_number,
                s.email,
                s.contact_number
            FROM invoices i
            LEFT JOIN subscribers s ON s.id = i.subscriber_id
            WHERE i.id = :id
            LIMIT 1
        ");

        $stmt->execute(['id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function acquireCheckoutLock(int $invoiceId, int $timeoutSeconds = 5): bool
    {
        $stmt = $this->db->prepare('SELECT GET_LOCK(:name, :timeout)');
        $stmt->bindValue(':name', 'nexusbox-paymongo-invoice-' . $invoiceId);
        $stmt->bindValue(':timeout', max(0, $timeoutSeconds), PDO::PARAM_INT);
        $stmt->execute();
        return (int)$stmt->fetchColumn() === 1;
    }

    public function releaseCheckoutLock(int $invoiceId): void
    {
        try {
            $stmt = $this->db->prepare('SELECT RELEASE_LOCK(:name)');
            $stmt->execute(['name' => 'nexusbox-paymongo-invoice-' . $invoiceId]);
        } catch (Throwable $e) {
            error_log('[PayMongo] Unable to release checkout lock: ' . $e->getMessage());
        }
    }

    public function findSubscriberIdByUserId(int $userId): ?int
    {
        $stmt = $this->db->prepare("
            SELECT id
            FROM subscribers
            WHERE user_id = :user_id
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute(['user_id' => $userId]);

        $id = $stmt->fetchColumn();
        return $id !== false ? (int)$id : null;
    }

    public function createTransaction(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO payment_gateway_transactions
            (
                payment_id,
                invoice_id,
                subscriber_id,
                gateway,
                gateway_reference,
                gateway_payment_url,
                gateway_status,
                amount,
                currency,
                raw_request,
                raw_response,
                raw_webhook
            )
            VALUES
            (
                :payment_id,
                :invoice_id,
                :subscriber_id,
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
            'payment_id' => $data['payment_id'] ?? null,
            'invoice_id' => $data['invoice_id'] ?? null,
            'subscriber_id' => $data['subscriber_id'] ?? null,
            'gateway' => strtoupper((string)$data['gateway']),
            'gateway_reference' => $data['gateway_reference'] ?? null,
            'gateway_payment_url' => $data['gateway_payment_url'] ?? null,
            'gateway_status' => strtoupper((string)($data['gateway_status'] ?? 'PENDING')),
            'amount' => $data['amount'] ?? 0,
            'currency' => strtoupper((string)($data['currency'] ?? 'PHP')),
            'raw_request' => $data['raw_request'] ?? null,
            'raw_response' => $data['raw_response'] ?? null,
            'raw_webhook' => $data['raw_webhook'] ?? null,
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function listTransactions(array $filters = []): array
    {
        $sql = "
            SELECT
                t.*,
                i.invoice_no,
                i.status AS invoice_status,
                i.balance_amount,
                s.full_name AS subscriber_name,
                s.account_number
            FROM payment_gateway_transactions t
            LEFT JOIN invoices i ON i.id = t.invoice_id
            LEFT JOIN subscribers s ON s.id = t.subscriber_id
            WHERE 1 = 1
        ";

        $params = [];

        if (!empty($filters['gateway'])) {
            $sql .= " AND t.gateway = :gateway";
            $params['gateway'] = strtoupper((string)$filters['gateway']);
        }

        if (!empty($filters['gateway_status'])) {
            $sql .= " AND t.gateway_status = :gateway_status";
            $params['gateway_status'] = strtoupper((string)$filters['gateway_status']);
        }

        if (!empty($filters['invoice_id'])) {
            $sql .= " AND t.invoice_id = :invoice_id";
            $params['invoice_id'] = (int)$filters['invoice_id'];
        }

        $sql .= " ORDER BY t.id DESC LIMIT 500";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findTransactionByReference(string $reference): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM payment_gateway_transactions
            WHERE gateway_reference = :reference
            LIMIT 1
        ");

        $stmt->execute([
            'reference' => $reference,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findLatestPendingPayMongoTransaction(?int $subscriberId = null): ?array
    {
        $sql = "
            SELECT *
            FROM payment_gateway_transactions
            WHERE gateway = 'PAYMONGO'
              AND gateway_status IN ('PENDING', 'UPDATED')
        ";

        $params = [];

        if ($subscriberId !== null && $subscriberId > 0) {
            $sql .= " AND subscriber_id = :subscriber_id";
            $params['subscriber_id'] = $subscriberId;
        }

        $sql .= " ORDER BY id DESC LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findPendingPayMongoTransactionForInvoice(int $invoiceId, ?int $subscriberId = null): ?array
    {
        $sql="SELECT * FROM payment_gateway_transactions WHERE gateway='PAYMONGO' AND invoice_id=:invoice_id AND gateway_status IN ('PENDING','UPDATED')";
        $params=[':invoice_id'=>$invoiceId]; if($subscriberId){$sql.=' AND subscriber_id=:subscriber_id';$params[':subscriber_id']=$subscriberId;}
        $stmt=$this->db->prepare($sql.' ORDER BY id DESC LIMIT 1');$stmt->execute($params);return $stmt->fetch(PDO::FETCH_ASSOC)?:null;
    }

    public function updateTransactionWebhook(
        int $id,
        string $status,
        string $rawWebhook
    ): void {
        $stmt = $this->db->prepare("
            UPDATE payment_gateway_transactions
            SET
                gateway_status = :gateway_status,
                raw_webhook = :raw_webhook,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $id,
            'gateway_status' => strtoupper($status),
            'raw_webhook' => $rawWebhook,
        ]);
    }

    public function updateTransactionStatus(
        int $id,
        string $status,
        ?string $rawWebhook = null
    ): void {
        $stmt = $this->db->prepare("
            UPDATE payment_gateway_transactions
            SET
                gateway_status = :gateway_status,
                raw_webhook = COALESCE(:raw_webhook, raw_webhook),
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $id,
            'gateway_status' => strtoupper($status),
            'raw_webhook' => $rawWebhook,
        ]);
    }

    public function findReusablePendingTransactionByInvoiceId(int $invoiceId): ?array
    {
        $stmt = $this->db->prepare("
        SELECT *
        FROM payment_gateway_transactions
        WHERE invoice_id = :invoice_id
          AND gateway = 'PAYMONGO'
          AND gateway_status IN ('PENDING', 'UPDATED')
          AND gateway_payment_url IS NOT NULL
          AND created_at >= DATE_SUB(NOW(), INTERVAL 3 HOUR)
        ORDER BY id DESC
        LIMIT 1
    ");

        $stmt->execute([
            'invoice_id' => $invoiceId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function postPaidGatewayTransaction(int $transactionId, ?string $rawVerification = null): array
    {
        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                SELECT *
                FROM payment_gateway_transactions
                WHERE id = :id
                FOR UPDATE
            ");
            $stmt->execute(['id' => $transactionId]);

            $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$transaction) {
                throw new RuntimeException('Gateway transaction not found.');
            }

            if (!empty($transaction['payment_id'])) {
                $this->db->commit();

                return [
                    'already_posted' => true,
                    'payment_id' => (int)$transaction['payment_id'],
                    'invoice_id' => (int)$transaction['invoice_id'],
                ];
            }

            $invoiceId = (int)($transaction['invoice_id'] ?? 0);

            if ($invoiceId <= 0) {
                throw new RuntimeException('Gateway transaction has no invoice.');
            }

            $stmt = $this->db->prepare("
                SELECT *
                FROM invoices
                WHERE id = :id
                FOR UPDATE
            ");
            $stmt->execute(['id' => $invoiceId]);

            $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$invoice) {
                throw new RuntimeException('Invoice not found.');
            }

            if (strtoupper((string)($invoice['status'] ?? '')) === 'CANCELLED') {
                throw new RuntimeException('Cancelled invoices cannot receive gateway payments.');
            }

            $balance = round((float)($invoice['balance_amount'] ?? 0), 2);
            $amount = round((float)($transaction['amount'] ?? 0), 2);

            if ($balance <= 0) {
                $this->markTransactionPaidOnly((int)$transaction['id'], $rawVerification);
                $this->db->commit();

                return [
                    'already_paid' => true,
                    'payment_id' => null,
                    'invoice_id' => $invoiceId,
                ];
            }

            $postAmount = min($amount, $balance);

            if ($postAmount <= 0) {
                throw new RuntimeException('Invalid payment amount.');
            }

            $stmt = $this->db->prepare("
                INSERT INTO payments
                (
                    payment_no,
                    invoice_id,
                    subscriber_id,
                    service_id,
                    amount,
                    payment_date,
                    method,
                    reference_no,
                    payment_status,
                    remarks,
                    received_by,
                    posted_at
                )
                VALUES
                (
                    NULL,
                    :invoice_id,
                    :subscriber_id,
                    :service_id,
                    :amount,
                    CURRENT_TIMESTAMP,
                    'PAYMONGO',
                    :reference_no,
                    'POSTED',
                    :remarks,
                    NULL,
                    NOW()
                )
            ");

            $stmt->execute([
                'invoice_id' => $invoiceId,
                'subscriber_id' => $invoice['subscriber_id'] ?? $transaction['subscriber_id'] ?? null,
                'service_id' => $invoice['service_id'] ?? null,
                'amount' => $postAmount,
                'reference_no' => $transaction['gateway_reference'],
                'remarks' => 'Auto-posted from PayMongo checkout.',
            ]);

            $paymentId = (int)$this->db->lastInsertId();
            $paymentNo = $this->generatePaymentNo($paymentId);

            $stmt = $this->db->prepare("
                UPDATE payments
                SET payment_no = :payment_no
                WHERE id = :id
            ");
            $stmt->execute([
                'id' => $paymentId,
                'payment_no' => $paymentNo,
            ]);

            $stmt = $this->db->prepare("
                INSERT INTO payment_allocations
                (
                    payment_id,
                    invoice_id,
                    allocated_amount
                )
                VALUES
                (
                    :payment_id,
                    :invoice_id,
                    :allocated_amount
                )
            ");
            $stmt->execute([
                'payment_id' => $paymentId,
                'invoice_id' => $invoiceId,
                'allocated_amount' => $postAmount,
            ]);

            $newPaid = round((float)$invoice['paid_amount'] + $postAmount, 2);
            $newBalance = max(round((float)$invoice['total_amount'] - $newPaid, 2), 0);
            $newStatus = $newBalance <= 0 ? 'PAID' : 'PARTIAL';

            $stmt = $this->db->prepare("
                UPDATE invoices
                SET
                    paid_amount = :paid_amount,
                    balance_amount = :balance_amount,
                    status = :status,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
            $stmt->execute([
                'id' => $invoiceId,
                'paid_amount' => $newPaid,
                'balance_amount' => $newBalance,
                'status' => $newStatus,
            ]);

            $stmt = $this->db->prepare("
                UPDATE payment_gateway_transactions
                SET
                    payment_id = :payment_id,
                    gateway_status = 'PAID',
                    raw_webhook = COALESCE(:raw_webhook, raw_webhook),
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
            $stmt->execute([
                'id' => $transactionId,
                'payment_id' => $paymentId,
                'raw_webhook' => $rawVerification,
            ]);

            $this->db->commit();

            return [
                'already_posted' => false,
                'payment_id' => $paymentId,
                'payment_no' => $paymentNo,
                'invoice_id' => $invoiceId,
                'invoice_status' => $newStatus,
                'paid_amount' => $newPaid,
                'balance_amount' => $newBalance,
            ];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }

    private function markTransactionPaidOnly(int $transactionId, ?string $rawVerification = null): void
    {
        $stmt = $this->db->prepare("
            UPDATE payment_gateway_transactions
            SET
                gateway_status = 'PAID',
                raw_webhook = COALESCE(:raw_webhook, raw_webhook),
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $transactionId,
            'raw_webhook' => $rawVerification,
        ]);
    }

    private function generatePaymentNo(int $paymentId): string
    {
        return 'PAY-' . date('Y') . '-' . str_pad((string)$paymentId, 6, '0', STR_PAD_LEFT);
    }
}
