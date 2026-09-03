<?php

use App\Modules\TechnicianPortal\Controllers\TechnicianPortalApiController;

$router->get('/api/v1/technician-portal/dashboard', [TechnicianPortalApiController::class, 'dashboard']);

$router->get('/api/v1/technician-portal/work-orders', [TechnicianPortalApiController::class, 'workOrders']);

$router->get('/api/v1/technician-portal/work-orders/show/{id}', [TechnicianPortalApiController::class, 'showWorkOrder']);
$router->get('/api/v1/technician-portal/attachments/{id}/download', [TechnicianPortalApiController::class, 'downloadPhoto']);

$router->post('/api/v1/technician-portal/work-orders/check-in', [TechnicianPortalApiController::class, 'checkIn']);

$router->post('/api/v1/technician-portal/work-orders/start', [TechnicianPortalApiController::class, 'startWork']);

$router->post('/api/v1/technician-portal/work-orders/complete', [TechnicianPortalApiController::class, 'completeWork']);

$router->post('/api/v1/technician-portal/work-orders/complete-task', [TechnicianPortalApiController::class, 'completeTask']);

$router->post('/api/v1/technician-portal/work-orders/add-note', [TechnicianPortalApiController::class, 'addNote']);

$router->post('/api/v1/technician-portal/attendance/time-in', [TechnicianPortalApiController::class, 'timeIn']);

$router->post('/api/v1/technician-portal/attendance/time-out', [TechnicianPortalApiController::class, 'timeOut']);

$router->post('/api/v1/technician-portal/attendance/status', [TechnicianPortalApiController::class, 'updateAttendanceStatus']);

$router->post('/api/v1/technician-portal/work-orders/upload-photo', [TechnicianPortalApiController::class, 'uploadPhoto']);

$router->post('/api/v1/technician-portal/work-orders/delete-photo', [TechnicianPortalApiController::class, 'deletePhoto']);
