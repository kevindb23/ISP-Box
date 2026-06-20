<?php

namespace App\Modules\Billing\Services;

use App\Modules\Audit\Services\AuditService;
use App\Modules\Billing\Repositories\CollectionRepository;

class CollectionService
{
    private CollectionRepository $collections;
    private AuditService $audit;

    public function __construct(
        CollectionRepository $collections,
        AuditService $audit
    ) {
        $this->collections = $collections;
        $this->audit = $audit;
    }

    public function aging(array $filters = []): array
    {
        $asOfDate = $filters['as_of_date'] ?? date('Y-m-d');

        $normalizedFilters = [
            'as_of_date' => $asOfDate,
            'bucket' => $filters['bucket'] ?? null,
            'subscriber_id' => !empty($filters['subscriber_id'])
                ? (int)$filters['subscriber_id']
                : null,
            'service_id' => !empty($filters['service_id'])
                ? (int)$filters['service_id']
                : null,
            'search' => trim((string)($filters['search'] ?? '')),
            'limit' => (int)($filters['limit'] ?? 100),
            'offset' => (int)($filters['offset'] ?? 0),
        ];

        if ($normalizedFilters['limit'] <= 0) {
            $normalizedFilters['limit'] = 100;
        }

        if ($normalizedFilters['limit'] > 1000) {
            $normalizedFilters['limit'] = 1000;
        }

        if ($normalizedFilters['offset'] < 0) {
            $normalizedFilters['offset'] = 0;
        }

        $result = [
            'as_of_date' => $asOfDate,
            'summary' => $this->collections->agingSummary($asOfDate),
            'items' => $this->collections->agingItems($normalizedFilters),
            'total' => $this->collections->agingCount($normalizedFilters),
        ];

        $this->audit->log(
            'BILLING',
            'VIEW_AGING_REPORT',
            sprintf(
                'Collection aging report viewed. Date=%s Bucket=%s Records=%d',
                $asOfDate,
                $normalizedFilters['bucket'] ?? 'ALL',
                (int)$result['total']
            )
        );

        return $result;
    }
}