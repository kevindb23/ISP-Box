<?php

namespace App\Modules\Billing\Controllers;

use App\Modules\Billing\Repositories\CollectionRepository;
use App\Modules\Billing\Services\CollectionService;
use Framework\ApiController;
use Framework\DatabaseConnection;
use Throwable;

class CollectionApiController extends ApiController
{
    private CollectionService $service;

    public function __construct(DatabaseConnection $database)
    {
        $db = $database->get();

        $collectionRepo = new CollectionRepository($db);

        $this->service = new CollectionService($collectionRepo);
    }

    public function aging(): void
    {
        try {
            $filters = [
                'as_of_date' => $_GET['as_of_date'] ?? null,
                'bucket' => $_GET['bucket'] ?? null,
                'subscriber_id' => $_GET['subscriber_id'] ?? null,
                'service_id' => $_GET['service_id'] ?? null,
                'search' => $_GET['search'] ?? null,
                'limit' => $_GET['limit'] ?? 100,
                'offset' => $_GET['offset'] ?? 0,
            ];

            $this->success($this->service->aging($filters));
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 500);
        }
    }
}