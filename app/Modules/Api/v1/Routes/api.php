<?php

use App\Modules\Api\v1\Controllers\AuthController;
use App\Modules\Api\v1\Controllers\SubscriberController;
use App\Modules\Api\v1\Controllers\MonitoringController;
use App\Modules\Api\v1\Middleware\ApiRateLimitMiddleware;
use App\Modules\Api\v1\Middleware\ApiLoggerMiddleware;

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

$router->post('/api/v1/login', [AuthController::class, 'login'], [
    ApiRateLimitMiddleware::class,
    ApiLoggerMiddleware::class
]);

/*
|--------------------------------------------------------------------------
| Subscribers API
|--------------------------------------------------------------------------
*/

$router->post('/api/v1/subscribers', [SubscriberController::class, 'store'], [
    ApiRateLimitMiddleware::class,
    ApiLoggerMiddleware::class
]);

$router->post('/api/v1/subscribers/{id}/delete', [SubscriberController::class, 'delete'], [
    ApiRateLimitMiddleware::class,
    ApiLoggerMiddleware::class
]);

$monitoringMiddleware = [ApiRateLimitMiddleware::class, ApiLoggerMiddleware::class];
$router->get('/api/v1/system/info', [MonitoringController::class, 'systemInfo'], $monitoringMiddleware);
$router->get('/api/v1/monitoring/infrastructure/snapshot', [MonitoringController::class, 'infrastructureSnapshot'], $monitoringMiddleware);
$router->get('/api/v1/monitoring/infrastructure/history', [MonitoringController::class, 'infrastructureHistory'], $monitoringMiddleware);
$router->get('/api/v1/monitoring/{resource}/summary', [MonitoringController::class, 'summary'], $monitoringMiddleware);
