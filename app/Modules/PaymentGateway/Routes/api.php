<?php

use App\Modules\PaymentGateway\Controllers\PaymentGatewayApiController;

$router->get('/api/v1/payment-gateway/settings', [PaymentGatewayApiController::class, 'settings']);
$router->post('/api/v1/payment-gateway/settings/save', [PaymentGatewayApiController::class, 'saveSettings']);
$router->get('/api/v1/payment-gateway/transactions', [PaymentGatewayApiController::class, 'transactions']);
$router->post('/api/v1/payment-gateway/paymongo/checkout', [PaymentGatewayApiController::class, 'createCheckout']);
$router->post('/api/v1/payment-gateway/paymongo/webhook', [PaymentGatewayApiController::class, 'webhook']);
$router->post('/api/v1/payment-gateway/paymongo/verify', [PaymentGatewayApiController::class, 'verify']);
