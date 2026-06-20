<?php

use App\Modules\ServiceProvisioning\Controllers\ServiceProvisioningController;

$router->get('/service-provisioning',[ServiceProvisioningController::class,'index']);
