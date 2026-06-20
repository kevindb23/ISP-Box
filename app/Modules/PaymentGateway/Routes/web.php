<?php

use App\Modules\PaymentGateway\Controllers\PaymentGatewayController;

$router->get('/payment-gateway', [PaymentGatewayController::class, 'index']);