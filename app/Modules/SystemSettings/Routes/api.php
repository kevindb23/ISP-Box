<?php

use App\Modules\SystemSettings\Controllers\SystemSettingsApiController;

$router->get('/api/v1/system-settings/general', [SystemSettingsApiController::class, 'getGeneral']);
$router->post('/api/v1/system-settings/general', [SystemSettingsApiController::class, 'updateGeneral']);
