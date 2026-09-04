<?php

use App\Modules\Mfa\Controllers\MfaController;
use App\Modules\Mfa\Controllers\MfaApiController;

$router->get('/mfa', [MfaController::class, 'index']);
$router->post('/login/mfa', [MfaApiController::class, 'verifyLogin']);
