<?php

use App\Modules\Radius\Controllers\RadiusController;

$router->get('/radius', [RadiusController::class, 'index']);
