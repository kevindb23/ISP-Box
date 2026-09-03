<?php

namespace App\Modules\PaymentGateway\Controllers;

use Framework\Controller;

class PaymentGatewayController extends Controller
{
    public function index()
    {
        return $this->view('PaymentGateway/index');
    }

}
