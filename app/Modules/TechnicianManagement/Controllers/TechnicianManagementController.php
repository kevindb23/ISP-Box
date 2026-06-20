<?php

namespace App\Modules\TechnicianManagement\Controllers;

use Framework\Controller;

class TechnicianManagementController extends Controller
{
    public function index()
    {
        return $this->view('TechnicianManagement/index', [
            'title' => 'Technician Management',
        ]);
    }
}