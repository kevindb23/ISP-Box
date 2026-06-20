<?php

namespace App\Modules\SubscriberPortal\Controllers;

use App\Modules\Billing\Repositories\BillingSettingsRepository;
use App\Modules\Billing\Repositories\InvoiceRepository;
use App\Modules\Billing\Repositories\PaymentRepository;
use Framework\Controller;
use Framework\DatabaseConnection;
use Framework\SessionManager;
use PDO;
use Throwable;

class SubscriberPortalController extends Controller
{
    private PDO $db;
    private InvoiceRepository $invoiceRepo;
    private PaymentRepository $paymentRepo;
    private BillingSettingsRepository $settingsRepo;

    public function __construct(DatabaseConnection $database)
    {
        $this->db = $database->get();

        $this->invoiceRepo = new InvoiceRepository($this->db);
        $this->paymentRepo = new PaymentRepository($this->db);
        $this->settingsRepo = new BillingSettingsRepository($this->db);
    }

    public function account()
    {
        $this->requireSubscriber();

        return $this->view('SubscriberPortal/account');
    }

    public function services()
    {
        $this->requireSubscriber();

        return $this->view('SubscriberPortal/services');
    }

    public function invoices()
    {
        $this->requireSubscriber();

        return $this->view('SubscriberPortal/invoices');
    }

    public function payments()
    {
        $this->requireSubscriber();

        return $this->view('SubscriberPortal/payments');
    }

    public function tickets()
    {
        $this->requireSubscriber();

        return $this->view('SubscriberPortal/tickets');
    }

    public function index()
    {
        return $this->account();
    }

    public function printInvoice($id): void
    {
        $invoiceId = (int)$id;

        if ($invoiceId <= 0) {
            http_response_code(404);
            echo 'Invoice not found.';
            return;
        }

        $user = SessionManager::user();

        if (!is_array($user) || empty($user['id'])) {
            http_response_code(403);
            echo 'You must be logged in to view this invoice.';
            return;
        }

        $role = strtoupper((string)($user['role'] ?? ''));

        if ($role !== 'SUBSCRIBER') {
            http_response_code(403);
            echo 'Subscriber portal access only.';
            return;
        }

        $subscriber = $this->findSubscriberByUserId((int)$user['id']);

        if (!$subscriber) {
            http_response_code(403);
            echo 'Subscriber account is not linked to this user.';
            return;
        }

        $invoice = $this->invoiceRepo->find($invoiceId);

        if (!$invoice) {
            http_response_code(404);
            echo 'Invoice not found.';
            return;
        }

        if ((int)($invoice['subscriber_id'] ?? 0) !== (int)($subscriber['id'] ?? 0)) {
            http_response_code(403);
            echo 'You are not allowed to view this invoice.';
            return;
        }

        $company = $this->getCompanyProfile();
        $items = $this->invoiceRepo->getItems($invoiceId);
        $payments = $this->paymentRepo->getPaymentsByInvoice($invoiceId);
        $settings = $this->getBillingSettingsMap();

        require BASE_PATH . '/app/Modules/SubscriberPortal/Views/invoice_print.php';
        exit;
    }

    private function requireSubscriber(): void
    {
        $user = SessionManager::user();

        if (!is_array($user) || empty($user['id'])) {
            header('Location: /login');
            exit;
        }

        $role = strtoupper((string)($user['role'] ?? ''));

        if ($role !== 'SUBSCRIBER') {
            header('Location: /logout');
            exit;
        }

        $subscriber = $this->findSubscriberByUserId((int)$user['id']);

        if (!$subscriber) {
            header('Location: /logout');
            exit;
        }
    }

    private function findSubscriberByUserId(int $userId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM subscribers
            WHERE user_id = :user_id
              AND deleted_at IS NULL
            LIMIT 1
        ");

        $stmt->execute([
            ':user_id' => $userId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function getBillingSettingsMap(): array
    {
        try {
            $rows = $this->settingsRepo->all();
            $map = [];

            foreach ($rows as $row) {
                $key = (string)($row['setting_key'] ?? '');

                if ($key !== '') {
                    $map[$key] = $row['setting_value'] ?? '';
                }
            }

            return $map;
        } catch (Throwable $e) {
            return [];
        }
    }

    public function printPaymentReceipt($id): void
    {
        $paymentId = (int)$id;

        if ($paymentId <= 0) {
            http_response_code(404);
            echo 'Payment receipt not found.';
            return;
        }

        $user = SessionManager::user();

        if (!is_array($user) || empty($user['id'])) {
            http_response_code(403);
            echo 'You must be logged in to view this receipt.';
            return;
        }

        $role = strtoupper((string)($user['role'] ?? ''));

        if ($role !== 'SUBSCRIBER') {
            http_response_code(403);
            echo 'Subscriber portal access only.';
            return;
        }

        $subscriber = $this->findSubscriberByUserId((int)$user['id']);

        if (!$subscriber) {
            http_response_code(403);
            echo 'Subscriber account is not linked to this user.';
            return;
        }

        $repo = new \App\Modules\SubscriberPortal\Repositories\SubscriberPortalRepository(
            new DatabaseConnection()
        );

        $receipt = $repo->findPaymentReceiptForSubscriber(
            $paymentId,
            (int)$subscriber['id']
        );

        if (!$receipt) {
            http_response_code(404);
            echo 'Payment receipt not found.';
            return;
        }

        $company = $this->getCompanyProfile();
        $settings = $this->getBillingSettingsMap();

        require BASE_PATH . '/app/Modules/SubscriberPortal/Views/receipt_print.php';
        exit;
    }
    private function getCompanyProfile(): array
    {
        $settings = $this->getBillingSettingsMap();

        return [
            'name' => $settings['company_name'] ?? 'NexusBox ISP',
            'tagline' => $settings['company_tagline'] ?? 'Internet Service Provider',
            'logo_url' => $settings['company_logo_url'] ?? '',
            'address' => $settings['company_address'] ?? 'Company address will appear here',
            'contact' => $settings['company_contact'] ?? 'Contact number / email will appear here',
            'tin' => $settings['company_tin'] ?? '',
            'website' => $settings['company_website'] ?? '',
        ];
    }
}