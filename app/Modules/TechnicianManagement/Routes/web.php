<?php

use App\Modules\TechnicianManagement\Controllers\TechnicianManagementController;

$router->get('/technician-management', [TechnicianManagementController::class, 'index']);