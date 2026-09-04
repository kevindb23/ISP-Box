<?php

use App\Modules\SystemMaintenance\Controllers\SystemMaintenanceApiController;

$router->get('/api/v1/system-maintenance', [SystemMaintenanceApiController::class, 'index']);
$router->post('/api/v1/system-maintenance', [SystemMaintenanceApiController::class, 'save']);
