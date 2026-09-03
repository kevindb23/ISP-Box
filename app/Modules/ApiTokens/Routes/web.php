<?php

use App\Modules\ApiTokens\Controllers\ApiTokensController;
use App\Modules\ApiTokens\Controllers\ApiTokensApiController;

$router->get('/api-tokens',[ApiTokensController::class,'index']);
$router->post('/api/v1/api-tokens/create', [ApiTokensApiController::class, 'create']);
$router->post('/api/v1/api-tokens/{id}/revoke', [ApiTokensApiController::class, 'revoke']);
$router->post('/api/v1/api-tokens/monitoring-identity', [ApiTokensApiController::class, 'updateMonitoringIdentity']);
$router->get('/api/v1/api-tokens/monitoring-operations', [ApiTokensApiController::class, 'monitoringOperations']);
$router->post('/api/v1/api-tokens/monitoring-settings', [ApiTokensApiController::class, 'updateMonitoringSettings']);
$router->post('/api/v1/api-tokens/monitoring-test', [ApiTokensApiController::class, 'testMonitoringConnectivity']);
$router->post('/api/v1/api-tokens/monitoring-retry', [ApiTokensApiController::class, 'retryMonitoringDeliveries']);
