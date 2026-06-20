<?php

namespace App\Modules\SubscriberPlans\Controllers;

use Framework\Controller;
use App\Modules\SubscriberPlans\Services\SubscriberPlansService;

class SubscriberPlansApiController extends Controller
{
    private SubscriberPlansService $service;

    public function __construct(SubscriberPlansService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        header('Content-Type: application/json');

        echo json_encode([
            'success' => true,
            'data'    => $this->service->getAll()
        ]);
        exit;
    }

    public function store()
    {
        $result = $this->service->create($_POST);

        header('Content-Type: application/json');
        echo json_encode($result);
        exit;
    }

    public function update($id)
    {
        $result = $this->service->update($id, $_POST);

        header('Content-Type: application/json');
        echo json_encode($result);
        exit;
    }

    public function delete()
    {
        $id = $_POST['id'] ?? null;

        $result = $this->service->delete($id);

        header('Content-Type: application/json');
        echo json_encode($result);
        exit;
    }
}
