<?php

use App\Modules\SubscriberPortal\Controllers\SubscriberPortalController;

$router->get('/subscriber-portal', [SubscriberPortalController::class, 'account']);
$router->get('/subscriber-portal/account', [SubscriberPortalController::class, 'account']);
$router->get('/subscriber-portal/services', [SubscriberPortalController::class, 'services']);
$router->get('/subscriber-portal/invoices', [SubscriberPortalController::class, 'invoices']);
$router->get('/subscriber-portal/payments', [SubscriberPortalController::class, 'payments']);
$router->get('/subscriber-portal/tickets', [SubscriberPortalController::class, 'tickets']);
$router->get('/subscriber-portal/security', [SubscriberPortalController::class, 'security']);

$router->get('/subscriber-portal/invoices/print/{id}', [SubscriberPortalController::class, 'printInvoice']);
$router->get('/subscriber-portal/payments/receipt/{id}', [SubscriberPortalController::class, 'printPaymentReceipt']);
