<?php

namespace App\Modules\Users\Controllers;

use Framework\Controller;

class UsersController extends Controller
{
    public function index()
    {
        return $this->view('Users/index', [
            'items' => []
        ]);
    }
}