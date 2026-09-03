<?php

use App\Modules\Dashboard\Controllers\DashboardController;
use App\Modules\Dashboard\Controllers\LogoutController;

$router->get('/dashboard', [DashboardController::class, 'index']);
$router->post('/logout', [LogoutController::class, 'logout']);
