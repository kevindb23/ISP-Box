<?php

namespace App\Modules\Audit\Controllers;

use Framework\Controller;

class AuditController extends Controller
{
    public function index()
    {
        return $this->view('Audit/index', [
            'items' => []
        ]);
    }
}