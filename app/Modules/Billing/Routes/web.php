<?php

use App\Modules\Billing\Controllers\BillingController;

$router->get('/billing',[BillingController::class,'index']);

$router->get('/billing/invoices/print/{id}', [BillingController::class, 'printInvoice']);
$router->get('/billing/payments/receipt/{id}', [BillingController::class, 'paymentReceipt']);
