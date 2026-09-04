<?php

use App\Modules\ScheduledDowntime\Controllers\ScheduledDowntimeApiController;

$router->get('/api/v1/scheduled-downtime', [ScheduledDowntimeApiController::class, 'index']);
$router->post('/api/v1/scheduled-downtime', [ScheduledDowntimeApiController::class, 'store']);
$router->post('/api/v1/scheduled-downtime/{id}/delete', [ScheduledDowntimeApiController::class, 'delete']);
$router->post('/api/v1/scheduled-downtime/{id}/toggle', [ScheduledDowntimeApiController::class, 'toggle']);
$router->post('/api/v1/scheduled-downtime/{id}', [ScheduledDowntimeApiController::class, 'update']);
$router->put('/api/v1/scheduled-downtime/{id}', [ScheduledDowntimeApiController::class, 'update']);
$router->delete('/api/v1/scheduled-downtime/{id}', [ScheduledDowntimeApiController::class, 'delete']);
