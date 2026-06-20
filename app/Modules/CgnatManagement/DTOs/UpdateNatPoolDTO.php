<?php

namespace App\Modules\CgnatManagement\DTOs;

class UpdateNatPoolDTO extends CreateNatPoolDTO
{
    public int $id;

    public function __construct(array $data)
    {
        parent::__construct($data);
        $this->id = (int)($data['id'] ?? 0);
    }

    public function toArray(): array
    {
        return parent::toArray() + ['id' => $this->id];
    }
}