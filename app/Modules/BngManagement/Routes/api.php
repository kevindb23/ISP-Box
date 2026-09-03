<?php

use App\Modules\BngManagement\Controllers\BngManagementApiController;

$router->get('/api/v1/bng/setting', [BngManagementApiController::class, 'setting']);
$router->post('/api/v1/bng/setting', [BngManagementApiController::class, 'saveSetting']);
$router->post('/api/v1/bng/setting/delete', [BngManagementApiController::class, 'deleteSetting']);
$router->post('/api/v1/bng/setting/test', [BngManagementApiController::class, 'testConnection']);
$router->get('/api/v1/bng/setting/host-key', [BngManagementApiController::class, 'scanHostKey']);
$router->post('/api/v1/bng/setting/host-key/trust', [BngManagementApiController::class, 'trustHostKey']);
$router->get('/api/v1/bng/runtime', [BngManagementApiController::class, 'runtime']);
$router->get('/api/v1/bng/accel-ppp', [BngManagementApiController::class, 'accel']);
$router->post('/api/v1/bng/accel-ppp', [BngManagementApiController::class, 'saveAccel']);
$router->get('/api/v1/bng/accel-ppp/preview', [BngManagementApiController::class, 'previewAccel']);
$router->post('/api/v1/bng/accel-ppp/preview-draft', [BngManagementApiController::class, 'previewAccelDraft']);
$router->post('/api/v1/bng/accel-ppp/stage', [BngManagementApiController::class, 'stageAccel']);
$router->post('/api/v1/bng/accel-ppp/activate-maintenance', [BngManagementApiController::class, 'activateAccel']);
$router->post('/api/v1/bng/reconciliation/install', [BngManagementApiController::class, 'installBootRecovery']);
