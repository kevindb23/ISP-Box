<?php

declare(strict_types=1);

$base = dirname(__DIR__, 2);
$failures = [];
$files = [
    'invoiceService' => (string)file_get_contents($base . '/app/Modules/Billing/Services/InvoiceService.php'),
    'invoiceRepository' => (string)file_get_contents($base . '/app/Modules/Billing/Repositories/InvoiceRepository.php'),
    'paymentService' => (string)file_get_contents($base . '/app/Modules/Billing/Services/PaymentService.php'),
    'adjustmentService' => (string)file_get_contents($base . '/app/Modules/Billing/Services/AdjustmentService.php'),
    'adjustmentRepository' => (string)file_get_contents($base . '/app/Modules/Billing/Repositories/AdjustmentRepository.php'),
    'paymongo' => (string)file_get_contents($base . '/app/Modules/PaymentGateway/Services/PaymentGatewayService.php'),
    'paymongoRepository' => (string)file_get_contents($base . '/app/Modules/PaymentGateway/Repositories/PaymentGatewayRepository.php'),
    'xendit' => (string)file_get_contents($base . '/app/Modules/Billing/Services/XenditGatewayService.php'),
    'xenditRepository' => (string)file_get_contents($base . '/app/Modules/Billing/Repositories/PaymentGatewayRepository.php'),
];

$requirements = [
    ['invoiceService', 'assignInvoiceNo(', 'Automatic invoices are not assigned from the inserted identity.'],
    ['invoiceRepository', 'invoice_no IS NULL', 'Invoice number assignment is not guarded.'],
    ['paymentService', 'findForUpdate(', 'Payment posting does not lock its invoice.'],
    ['paymentService', 'assignPaymentNo(', 'Payment numbering is not identity-derived.'],
    ['adjustmentService', 'findForUpdate(', 'Adjustment processing does not lock its invoice.'],
    ['adjustmentService', 'assignAdjustmentNo(', 'Adjustment numbering is not identity-derived.'],
    ['adjustmentRepository', 'expected_temporary_no', 'Adjustment number assignment is not guarded.'],
    ['paymongo', 'acquireCheckoutLock(', 'PayMongo checkout creation does not acquire its lock.'],
    ['paymongoRepository', 'GET_LOCK(', 'PayMongo checkout repository lacks a per-invoice lock.'],
    ['xendit', 'acquireInvoiceCheckoutLock(', 'Xendit checkout creation does not acquire its lock.'],
    ['xenditRepository', 'GET_LOCK(', 'Xendit checkout repository lacks a per-invoice lock.'],
];

foreach ($requirements as [$file, $needle, $message]) {
    if (!str_contains($files[$file], $needle)) $failures[] = $message;
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo 'financial_concurrency_contract=PASS' . PHP_EOL;
