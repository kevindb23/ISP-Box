<?php

namespace App\Modules\SubscriberPlans\SystemSettings\Controllers;

use Framework\Controller;

class SystemSettingsController extends Controller
{
    public function index()
    {
        return $this->view('SystemSettings/index', []);
    }
}