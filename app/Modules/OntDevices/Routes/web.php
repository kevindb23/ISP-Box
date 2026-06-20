<?php

use App\Modules\OntDevices\Controllers\OntDevicesController;

$router->get('/ont-devices', [OntDevicesController::class, 'index']);
$router->get('/ont-devices/acs-device/{id}', [OntDevicesController::class, 'acsDevice']);