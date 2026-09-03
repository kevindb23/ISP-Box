<?php

namespace App\Modules\SystemSettings\Controllers;

use Framework\Controller;

class SystemSettingsController extends Controller
{
    public function index()
    {
        return $this->view('SystemSettings/index', [
            'title' => 'System Settings',
            'section' => 'system-settings',
        ]);
    }
}
