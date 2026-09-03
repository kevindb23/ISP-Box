<?php
use App\Modules\RouterManagement\Controllers\RouterManagementApiController;
$router->get('/api/v1/routers',[RouterManagementApiController::class,'index']);
$router->post('/api/v1/routers/core',[RouterManagementApiController::class,'saveCore']);
$router->get('/api/v1/routers/core/host-key',[RouterManagementApiController::class,'scanCore']);
$router->post('/api/v1/routers/core/host-key/trust',[RouterManagementApiController::class,'trustCore']);
$router->get('/api/v1/routers/core/runtime',[RouterManagementApiController::class,'runtimeCore']);
$router->post('/api/v1/routers/core/apply',[RouterManagementApiController::class,'applyCore']);
$router->post('/api/v1/routers/frr',[RouterManagementApiController::class,'saveFrr']);
$router->get('/api/v1/routers/frr/runtime',[RouterManagementApiController::class,'runtimeFrr']);
$router->post('/api/v1/routers/frr/apply',[RouterManagementApiController::class,'applyFrr']);
