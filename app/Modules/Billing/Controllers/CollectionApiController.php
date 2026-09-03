<?php

namespace App\Modules\Billing\Controllers;

use App\Modules\Billing\Services\CollectionService;
use Framework\ApiController;
use Throwable;

class CollectionApiController extends ApiController
{
    private CollectionService $service;

    public function __construct(CollectionService $service)
    {
        $this->service = $service;
    }

    public function aging(): void
    {
        try {
            $query = $this->request()->query();
            $filters = [
                'as_of_date' => $query['as_of_date'] ?? null,
                'bucket' => $query['bucket'] ?? null,
                'subscriber_id' => $query['subscriber_id'] ?? null,
                'service_id' => $query['service_id'] ?? null,
                'search' => $query['search'] ?? null,
                'limit' => $query['limit'] ?? 100,
                'offset' => $query['offset'] ?? 0,
            ];

            $this->success($this->service->aging($filters));
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 500);
        }
    }
}
