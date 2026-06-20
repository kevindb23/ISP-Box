<?php

namespace App\Modules\ServiceProvisioning\Controllers;

use Framework\Controller;

class ServiceProvisioningController extends Controller
{
    public function index()
    {
        return $this->view('ServiceProvisioning/index',['items'=>[]]);
    }
}
