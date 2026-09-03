<?php

use App\Modules\Branding\Controllers\BrandingController;

$router->get('/branding', [BrandingController::class, 'index']);