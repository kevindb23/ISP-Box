<?php

use App\Modules\TechnicianPortal\Controllers\TechnicianPortalController;

$router->get('/technician-portal', [TechnicianPortalController::class, 'index']);

$router->get('/technician-portal/work-orders', [TechnicianPortalController::class, 'workOrders']);