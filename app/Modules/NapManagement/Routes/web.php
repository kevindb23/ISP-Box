<?php

use App\Modules\NapManagement\Controllers\NapManagementController;

$router->get('/nap-management', [NapManagementController::class, 'index']);

$router->post('/nap-management/lcp/store', [NapManagementController::class, 'storeLcp']);
$router->post('/nap-management/lcp/update/{id}', [NapManagementController::class, 'updateLcp']);
$router->post('/nap-management/lcp/delete', [NapManagementController::class, 'deleteLcp']);

$router->post('/nap-management/store', [NapManagementController::class, 'storeNap']);
$router->post('/nap-management/update/{id}', [NapManagementController::class, 'updateNap']);
$router->post('/nap-management/delete', [NapManagementController::class, 'deleteNap']);

$router->get('/nap-management/lcp-ports/{lcpId}', [NapManagementController::class, 'getAvailablePorts']);