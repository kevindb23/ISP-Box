<?php

use App\Modules\BngManagement\Controllers\BngManagementController;

$router->get('/bng', [BngManagementController::class, 'index']);
