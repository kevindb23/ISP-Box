<?php

use App\Modules\Email\Controllers\EmailController;

$router->get('/email', [EmailController::class, 'index']);
