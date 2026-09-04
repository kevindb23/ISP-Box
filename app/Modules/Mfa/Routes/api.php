<?php

use App\Modules\Mfa\Controllers\MfaApiController;

$router->get('/api/v1/mfa', [MfaApiController::class, 'index']);
$router->post('/api/v1/mfa/{id}/enroll', [MfaApiController::class, 'enroll']);
$router->post('/api/v1/mfa/{id}/complete', [MfaApiController::class, 'verifyEnrollment']);
$router->post('/api/v1/mfa/{id}/disable', [MfaApiController::class, 'disable']);
$router->post('/api/v1/mfa/{id}/reset', [MfaApiController::class, 'reset']);
$router->post('/api/v1/mfa/login/verify', [MfaApiController::class, 'verifyLogin']);
