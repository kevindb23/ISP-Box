<?php

namespace App\Modules\SubscriberPortal\Controllers;

use App\Modules\Billing\Repositories\BillingSettingsRepository;
use App\Modules\Billing\Repositories\InvoiceRepository;
use App\Modules\Billing\Repositories\PaymentRepository;
use App\Modules\SubscriberPortal\Repositories\SubscriberPortalRepository;
use App\Modules\SystemMaintenance\Services\SystemMaintenanceService;
use Framework\Controller;
use Framework\SessionManager;
use Throwable;

class SubscriberPortalController extends Controller
{
    private InvoiceRepository $invoiceRepo;
    private PaymentRepository $paymentRepo;
    private BillingSettingsRepository $settingsRepo;

    public function __construct(
        InvoiceRepository $invoiceRepo,
        PaymentRepository $paymentRepo,
        BillingSettingsRepository $settingsRepo,
        private SubscriberPortalRepository $subscriberRepo,
        private SystemMaintenanceService $systemMaintenance
    )
    {
        $this->invoiceRepo = $invoiceRepo;
        $this->paymentRepo = $paymentRepo;
        $this->settingsRepo = $settingsRepo;
    }

    public function account()
    {
        $this->requireSubscriber();

        return $this->portalView('SubscriberPortal/account');
    }

    public function services()
    {
        $this->requireSubscriber();

        return $this->portalView('SubscriberPortal/services');
    }

    public function invoices()
    {
        $this->requireSubscriber();

        return $this->portalView('SubscriberPortal/invoices');
    }

    public function payments()
    {
        $this->requireSubscriber();

        return $this->portalView('SubscriberPortal/payments');
    }

    public function tickets()
    {
        $this->requireSubscriber();

        return $this->portalView('SubscriberPortal/tickets');
    }

    public function security()
    {
        $this->requireSubscriber();

        return $this->portalView('SubscriberPortal/security');
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

        if ($this->renderMaintenanceResponse()) {
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
            SessionManager::destroy();
            header('Location: /login');
            exit;
        }

        $subscriber = $this->findSubscriberByUserId((int)$user['id']);

        if (!$subscriber) {
            SessionManager::destroy();
            header('Location: /login');
            exit;
        }
    }

    private function findSubscriberByUserId(int $userId): ?array
    {
        return $this->subscriberRepo->findSubscriberByUserId($userId);
    }

    private function portalView(string $view)
    {
        return $this->view($view, [
            'maintenanceState' => $this->systemMaintenance->activeState(),
        ]);
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

        if ($this->renderMaintenanceResponse()) {
            return;
        }

        $subscriber = $this->findSubscriberByUserId((int)$user['id']);

        if (!$subscriber) {
            http_response_code(403);
            echo 'Subscriber account is not linked to this user.';
            return;
        }

        $receipt = $this->subscriberRepo->findPaymentReceiptForSubscriber(
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

    private function renderMaintenanceResponse(): bool
    {
        $state = $this->systemMaintenance->activeState();
        if (empty($state['active'])) {
            return false;
        }

        $message = trim((string)($state['message'] ?? '')) ?: 'We are performing scheduled maintenance. Please try again soon.';
        http_response_code(503);
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>System maintenance</title><style>body{font-family:system-ui,sans-serif;background:#f8fafc;color:#172033;display:grid;place-items:center;min-height:100vh;margin:0;padding:24px}.card{max-width:680px;background:#fff;border:1px solid #dbe3ef;border-radius:14px;padding:42px;text-align:center;box-shadow:0 10px 30px rgba(23,32,51,.08)}h1{margin:0 0 16px}p{line-height:1.6;color:#53627a;white-space:pre-line}</style></head><body><main class="card"><div aria-hidden="true">🔧</div><h1>We are sorry for the interruption</h1><p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p><p>Please check back shortly.</p></main></body></html>';
        return true;
    }
}
