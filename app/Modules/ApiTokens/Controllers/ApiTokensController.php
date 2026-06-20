<?php

namespace App\Modules\ApiTokens\Controllers;

use Framework\Controller;

class ApiTokensController extends Controller
{
    public function index()
    {
        return $this->view('ApiTokens/index',['items'=>[]]);
    }
}
