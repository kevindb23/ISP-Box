<?php

namespace App\Modules\VlanManagement\Controllers;

use App\Infrastructure\Database\DatabaseConnection;
use App\Modules\VlanManagement\Repositories\VlanManagementRepository;
use App\Modules\VlanManagement\Services\VlanManagementService;
use Framework\Controller;

class VlanManagementController extends Controller
{
    private VlanManagementService $service;

    public function __construct()
    {
        $pdo = (new DatabaseConnection())->get();
        $repo = new VlanManagementRepository($pdo);
        $this->service = new VlanManagementService($repo);
    }

    public function index()
    {
        return $this->view('VlanManagement/index', [
            'summary' => $this->service->getSummary(),
        ]);
    }
}