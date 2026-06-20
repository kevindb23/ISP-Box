<?php

use App\Modules\Subscribers\Controllers\SubscriberController;

$router->get('/subscribers', [SubscriberController::class, 'index']);

$router->post('/subscribers/create', [SubscriberController::class, 'create']);
$router->post('/subscribers/update/{id}', [SubscriberController::class, 'update']);
$router->post('/subscribers/suspend', [SubscriberController::class, 'suspend']);
$router->post('/subscribers/reactivate', [SubscriberController::class, 'reactivate']);
$router->post('/subscribers/reset-password', [SubscriberController::class, 'resetPassword']);
$router->post('/subscribers/reset-portal-password', [SubscriberController::class, 'resetPortalPassword']);
$router->post('/subscribers/delete', [SubscriberController::class, 'delete']);
