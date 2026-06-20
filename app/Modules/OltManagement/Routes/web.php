<?php

use App\Modules\OltManagement\Controllers\OltManagementController;

$router->get('/olt-management', [OltManagementController::class, 'index']);
$router->get('/olt-management/ports', [OltManagementController::class, 'ports']);
$router->get('/olt-management/profiles', [OltManagementController::class, 'profiles']);

/* DEVICE CRUD */
$router->post('/olt-management/device/create', [OltManagementController::class, 'createDevice']);
$router->post('/olt-management/device/update/{id}', [OltManagementController::class, 'updateDevice']);
$router->post('/olt-management/device/delete', [OltManagementController::class, 'deleteDevice']);

/* PORT OPERATIONS */
$router->post('/olt-management/port/update/{id}', [OltManagementController::class, 'updatePort']);
$router->post('/olt-management/port/delete', [OltManagementController::class, 'deletePort']);

/* FETCH / IMPORT */
$router->post('/olt-management/port/fetch', [OltManagementController::class, 'fetchPorts']);
$router->post('/olt-management/port/import-fetched', [OltManagementController::class, 'importFetchedPorts']);
