<?php

use App\Modules\Users\Controllers\UsersController;
use App\Modules\Users\Controllers\UsersApiController;

$router->get('/users', [UsersController::class, 'index']);

$router->get('/api/v1/users', [UsersApiController::class, 'index']);
$router->get('/api/v1/users/{id}', [UsersApiController::class, 'show']);
$router->post('/api/v1/users/store', [UsersApiController::class, 'store']);
$router->post('/api/v1/users/update/{id}', [UsersApiController::class, 'update']);
$router->post('/api/v1/users/delete', [UsersApiController::class, 'delete']);
$router->post('/api/v1/users/reset-password/{id}', [UsersApiController::class, 'resetPassword']);