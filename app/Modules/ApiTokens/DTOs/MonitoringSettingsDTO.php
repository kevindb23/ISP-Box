<?php

namespace App\Modules\ApiTokens\DTOs;

final class MonitoringSettingsDTO
{
    public function __construct(
        public readonly bool $enabled,
        public readonly string $hqUrl,
        public readonly bool $allowInsecureHttp,
        public readonly bool $verifyTls,
        public readonly int $retentionDays,
        public readonly int $diskWarningPercent,
        public readonly int $diskCriticalPercent,
        public readonly int $memoryWarningPercent,
        public readonly int $heartbeatSeconds,
    ) {}
}
