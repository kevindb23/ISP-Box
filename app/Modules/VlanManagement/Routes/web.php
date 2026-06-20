<?php

use App\Modules\VlanManagement\Controllers\VlanManagementController;

$router->get('/vlan-management', [VlanManagementController::class, 'index']);