<?php

use App\Modules\Subscribers\Controllers\SubscriberApiController;

$router->get('/api/v1/subscribers', [SubscriberApiController::class, 'index']);
$router->get('/api/v1/subscribers/sessions', [SubscriberApiController::class, 'sessions']);
$router->post('/api/v1/subscribers/create', [SubscriberApiController::class, 'create']);
$router->post('/api/v1/subscribers/update/{id}', [SubscriberApiController::class, 'update']);
$router->post('/api/v1/subscribers/suspend', [SubscriberApiController::class, 'suspend']);
$router->post('/api/v1/subscribers/reactivate', [SubscriberApiController::class, 'reactivate']);
$router->post('/api/v1/subscribers/reset-password', [SubscriberApiController::class, 'resetPassword']);
$router->post('/api/v1/subscribers/reset-portal-password', [SubscriberApiController::class, 'resetPortalPassword']);
$router->post('/api/v1/subscribers/delete', [SubscriberApiController::class, 'delete']);
$router->get('/api/v1/subscribers/{id}', [SubscriberApiController::class, 'show']);