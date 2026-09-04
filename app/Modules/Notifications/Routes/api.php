<?php

use App\Modules\Notifications\Controllers\NotificationsApiController;

$router->get('/api/v1/notifications/settings', [NotificationsApiController::class, 'index']);
$router->post('/api/v1/notifications/settings', [NotificationsApiController::class, 'save']);
$router->post('/api/v1/notifications/test', [NotificationsApiController::class, 'test']);
