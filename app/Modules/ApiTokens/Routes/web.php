<?php

use App\Modules\ApiTokens\Controllers\ApiTokensController;

$router->get('/api-tokens',[ApiTokensController::class,'index']);
