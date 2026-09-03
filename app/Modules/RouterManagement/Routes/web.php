<?php
use App\Modules\RouterManagement\Controllers\RouterManagementController;
$router->get('/routers',[RouterManagementController::class,'index']);
