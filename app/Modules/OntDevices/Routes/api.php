<?php

use App\Modules\OntDevices\Controllers\OntDevicesApiController;
use App\Modules\OntDevices\Controllers\AcsActionsController;

// =========================
// INVENTORY
// =========================
$router->get('/api/v1/ont-devices/inventory', [OntDevicesApiController::class, 'inventory']);
$router->get('/api/v1/ont-devices/inventory/{id}', [OntDevicesApiController::class, 'inventoryItem']);

// =========================
// DISCOVERY
// =========================
$router->get('/api/v1/ont-devices/discovery', [OntDevicesApiController::class, 'discovery']);
$router->post('/api/v1/ont-devices/discover', [OntDevicesApiController::class, 'discover']);
$router->post('/api/v1/ont-devices/add-to-inventory', [OntDevicesApiController::class, 'addToInventory']);

// =========================
// ACS SUMMARY / LIST
// =========================
$router->get('/api/v1/ont-devices/acs', [OntDevicesApiController::class, 'acs']);

// =========================
// INVENTORY CRUD
// =========================
$router->post('/api/v1/ont-devices/store', [OntDevicesApiController::class, 'store']);
$router->post('/api/v1/ont-devices/update/{id}', [OntDevicesApiController::class, 'update']);
$router->post('/api/v1/ont-devices/delete', [OntDevicesApiController::class, 'delete']);

// =========================
// ACS DEVICE ACTIONS
// =========================
$router->get('/api/v1/ont-devices/acs/devices', [AcsActionsController::class, 'devices']);
$router->get('/api/v1/ont-devices/acs/device/{id}', [AcsActionsController::class, 'device']);
$router->get('/api/v1/ont-devices/acs/device/{id}/parameters', [AcsActionsController::class, 'parameters']);

$router->post('/api/v1/ont-devices/acs/ping', [AcsActionsController::class, 'ping']);
$router->post('/api/v1/ont-devices/acs/refresh', [AcsActionsController::class, 'refreshDevice']);
$router->post('/api/v1/ont-devices/acs/reboot', [AcsActionsController::class, 'reboot']);
$router->post('/api/v1/ont-devices/acs/factory-reset', [AcsActionsController::class, 'factoryReset']);
$router->post('/api/v1/ont-devices/acs/wifi-config', [AcsActionsController::class, 'wifiConfig']);

// =========================
// ACS OPTICAL
// =========================
$router->get('/api/v1/ont-devices/acs/device/{id}/optical', [AcsActionsController::class, 'optical']);
$router->get('/api/v1/ont-devices/acs/device/{id}/cached-optical', [AcsActionsController::class, 'cachedOptical']);

// =========================
// ACS WAN
// =========================
$router->post('/api/v1/ont-devices/acs/wan/create', [AcsActionsController::class, 'createWan']);
$router->post('/api/v1/ont-devices/acs/wan/update', [AcsActionsController::class, 'updateWan']);
$router->post('/api/v1/ont-devices/acs/wan/delete', [AcsActionsController::class, 'deleteWan']);