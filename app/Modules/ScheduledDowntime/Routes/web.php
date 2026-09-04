<?php

use App\Modules\ScheduledDowntime\Controllers\ScheduledDowntimeController;

$router->get('/scheduled-downtime', [ScheduledDowntimeController::class, 'index']);
