<?php

use App\Modules\CgnatManagement\Controllers\CgnatManagementController;

$router->get('/cgnat', [CgnatManagementController::class, 'index']);