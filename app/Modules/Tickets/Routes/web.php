<?php

use App\Modules\Tickets\Controllers\TicketsController;

$router->get('/tickets', [TicketsController::class, 'index']);