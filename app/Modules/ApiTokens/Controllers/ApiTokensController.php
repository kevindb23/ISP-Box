<?php

namespace App\Modules\ApiTokens\Controllers;

use App\Modules\ApiTokens\DTOs\CreateApiTokensDTO;
use App\Modules\ApiTokens\Services\ApiTokensService;
use App\Modules\ApiTokens\Validators\CreateApiTokensValidator;
use Framework\Controller;
use Framework\SessionManager;

class ApiTokensController extends Controller
{
    public function __construct(
        private ApiTokensService $service,
        private CreateApiTokensValidator $validator
    )
    {
    }

    public function index()
    {
        $userId = (int)(SessionManager::id() ?? 0);

        return $this->view('ApiTokens/index', [
            'items' => $userId > 0 ? $this->service->forUser($userId) : [],
            'monitoringIdentity' => $userId > 0 ? $this->service->monitoringIdentity() : [],
            'errors' => $userId > 0 ? [] : ['user_id' => 'Authentication required.'],
        ]);
    }
}
