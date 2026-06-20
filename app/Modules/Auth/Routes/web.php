<?php

use App\Modules\Auth\Controllers\AuthController;

$router->get('/', function () {
    header("Location: /login");
    exit;
});

$router->get('/login', [AuthController::class, 'loginPage']);
$router->post('/login', [AuthController::class, 'login']);
