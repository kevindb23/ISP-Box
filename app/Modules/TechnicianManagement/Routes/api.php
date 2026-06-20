<?php

use App\Modules\TechnicianManagement\Controllers\TechnicianManagementApiController;

$router->get('/api/v1/technician-management', [TechnicianManagementApiController::class, 'index']);
$router->get('/api/v1/technician-management/technicians/{id}', [TechnicianManagementApiController::class, 'show']);
$router->post('/api/v1/technician-management/status', [TechnicianManagementApiController::class, 'updateStatus']);
$router->post('/api/v1/technician-management/profile', [TechnicianManagementApiController::class, 'updateProfile']);

$router->get('/api/v1/technician-management/dispatch', [TechnicianManagementApiController::class, 'dispatch']);
$router->post('/api/v1/technician-management/assign-work-order', [TechnicianManagementApiController::class, 'assignWorkOrder']);

$router->post('/api/v1/technician-management/work-order-status', [TechnicianManagementApiController::class, 'updateWorkOrderStatus']);