<?php

use App\Modules\OltManagement\Controllers\OltManagementApiController;

/*
|--------------------------------------------------------------------------
| OLT Management API Routes
|--------------------------------------------------------------------------
*/

/* =========================================================
 * DEVICES
 * ========================================================= */
$router->get('/api/v1/olt-management/devices', [OltManagementApiController::class, 'devices']);
$router->get('/api/v1/olt-management/device/{id}', [OltManagementApiController::class, 'device']);
$router->post('/api/v1/olt-management/device/create', [OltManagementApiController::class, 'createDevice']);
$router->post('/api/v1/olt-management/device/update/{id}', [OltManagementApiController::class, 'updateDevice']);
$router->post('/api/v1/olt-management/device/delete', [OltManagementApiController::class, 'deleteDevice']);

/* =========================================================
 * PORTS
 * ========================================================= */
$router->get('/api/v1/olt-management/ports', [OltManagementApiController::class, 'ports']);
$router->get('/api/v1/olt-management/ports/by-olt/{oltId}', [OltManagementApiController::class, 'portsByOlt']);
$router->get('/api/v1/olt-management/port/{id}', [OltManagementApiController::class, 'port']);
$router->get('/api/v1/olt-management/available-ports/{oltId}', [OltManagementApiController::class, 'availablePorts']);

$router->post('/api/v1/olt-management/port/create', [OltManagementApiController::class, 'createPort']);
$router->post('/api/v1/olt-management/port/update/{id}', [OltManagementApiController::class, 'updatePort']);
$router->post('/api/v1/olt-management/port/delete', [OltManagementApiController::class, 'deletePort']);

$router->post('/api/v1/olt-management/port/fetch', [OltManagementApiController::class, 'fetchPorts']);
$router->post('/api/v1/olt-management/port/import-fetched', [OltManagementApiController::class, 'importFetchedPorts']);

/* =========================================================
 * DBA PROFILES
 * ========================================================= */
$router->get('/api/v1/olt-management/dba-profiles', [OltManagementApiController::class, 'dbaProfiles']);
$router->get('/api/v1/olt-management/dba-profile/{id}', [OltManagementApiController::class, 'dbaProfile']);
$router->post('/api/v1/olt-management/dba-profile/create', [OltManagementApiController::class, 'createDbaProfile']);
$router->post('/api/v1/olt-management/dba-profile/update/{id}', [OltManagementApiController::class, 'updateDbaProfile']);
$router->post('/api/v1/olt-management/dba-profile/delete', [OltManagementApiController::class, 'deleteDbaProfile']);
$router->get('/api/v1/olt-management/dba-profile/{id}/cli-preview', [OltManagementApiController::class, 'dbaProfileCliPreview']);

/* =========================================================
 * LINE PROFILES
 * ========================================================= */
$router->get('/api/v1/olt-management/line-profiles', [OltManagementApiController::class, 'lineProfiles']);
$router->get('/api/v1/olt-management/line-profile/{id}', [OltManagementApiController::class, 'lineProfile']);
$router->post('/api/v1/olt-management/line-profile/create', [OltManagementApiController::class, 'createLineProfile']);
$router->post('/api/v1/olt-management/line-profile/update/{id}', [OltManagementApiController::class, 'updateLineProfile']);
$router->post('/api/v1/olt-management/line-profile/delete', [OltManagementApiController::class, 'deleteLineProfile']);
$router->get('/api/v1/olt-management/line-profile/{id}/cli-preview', [OltManagementApiController::class, 'lineProfileCliPreview']);

/* =========================================================
 * WAN PROFILES
 * ========================================================= */
$router->get('/api/v1/olt-management/wan-profiles', [OltManagementApiController::class, 'wanProfiles']);
$router->get('/api/v1/olt-management/wan-profile/{id}', [OltManagementApiController::class, 'wanProfile']);
$router->post('/api/v1/olt-management/wan-profile/create', [OltManagementApiController::class, 'createWanProfile']);
$router->post('/api/v1/olt-management/wan-profile/update/{id}', [OltManagementApiController::class, 'updateWanProfile']);
$router->post('/api/v1/olt-management/wan-profile/delete', [OltManagementApiController::class, 'deleteWanProfile']);
$router->get('/api/v1/olt-management/wan-profile/{id}/cli-preview', [OltManagementApiController::class, 'wanProfileCliPreview']);

/* =========================================================
 * TR069 PROFILES
 * ========================================================= */
$router->get('/api/v1/olt-management/tr069-profiles', [OltManagementApiController::class, 'tr069Profiles']);
$router->get('/api/v1/olt-management/tr069-profile/{id}', [OltManagementApiController::class, 'tr069Profile']);
$router->post('/api/v1/olt-management/tr069-profile/create', [OltManagementApiController::class, 'createTr069Profile']);
$router->post('/api/v1/olt-management/tr069-profile/update/{id}', [OltManagementApiController::class, 'updateTr069Profile']);
$router->post('/api/v1/olt-management/tr069-profile/delete', [OltManagementApiController::class, 'deleteTr069Profile']);
$router->get('/api/v1/olt-management/tr069-profile/{id}/cli-preview', [OltManagementApiController::class, 'tr069ProfileCliPreview']);

/* =========================================================
 * SRV PROFILES
 * ========================================================= */
$router->get('/api/v1/olt-management/srv-profiles', [OltManagementApiController::class, 'srvProfiles']);
$router->get('/api/v1/olt-management/srv-profile/{id}', [OltManagementApiController::class, 'srvProfile']);
$router->post('/api/v1/olt-management/srv-profile/create', [OltManagementApiController::class, 'createSrvProfile']);
$router->post('/api/v1/olt-management/srv-profile/update/{id}', [OltManagementApiController::class, 'updateSrvProfile']);
$router->post('/api/v1/olt-management/srv-profile/delete', [OltManagementApiController::class, 'deleteSrvProfile']);
$router->get('/api/v1/olt-management/srv-profile/{id}/cli-preview', [OltManagementApiController::class, 'srvProfileCliPreview']);

/* =========================================================
 * CONTROL BOARD VLAN BINDINGS
 * ========================================================= */
$router->get('/api/v1/olt-management/control-board/{oltId}/vlan-workspace', [OltManagementApiController::class, 'controlBoardVlanWorkspace']);
$router->get('/api/v1/olt-management/control-board/{oltId}/vlan-options', [OltManagementApiController::class, 'controlBoardVlanOptions']);

$router->post('/api/v1/olt-management/control-board/vlan-bindings', [OltManagementApiController::class, 'createControlBoardVlanBinding']);
$router->post('/api/v1/olt-management/control-board/vlan-bindings/delete', [OltManagementApiController::class, 'deleteControlBoardVlanBinding']);

/* =========================================================
 * PON PORT SVLAN ASSIGNMENT
 * ========================================================= */
$router->get('/api/v1/olt-management/pon-port/{oltId}/svlan-options', [OltManagementApiController::class, 'ponSvlanOptions']);
$router->post('/api/v1/olt-management/pon-port/assign-svlan', [OltManagementApiController::class, 'assignPonSvlan']);

$router->post('/api/v1/olt-management/pon-port/unassign-svlan', [OltManagementApiController::class, 'unassignPonSvlan']);
$router->post('/api/v1/olt-management/pon-port/unassign-svlan', [OltManagementApiController::class, 'unassignPonSvlan']);