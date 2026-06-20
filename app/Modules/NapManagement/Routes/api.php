<?php

use App\Modules\NapManagement\Controllers\NapManagementApiController;

/*
|--------------------------------------------------------------------------
| MAIN DATA API
|--------------------------------------------------------------------------
*/

$router->get('/api/v1/nap-management/odfs', [NapManagementApiController::class, 'odfs']);
$router->get('/api/v1/nap-management/odf/{id}', [NapManagementApiController::class, 'odf']);

$router->get('/api/v1/nap-management/lcps', [NapManagementApiController::class, 'lcps']);
$router->get('/api/v1/nap-management/lcp/{id}', [NapManagementApiController::class, 'lcp']);

$router->get('/api/v1/nap-management/naps', [NapManagementApiController::class, 'naps']);
$router->get('/api/v1/nap-management/nap/{id}', [NapManagementApiController::class, 'nap']);

/*
|--------------------------------------------------------------------------
| SUPPORTING API
|--------------------------------------------------------------------------
*/

$router->get('/api/v1/nap-management/odf-ports/{odfId}', [NapManagementApiController::class, 'odfPorts']);
$router->get('/api/v1/nap-management/lcp-ports/{lcpId}', [NapManagementApiController::class, 'lcpPorts']);
$router->get('/api/v1/nap-management/nap-parent-ports/{napId}', [NapManagementApiController::class, 'napParentPorts']);

$router->get('/api/v1/nap-management/odf-port-grid', [NapManagementApiController::class, 'odfPortGrid']);
$router->get('/api/v1/nap-management/lcp-port-grid', [NapManagementApiController::class, 'lcpPortGrid']);
$router->get('/api/v1/nap-management/nap-port-grid', [NapManagementApiController::class, 'napPortGrid']);

$router->get('/api/v1/nap-management/odf-candidates', [NapManagementApiController::class, 'odfCandidates']);
$router->get('/api/v1/nap-management/lcp-candidates', [NapManagementApiController::class, 'lcpCandidates']);
$router->get('/api/v1/nap-management/nap-candidates', [NapManagementApiController::class, 'napCandidates']);

$router->get('/api/v1/nap-management/topology', [NapManagementApiController::class, 'topology']);
$router->get('/api/v1/nap-management/topology/objects', [NapManagementApiController::class, 'topologyObjects']);

$router->get('/api/v1/nap-management/design-profiles', [NapManagementApiController::class, 'designProfiles']);

/*
|--------------------------------------------------------------------------
| WRITE API
|--------------------------------------------------------------------------
*/

$router->post('/api/v1/nap-management/odf/create', [NapManagementApiController::class, 'createOdf']);
$router->post('/api/v1/nap-management/odf/update/{id}', [NapManagementApiController::class, 'updateOdf']);
$router->post('/api/v1/nap-management/odf/delete', [NapManagementApiController::class, 'deleteOdf']);

$router->post('/api/v1/nap-management/lcp/create', [NapManagementApiController::class, 'createLcp']);
$router->post('/api/v1/nap-management/lcp/update/{id}', [NapManagementApiController::class, 'updateLcp']);
$router->post('/api/v1/nap-management/lcp/delete', [NapManagementApiController::class, 'deleteLcp']);

$router->post('/api/v1/nap-management/nap/create', [NapManagementApiController::class, 'createNap']);
$router->post('/api/v1/nap-management/nap/update/{id}', [NapManagementApiController::class, 'updateNap']);
$router->post('/api/v1/nap-management/nap/delete', [NapManagementApiController::class, 'deleteNap']);

/*
|--------------------------------------------------------------------------
| PLANNER API
|--------------------------------------------------------------------------
*/

$router->get('/api/v1/nap-management/planner/objects', [NapManagementApiController::class, 'plannerObjects']);

$router->post('/api/v1/nap-management/planner/connect', [NapManagementApiController::class, 'plannerConnect']);
$router->post('/api/v1/nap-management/planner/delete-link', [NapManagementApiController::class, 'plannerDeleteLink']);

$router->post('/api/v1/nap-management/planner/object-connect', [NapManagementApiController::class, 'plannerConnectObject']);
$router->post('/api/v1/nap-management/planner/object-delete-link', [NapManagementApiController::class, 'plannerDeleteObjectLink']);
$router->post('/api/v1/nap-management/planner/rebuild', [NapManagementApiController::class, 'rebuildPlannerProjection']);

/*
|--------------------------------------------------------------------------
| TOPOLOGY / DESIGN API
|--------------------------------------------------------------------------
*/

$router->post('/api/v1/nap-management/topology/object/create', [NapManagementApiController::class, 'createTopologyObject']);
$router->post('/api/v1/nap-management/topology/link/create', [NapManagementApiController::class, 'createTopologyLink']);
$router->post('/api/v1/nap-management/topology/link/delete', [NapManagementApiController::class, 'deleteTopologyLink']);

$router->post('/api/v1/nap-management/generate-design', [NapManagementApiController::class, 'generateDesign']);

/*
|--------------------------------------------------------------------------
| MAINTENANCE API
|--------------------------------------------------------------------------
*/

$router->post('/api/v1/nap-management/odf/{id}/maintenance', [NapManagementApiController::class, 'setOdfMaintenance']);
$router->post('/api/v1/nap-management/lcp/{id}/maintenance', [NapManagementApiController::class, 'setLcpMaintenance']);
$router->post('/api/v1/nap-management/nap/{id}/maintenance', [NapManagementApiController::class, 'setNapMaintenance']);


$router->post('/api/v1/nap-management/planner/node-position', [NapManagementApiController::class, 'savePlannerNodePosition']);
$router->post('/api/v1/nap-management/planner/reset-layout', [NapManagementApiController::class, 'resetPlannerLayout']);