<?php

use App\Modules\WorkOrders\Controllers\WorkOrdersApiController;

$router->get('/api/v1/work-orders', [WorkOrdersApiController::class, 'index']);
$router->get('/api/v1/work-orders/show/{id}', [WorkOrdersApiController::class, 'show']);

$router->post('/api/v1/work-orders/create-from-ticket', [WorkOrdersApiController::class, 'createFromTicket']);
$router->post('/api/v1/work-orders/assign', [WorkOrdersApiController::class, 'assign']);
$router->post('/api/v1/work-orders/status', [WorkOrdersApiController::class, 'updateStatus']);
$router->post('/api/v1/work-orders/tasks/complete', [WorkOrdersApiController::class, 'completeTask']);
$router->post('/api/v1/work-orders/tasks/reopen', [WorkOrdersApiController::class, 'reopenTask']);