<?php

namespace App\Modules\SubscriberPortal\DTOs;

class SubscriberInvoiceFilterDTO
{
    public ?string $status;
    public ?string $search;
    public int $limit;
    public int $offset;

    public function __construct(array $data = [])
    {
        $status = strtoupper(trim((string)($data['status'] ?? '')));
        $search = trim((string)($data['search'] ?? ''));

        $this->status = $status !== '' ? $status : null;
        $this->search = $search !== '' ? $search : null;

        $this->limit = isset($data['limit'])
            ? max(1, min(100, (int)$data['limit']))
            : 50;

        $this->offset = isset($data['offset'])
            ? max(0, (int)$data['offset'])
            : 0;
    }

    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'search' => $this->search,
            'limit' => $this->limit,
            'offset' => $this->offset,
        ];
    }
}
