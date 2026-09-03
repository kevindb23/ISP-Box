<?php

namespace App\Modules\CgnatManagement\Controllers;

use App\Modules\CgnatManagement\Services\CgnatManagementService;
use Framework\Controller;

class CgnatManagementController extends Controller
{
    private CgnatManagementService $service;

    public function __construct(CgnatManagementService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        return $this->view('CgnatManagement/index', $this->service->getIndexData());
    }
}
