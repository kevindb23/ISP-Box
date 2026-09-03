<?php

namespace App\Modules\VlanManagement\Controllers;

use App\Modules\VlanManagement\Services\VlanManagementService;
use Framework\Controller;

class VlanManagementController extends Controller
{
    private VlanManagementService $service;

    public function __construct(VlanManagementService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        return $this->view('VlanManagement/index', [
            'summary' => $this->service->getSummary(),
        ]);
    }
}
