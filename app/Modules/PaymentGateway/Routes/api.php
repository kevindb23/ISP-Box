<?php

use App\Modules\PaymentGateway\Controllers\PaymentGatewayController;

$router->get('/api/v1/payment-gateway/settings', [PaymentGatewayController::class, 'settings']);
$router->post('/api/v1/payment-gateway/settings/save', [PaymentGatewayController::class, 'saveSettings']);
$router->get('/api/v1/payment-gateway/transactions', [PaymentGatewayController::class, 'transactions']);
$router->post('/api/v1/payment-gateway/paymongo/checkout', [PaymentGatewayController::class, 'createCheckout']);
$router->post('/api/v1/payment-gateway/paymongo/webhook', [PaymentGatewayController::class, 'webhook']);
$router->post('/api/v1/payment-gateway/paymongo/verify', [PaymentGatewayController::class, 'verify']);