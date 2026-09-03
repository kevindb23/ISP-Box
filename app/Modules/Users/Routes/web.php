<?php

use App\Modules\Users\Controllers\UsersController;
$router->get('/users', [UsersController::class, 'index']);
