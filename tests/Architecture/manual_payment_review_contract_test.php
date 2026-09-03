<?php

declare(strict_types=1);

$base = dirname(__DIR__, 2);
$files = [
    'service' => file_get_contents($base . '/app/Modules/SubscriberPortal/Services/SubscriberPortalService.php'),
    'billing' => file_get_contents($base . '/app/Modules/Billing/Services/PaymentService.php'),
    'repository' => file_get_contents($base . '/app/Modules/Billing/Repositories/PaymentRepository.php'),
    'portalRoutes' => file_get_contents($base . '/app/Modules/SubscriberPortal/Routes/api.php'),
    'billingRoutes' => file_get_contents($base . '/app/Modules/Billing/Routes/api.php'),
    'view' => file_get_contents($base . '/app/Modules/SubscriberPortal/Views/invoices.php'),
];

$checks = [
    "['CASH','BANK_TRANSFER','GCASH','MAYA']" => $files['service'],
    'is_uploaded_file($tmp)' => $files['service'],
    "['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp']" => $files['service'],
    "'payment_status'=>'PENDING'" => $files['service'],
    "'proof_file_path'=>" => $files['service'],
    "'proof_file_path' => \$payload['proof_file_path'] ?? null" => $files['billing'],
    'approvePending($paymentId,$userId)' => $files['billing'],
    "strtoupper((string)(\$updatedInvoice['status'] ?? '')) === 'PAID'" => $files['billing'],
    'subscriberIsSuspended' => $files['billing'],
    'subscriberHasOutstandingOverdueInvoices' => $files['billing'],
    "payment_status='REJECTED'" => $files['repository'],
    '/api/v1/subscriber-portal/payments/submit' => $files['portalRoutes'],
    '/api/v1/billing/payments/{id}/approve' => $files['billingRoutes'],
    '/api/v1/billing/payments/{id}/reject' => $files['billingRoutes'],
    'payment_proof' => $files['view'],
];

foreach ($checks as $needle => $haystack) {
    if (!is_string($haystack) || !str_contains($haystack, $needle)) {
        fwrite(STDERR, "manual_payment_review_contract=FAIL missing {$needle}\n");
        exit(1);
    }
}

echo 'manual_payment_review_contract=PASS checks=' . count($checks) . PHP_EOL;
