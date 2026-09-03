<?php

use App\Modules\VlanManagement\Controllers\VlanManagementApiController;

$router->get('/api/v1/vlan-management/summary', [VlanManagementApiController::class, 'summary']);
$router->get('/api/v1/vlan-management/vlans', [VlanManagementApiController::class, 'vlans']);
$router->post('/api/v1/vlan-management/vlans', [VlanManagementApiController::class, 'createVlan']);
$router->post('/api/v1/vlan-management/vlans/{id}/update', [VlanManagementApiController::class, 'updateVlan']);
$router->post('/api/v1/vlan-management/vlans/{id}/retry', [VlanManagementApiController::class, 'retryVlan']);
$router->post('/api/v1/vlan-management/vlans/{id}/delete', [VlanManagementApiController::class, 'deleteVlan']);

$router->get('/api/v1/vlan-management/mgmt-vlans', [VlanManagementApiController::class, 'mgmtVlans']);
$router->post('/api/v1/vlan-management/mgmt-vlans', [VlanManagementApiController::class, 'saveMgmtVlan']);
$router->post('/api/v1/vlan-management/mgmt-vlans/{id}/delete', [VlanManagementApiController::class, 'deleteMgmtVlan']);
