<?php

use App\Modules\Notifications\Controllers\NotificationsController;

$router->get('/notifications', [NotificationsController::class, 'index']);
