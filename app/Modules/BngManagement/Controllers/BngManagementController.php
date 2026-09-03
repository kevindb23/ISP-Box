<?php

namespace App\Modules\BngManagement\Controllers;

use Framework\Controller;

final class BngManagementController extends Controller
{
    public function index(): mixed
    {
        return $this->view('BngManagement/index');
    }
}
