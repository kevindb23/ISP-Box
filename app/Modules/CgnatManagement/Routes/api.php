<?php

use App\Modules\CgnatManagement\Controllers\CgnatManagementApiController;

$router->get('/api/v1/cgnat/config', [CgnatManagementApiController::class, 'config']);
$router->post('/api/v1/cgnat/config', [CgnatManagementApiController::class, 'saveConfig']);
$router->post('/api/v1/cgnat/config/apply', [CgnatManagementApiController::class, 'applyConfig']);
$router->post('/api/v1/cgnat/postrouting/remove', [CgnatManagementApiController::class, 'removePostroutingRules']);
