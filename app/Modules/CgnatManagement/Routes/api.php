<?php

use App\Modules\CgnatManagement\Controllers\CgnatManagementApiController;

$router->get('/api/v1/cgnat/pools', [CgnatManagementApiController::class, 'pools']);
$router->post('/api/v1/cgnat/pools', [CgnatManagementApiController::class, 'createPool']);
$router->post('/api/v1/cgnat/pools/{id}/update', [CgnatManagementApiController::class, 'updatePool']);
$router->post('/api/v1/cgnat/pools/{id}/delete', [CgnatManagementApiController::class, 'deletePool']);
$router->get('/api/v1/cgnat/pools/{id}/preview', [CgnatManagementApiController::class, 'previewPool']);
$router->post('/api/v1/cgnat/pools/{id}/apply', [CgnatManagementApiController::class, 'applyPool']);

$router->get('/api/v1/cgnat/deployments', [CgnatManagementApiController::class, 'deployments']);
$router->get('/api/v1/cgnat/usage', [CgnatManagementApiController::class, 'usage']);

$router->get('/api/v1/cgnat/config', [CgnatManagementApiController::class, 'config']);
$router->post('/api/v1/cgnat/config', [CgnatManagementApiController::class, 'saveConfig']);
$router->post('/api/v1/cgnat/config/apply', [CgnatManagementApiController::class, 'applyConfig']);

$router->get('/api/v1/cgnat/bng-setting', [CgnatManagementApiController::class, 'bngSetting']);
$router->post('/api/v1/cgnat/bng-setting', [CgnatManagementApiController::class, 'saveBngSetting']);
$router->post('/api/v1/cgnat/bng-setting/test', [CgnatManagementApiController::class, 'testBngConnection']);
$router->get('/api/v1/cgnat/bng-runtime', [CgnatManagementApiController::class, 'bngRuntime']);

$router->post('/api/v1/cgnat/bng/create-svlan-interface', [CgnatManagementApiController::class, 'createSvlanInterface']);

$router->get('/api/v1/cgnat/svlans', [CgnatManagementApiController::class, 'availableSvlans']);