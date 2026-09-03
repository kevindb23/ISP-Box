<?php

namespace App\Modules\Branding\Validators;

use App\Modules\Branding\DTOs\BrandingDTO;

final class BrandingValidator
{
    public function validate(BrandingDTO $dto): array
    {
        $errors = [];
        if ($dto->companyName === '') $errors['company_name'] = 'Company name is required.';
        if (strlen($dto->portalTitle) > 60) $errors['portal_title'] = 'Logo text must not exceed 60 characters.';
        if ($dto->supportEmail !== '' && !filter_var($dto->supportEmail, FILTER_VALIDATE_EMAIL)) {
            $errors['support_email'] = 'Support email is invalid.';
        }
        if ($dto->website !== '' && !filter_var($dto->website, FILTER_VALIDATE_URL)) {
            $errors['website'] = 'Website URL is invalid.';
        }
        return $errors;
    }
}
