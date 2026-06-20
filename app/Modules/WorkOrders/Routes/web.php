<?php

use App\Modules\WorkOrders\Controllers\WorkOrdersController;

$router->get('/work-orders', [WorkOrdersController::class, 'index']);