<?php

namespace App\Modules\Billing\Controllers;

use App\Modules\Billing\Repositories\BillingSettingsRepository;
use App\Modules\Billing\Repositories\InvoiceRepository;
use App\Modules\Billing\Repositories\PaymentRepository;
use App\Modules\Billing\Services\BillingService;
use Framework\Controller;
use Throwable;

class BillingController extends Controller
{
    private BillingService $service;
    private InvoiceRepository $invoiceRepo;
    private PaymentRepository $paymentRepo;
    private BillingSettingsRepository $settingsRepo;

    public function __construct(
        BillingService $service,
        InvoiceRepository $invoiceRepo,
        PaymentRepository $paymentRepo,
        BillingSettingsRepository $settingsRepo
    )
    {
        $this->service = $service;
        $this->invoiceRepo = $invoiceRepo;
        $this->paymentRepo = $paymentRepo;
        $this->settingsRepo = $settingsRepo;
    }

    public function index()
    {
        return $this->view('Billing/index', []);
    }

    public function printInvoice($id)
    {
        $invoiceId = (int)$id;

        if ($invoiceId <= 0) {
            http_response_code(404);
            echo 'Invoice not found.';
            return;
        }

        $invoice = $this->invoiceRepo->find($invoiceId);

        if (!$invoice) {
            http_response_code(404);
            echo 'Invoice not found.';
            return;
        }

        $company = $this->getCompanyProfile();
        $items = $this->invoiceRepo->getItems($invoiceId);
        $payments = $this->paymentRepo->getPaymentsByInvoice($invoiceId);
        $settings = $this->getBillingSettingsMap();

        require BASE_PATH . '/app/Modules/Billing/Views/invoice_print.php';
        exit;
    }

    public function paymentReceipt($id)
    {
        $paymentId = (int)$id;

        if ($paymentId <= 0) {
            http_response_code(404);
            echo 'Payment receipt not found.';
            return;
        }

        $payment = $this->paymentRepo->find($paymentId);

        if (!$payment) {
            http_response_code(404);
            echo 'Payment receipt not found.';
            return;
        }

        $invoice = null;
        $items = [];

        if (!empty($payment['invoice_id'])) {
            $invoice = $this->invoiceRepo->find((int)$payment['invoice_id']);
            $items = $this->invoiceRepo->getItems((int)$payment['invoice_id']);
        }

        $company = $this->getCompanyProfile();
        $settings = $this->getBillingSettingsMap();

        require BASE_PATH . '/app/Modules/Billing/Views/payment_receipt.php';
        exit;
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
