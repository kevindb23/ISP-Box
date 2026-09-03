<?php

namespace App\Modules\Api\v1\Controllers;

use Framework\ApiController;
use App\Modules\Subscribers\Services\SubscriberService;

class SubscriberController extends ApiController
{
    private SubscriberService $subscribers;

    public function __construct(SubscriberService $subscribers)
    {
        $this->subscribers = $subscribers;
    }

    /*
    |--------------------------------------------------------------------------
    | Create Subscriber
    |--------------------------------------------------------------------------
    */

    public function store()
    {
        $input = json_decode(file_get_contents("php://input"), true);

        $result = $this->subscribers->create($input);

        $this->success($result, "Subscriber created");
    }

    /*
    |--------------------------------------------------------------------------
    | Delete Subscriber
    |--------------------------------------------------------------------------
    */

    public function delete($id)
    {
        $this->subscribers->delete($id);

        $this->success([], "Subscriber deleted");
    }
}
