<?php

namespace App\Modules\StaffAttendance\Validators;

use App\Modules\StaffAttendance\DTOs\AttendanceCommandDTO;

final class AttendanceCommandValidator
{
    private const STATUSES = ['AVAILABLE', 'BUSY', 'ON_BREAK', 'ON_SITE', 'TRAVELING'];

    public function validateStatus(AttendanceCommandDTO $dto): array
    {
        return in_array($dto->status, self::STATUSES, true)
            ? []
            : ['status' => 'Invalid duty status.'];
    }
}
