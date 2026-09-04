<?php

use App\Modules\SystemMaintenance\Controllers\SystemMaintenanceController;

$router->get('/system-maintenance', [SystemMaintenanceController::class, 'index']);
