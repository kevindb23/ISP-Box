<?php

use App\Modules\Api\v1\Controllers\AuthController;
use App\Modules\Api\v1\Controllers\SubscriberController;
use App\Modules\Api\v1\Middleware\ApiAuthMiddleware;
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

$router->get('/api/v1/subscribers', [SubscriberController::class, 'index'], [
    ApiAuthMiddleware::class,
    ApiRateLimitMiddleware::class,
    ApiLoggerMiddleware::class
]);

$router->post('/api/v1/subscribers', [SubscriberController::class, 'store'], [
    ApiAuthMiddleware::class,
    ApiRateLimitMiddleware::class,
    ApiLoggerMiddleware::class
]);

$router->get('/api/v1/subscribers/{id}', [SubscriberController::class, 'show'], [
    ApiAuthMiddleware::class,
    ApiRateLimitMiddleware::class,
    ApiLoggerMiddleware::class
]);

$router->post('/api/v1/subscribers/{id}/delete', [SubscriberController::class, 'delete'], [
    ApiAuthMiddleware::class,
    ApiRateLimitMiddleware::class,
    ApiLoggerMiddleware::class
]);
