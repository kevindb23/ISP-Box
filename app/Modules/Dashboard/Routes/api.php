<?php

use App\Modules\Dashboard\Controllers\DashboardApiController;

$router->get('/api/v1/dashboard/stats', [DashboardApiController::class, 'stats']);