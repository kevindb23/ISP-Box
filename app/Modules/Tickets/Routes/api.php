<?php

use App\Modules\Tickets\Controllers\TicketsApiController;

$router->get('/api/v1/tickets', [TicketsApiController::class, 'index']);

$router->get('/api/v1/tickets/show/{id}', [TicketsApiController::class, 'show']);

$router->post('/api/v1/tickets/reply', [TicketsApiController::class, 'reply']);

$router->post('/api/v1/tickets/internal-note', [TicketsApiController::class, 'internalNote']);

$router->post('/api/v1/tickets/status', [TicketsApiController::class, 'updateStatus']);

$router->post('/api/v1/tickets/priority', [TicketsApiController::class, 'updatePriority']);

$router->post('/api/v1/tickets/assign', [TicketsApiController::class, 'assign']);

$router->post('/api/v1/tickets/request-schedule', [TicketsApiController::class, 'requestSchedule']);