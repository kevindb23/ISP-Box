<?php

namespace App\Modules\CgnatManagement\Controllers;

use App\Infrastructure\Database\DatabaseConnection;
use App\Modules\CgnatManagement\Repositories\BngSettingRepository;
use App\Modules\CgnatManagement\Repositories\CgnatManagementRepository;
use App\Modules\CgnatManagement\Services\BngConnectionService;
use App\Modules\CgnatManagement\Services\CgnatManagementService;
use Framework\Controller;

class CgnatManagementController extends Controller
{
    private CgnatManagementService $service;

    public function __construct()
    {
        $pdo = (new DatabaseConnection())->get();

        $repo = new CgnatManagementRepository($pdo);
        $bngRepo = new BngSettingRepository($pdo);
        $bngService = new BngConnectionService($bngRepo);

        $this->service = new CgnatManagementService($repo, $bngService);
    }

    public function index()
    {
        return $this->view('CgnatManagement/index', $this->service->getIndexData());
    }
}