<?php

use App\Modules\Billing\Controllers\BillingApiController;
use App\Modules\Billing\Controllers\InvoiceApiController;
use App\Modules\Billing\Controllers\PaymentApiController;
use App\Modules\Billing\Controllers\XenditGatewayController;
use App\Modules\Billing\Controllers\AdjustmentApiController;
use App\Modules\Billing\Controllers\CollectionApiController;

$router->get('/api/v1/billing/overview', [BillingApiController::class, 'overview']);

$router->get('/api/v1/billing/settings', [BillingApiController::class, 'settings']);
$router->post('/api/v1/billing/settings/save', [BillingApiController::class, 'saveSettings']);

$router->get('/api/v1/billing/support/subscribers', [BillingApiController::class, 'supportSubscribers']);
$router->get('/api/v1/billing/support/services', [BillingApiController::class, 'supportServices']);
$router->get('/api/v1/billing/support/plans', [BillingApiController::class, 'supportPlans']);

/*
|--------------------------------------------------------------------------
| Billing - Invoices
|--------------------------------------------------------------------------
*/
$router->get('/api/v1/billing/invoices/list', [InvoiceApiController::class, 'list']);
$router->get('/api/v1/billing/invoices/show/{id}', [InvoiceApiController::class, 'show']);
$router->post('/api/v1/billing/invoices/create', [InvoiceApiController::class, 'create']);
$router->post('/api/v1/billing/invoices/cancel/{id}', [InvoiceApiController::class, 'cancel']);
$router->post('/api/v1/billing/invoices/recalculate/{id}', [InvoiceApiController::class, 'recalculate']);
$router->post('/api/v1/billing/invoices/mark-overdue', [InvoiceApiController::class, 'markOverdue']);

/*
|--------------------------------------------------------------------------
| Billing - Payments
|--------------------------------------------------------------------------
*/
$router->get('/api/v1/billing/payments/list', [PaymentApiController::class, 'list']);
$router->get('/api/v1/billing/payments/show/{id}', [PaymentApiController::class, 'show']);
$router->post('/api/v1/billing/payments/create', [PaymentApiController::class, 'create']);
$router->get('/api/v1/billing/payments/by-invoice/{invoiceId}', [PaymentApiController::class, 'byInvoice']);
$router->post('/api/v1/billing/payments/void/{id}', [PaymentApiController::class, 'void']);

/*
|--------------------------------------------------------------------------
| Billing - Cycle / Runs
|--------------------------------------------------------------------------
*/
$router->post('/api/v1/billing/generate-due-invoices', [BillingApiController::class, 'generateDueInvoices']);

$router->get('/api/v1/billing/runs/list', [BillingApiController::class, 'billingRuns']);
$router->get('/api/v1/billing/runs/show/{id}', [BillingApiController::class, 'billingRunShow']);

/*
|--------------------------------------------------------------------------
| Billing - Xendit
|--------------------------------------------------------------------------
*/
$router->post('/api/v1/billing/xendit/create-payment-link', [XenditGatewayController::class, 'createPaymentLink']);
$router->post('/api/v1/billing/xendit/webhook', [XenditGatewayController::class, 'webhook']);

/*
|--------------------------------------------------------------------------
| Billing - Adjustments
|--------------------------------------------------------------------------
*/
$router->get('/api/v1/billing/adjustments/list', [AdjustmentApiController::class, 'list']);
$router->get('/api/v1/billing/adjustments/show/{id}', [AdjustmentApiController::class, 'show']);
$router->post('/api/v1/billing/adjustments/create', [AdjustmentApiController::class, 'create']);
$router->post('/api/v1/billing/adjustments/void/{id}', [AdjustmentApiController::class, 'void']);
$router->get('/api/v1/billing/adjustments/by-invoice/{invoiceId}', [AdjustmentApiController::class, 'byInvoice']);

/*
|--------------------------------------------------------------------------
| Billing - Collections
|--------------------------------------------------------------------------
*/
$router->get('/api/v1/billing/collections/aging', [CollectionApiController::class, 'aging']);