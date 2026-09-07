<?php

use App\Modules\Audit\Controllers\AuditController;
use App\Modules\Audit\Controllers\AuditApiController;

$router->get('/audit', [AuditController::class, 'index']);

$router->get('/api/v1/audit', [AuditApiController::class, 'index']);
$router->get('/api/v1/notifications', [AuditApiController::class, 'notifications']);
<<<<<<< HEAD
$router->post('/api/v1/notifications/{id}/read', [AuditApiController::class, 'markRead']);
=======
>>>>>>> origin/main
$router->get('/api/v1/audit/{id}', [AuditApiController::class, 'show']);
