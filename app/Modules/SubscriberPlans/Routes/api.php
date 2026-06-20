<?php

use App\Modules\SubscriberPlans\Controllers\SubscriberPlansApiController;

$router->get('/api/v1/subscriber-plans', [SubscriberPlansApiController::class, 'index']);
$router->post('/api/v1/subscriber-plans/store', [SubscriberPlansApiController::class, 'store']);
$router->post('/api/v1/subscriber-plans/update/{id}', [SubscriberPlansApiController::class, 'update']);
$router->post('/api/v1/subscriber-plans/delete', [SubscriberPlansApiController::class, 'delete']);
