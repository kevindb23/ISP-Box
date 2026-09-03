<?php

use App\Modules\SystemSettings\Controllers\SystemSettingsController;

$router->get('/system-settings', [SystemSettingsController::class, 'index']);
