<?php

namespace App\Modules\SystemSettings\Entities;

class SystemConfig
{
    public function __construct(
        public string $key,
        public string $value,
        public ?string $description = null
    ) {
    }
}
