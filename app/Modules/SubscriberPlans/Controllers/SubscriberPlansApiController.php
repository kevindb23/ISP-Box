<?php

namespace App\Modules\SubscriberPlans\Controllers;

use Framework\ApiController;
use App\Modules\SubscriberPlans\Services\SubscriberPlansService;

class SubscriberPlansApiController extends ApiController
{
    private SubscriberPlansService $service;

    public function __construct(SubscriberPlansService $service)
    {
        $this->service = $service;
    }

    public function index(): void
    {
        $this->success($this->service->getAll(), 'Subscriber plans loaded.');
    }

    public function store(): void
    {
        $result = $this->service->create($this->request()->input());

        $this->serviceResult($result, 201);
    }

    public function update($id): void
    {
        $result = $this->service->update($id, $this->request()->input());

        $this->serviceResult($result);
    }

    public function delete(): void
    {
        $id = $this->request()->value('id');

        $result = $this->service->delete($id);

        $this->serviceResult($result);
    }
}
