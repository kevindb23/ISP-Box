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
    | List Subscribers
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        $page = $_GET['page'] ?? 1;
        $limit = $_GET['limit'] ?? 50;

        $data = $this->subscribers->paginate($page, $limit);

        $this->success([
            "items" => $data,
            "page" => (int)$page,
            "limit" => (int)$limit
        ]);
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
    | Show Subscriber
    |--------------------------------------------------------------------------
    */

    public function show($id)
    {
        $subscriber = $this->subscribers->find($id);

        if (!$subscriber) {
            $this->error("Subscriber not found", 404);
            return;
        }

        $this->success($subscriber);
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
