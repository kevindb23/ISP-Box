<?php

namespace App\Modules\Branding\DTOs;

final class BrandingDTO
{
    public function __construct(
        public string $companyName,
        public string $portalTitle,
        public string $companyAddress,
        public string $supportEmail,
        public string $supportPhone,
        public string $tin,
        public string $website,
        public string $logoPath,
        public bool $removeLogo = false
    ) {
    }

    public static function fromArray(array $input, array $current = []): self
    {
        return new self(
            companyName: trim((string)($input['company_name'] ?? '')),
            portalTitle: trim((string)($input['logo_text'] ?? $input['portal_title'] ?? $current['portal_title'] ?? '1WAN')),
            companyAddress: trim((string)($input['company_address'] ?? '')),
            supportEmail: trim((string)($input['support_email'] ?? '')),
            supportPhone: trim((string)($input['support_phone'] ?? '')),
            tin: trim((string)($input['tin'] ?? '')),
            website: trim((string)($input['website'] ?? '')),
            logoPath: trim((string)($input['logo_path'] ?? $current['logo_path'] ?? '')),
            removeLogo: (string)($input['remove_logo'] ?? '0') === '1'
        );
    }

    public function toArray(): array
    {
        return [
            'company_name' => $this->companyName,
            'business_name' => $this->companyName,
            'portal_title' => $this->portalTitle,
            'company_address' => $this->companyAddress,
            'support_email' => $this->supportEmail,
            'support_phone' => $this->supportPhone,
            'tin' => $this->tin,
            'website' => $this->website,
            'logo_path' => $this->removeLogo ? '' : $this->logoPath,
            'primary_color' => '#3b82f6',
        ];
    }
}
