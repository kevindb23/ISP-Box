<?php

namespace App\Modules\WorkOrders\Controllers;

use Framework\Controller;

class WorkOrdersController extends Controller
{
    public function index()
    {
        return $this->view('WorkOrders/index', [
            'title' => 'Work Orders',
        ]);
    }
}