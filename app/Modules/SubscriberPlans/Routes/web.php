<?php

use App\Modules\SubscriberPlans\Controllers\SubscriberPlansController;

$router->get('/subscriber-plans', [SubscriberPlansController::class, 'index']);

$router->post('/subscriber-plans/store', [SubscriberPlansController::class, 'store']);
$router->post('/subscriber-plans/update/{id}', [SubscriberPlansController::class, 'update']);
$router->post('/subscriber-plans/delete', [SubscriberPlansController::class, 'delete']);
