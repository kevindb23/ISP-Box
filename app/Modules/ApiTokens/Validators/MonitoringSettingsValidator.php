<?php

namespace App\Modules\ApiTokens\Validators;

use App\Modules\ApiTokens\DTOs\MonitoringSettingsDTO;

final class MonitoringSettingsValidator
{
    public function validate(MonitoringSettingsDTO $dto): array
    {
        $errors = [];
        if ($dto->hqUrl !== '' && !filter_var($dto->hqUrl, FILTER_VALIDATE_URL)) $errors[] = 'HQ URL is invalid.';
        $scheme = strtolower((string) parse_url($dto->hqUrl, PHP_URL_SCHEME));
        if ($dto->hqUrl !== '' && !in_array($scheme, ['http','https'], true)) $errors[] = 'HQ URL must use HTTP or HTTPS.';
        if ($scheme === 'http' && !$dto->allowInsecureHttp) $errors[] = 'Explicitly allow insecure HTTP before using an HTTP HQ URL.';
        if ($scheme === 'https' && !$dto->verifyTls) $errors[] = 'TLS verification must remain enabled for HTTPS monitoring.';
        if ($dto->enabled && $dto->hqUrl === '') $errors[] = 'HQ URL is required before delivery can be enabled.';
        if ($dto->retentionDays < 1 || $dto->retentionDays > 365) $errors[] = 'Retention must be between 1 and 365 days.';
        if ($dto->diskWarningPercent < 1 || $dto->diskWarningPercent > 99) $errors[] = 'Disk warning threshold must be between 1 and 99 percent.';
        if ($dto->diskCriticalPercent < 2 || $dto->diskCriticalPercent > 100) $errors[] = 'Disk critical threshold must be between 2 and 100 percent.';
        if ($dto->diskWarningPercent >= $dto->diskCriticalPercent) $errors[] = 'Disk warning threshold must be lower than the critical threshold.';
        if ($dto->memoryWarningPercent < 1 || $dto->memoryWarningPercent > 100) $errors[] = 'Memory warning threshold must be between 1 and 100 percent.';
        if ($dto->heartbeatSeconds < 30 || $dto->heartbeatSeconds > 3600) $errors[] = 'Heartbeat interval must be between 30 and 3600 seconds.';
        return $errors;
    }
}
