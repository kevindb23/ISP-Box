<?php

use App\Modules\Email\Controllers\EmailApiController;

$router->get('/api/v1/email', [EmailApiController::class, 'index']);
$router->post('/api/v1/email', [EmailApiController::class, 'save']);
$router->post('/api/v1/email/test', [EmailApiController::class, 'test']);
$router->post('/api/v1/email/test-email', [EmailApiController::class, 'testEmail']);
