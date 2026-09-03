<?php

use App\Modules\Radius\Controllers\RadiusApiController;

$router->get('/api/v1/radius/settings', [RadiusApiController::class, 'index']);
$router->get('/api/v1/radius/settings/{id}', [RadiusApiController::class, 'show']);
$router->post('/api/v1/radius/settings', [RadiusApiController::class, 'store']);
$router->post('/api/v1/radius/settings/{id}', [RadiusApiController::class, 'update']);
$router->post('/api/v1/radius/settings/delete', [RadiusApiController::class, 'destroy']);
