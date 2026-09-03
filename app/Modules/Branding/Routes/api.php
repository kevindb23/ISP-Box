<?php

use App\Modules\Branding\Controllers\BrandingApiController;

$router->get('/api/v1/branding', [BrandingApiController::class, 'show']);
$router->post('/api/v1/branding', [BrandingApiController::class, 'update']);