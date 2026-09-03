<?php

namespace App\Modules\Branding\Services;

use App\Modules\Audit\DTOs\AuditEventDTO;
use App\Modules\Audit\Services\AuditService;
use App\Modules\Branding\DTOs\BrandingDTO;
use App\Modules\Branding\Entities\Branding;
use App\Modules\Branding\Repositories\BrandingRepository;
use App\Modules\Branding\Validators\BrandingValidator;
use Exception;

class BrandingService
{
    private BrandingRepository $repo;

    public function __construct(
        BrandingRepository $repo,
        private BrandingValidator $validator,
        private AuditService $audit
    )
    {
        $this->repo = $repo;
    }

    public function getBranding(): array
    {
        $defaults = $this->defaults();
        $row = $this->repo->getBranding();

        if (!$row) {
            return $defaults;
        }

        $branding = array_merge($defaults, array_filter($row, static function ($value) {
            return $value !== null && $value !== '';
        }));

        if (array_key_exists('logo_path', $row)) {
            $branding['logo_path'] = trim((string)$row['logo_path']);
        }

        if (array_key_exists('portal_title', $row)) {
            $branding['portal_title'] = trim((string)$row['portal_title']);
        }

        $branding['logo_text'] = trim((string)($branding['portal_title'] ?? ''));

        return (new Branding($branding))->toArray();
    }

    public function updateBranding(array $input, array $files = []): array
    {
        $current = $this->getBranding();

        $dto = BrandingDTO::fromArray($input, $current);
        $errors = $this->validator->validate($dto);
        if ($errors !== []) {
            throw new Exception((string)reset($errors));
        }
        $data = $dto->toArray();

        if (!empty($files['company_logo']) && is_array($files['company_logo'])) {
            $uploadedLogo = $this->handleLogoUpload($files['company_logo']);

            if ($uploadedLogo !== '') {
                $data['logo_path'] = $uploadedLogo;
            }
        }

        $this->repo->saveBranding($data);
        $updated = $this->getBranding();
        $this->audit->logEvent(new AuditEventDTO(
            module: 'BRANDING', action: 'UPDATE', description: 'Updated portal branding settings.',
            objectType: 'BRANDING', oldValues: $current, newValues: $updated
        ));

        return [
            'message' => 'Branding settings updated successfully.',
            'branding' => $updated,
        ];
    }

    private function handleLogoUpload(array $file): string
    {
        $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error === UPLOAD_ERR_NO_FILE) {
            return '';
        }

        if ($error !== UPLOAD_ERR_OK) {
            throw new Exception('Logo upload failed.');
        }

        $tmpName = (string)($file['tmp_name'] ?? '');
        $size = (int)($file['size'] ?? 0);

        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new Exception('Invalid logo upload.');
        }

        if ($size <= 0 || $size > 2 * 1024 * 1024) {
            throw new Exception('Logo must not exceed 2MB.');
        }

        $mime = mime_content_type($tmpName);

        $allowed = [
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
        ];

        if (!isset($allowed[$mime])) {
            throw new Exception('Logo must be PNG, JPG, or WEBP.');
        }

        $uploadDir = BASE_PATH . '/public/uploads/branding';

        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            throw new Exception('Unable to create logo upload directory.');
        }

        $filename = 'company-logo-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
        $target = $uploadDir . '/' . $filename;

        if (!move_uploaded_file($tmpName, $target)) {
            throw new Exception('Unable to save uploaded logo.');
        }

        return '/uploads/branding/' . $filename;
    }

    private function defaults(): array
    {
        $config = require BASE_PATH . '/config/branding.php';

        return [
            'company_name' => $config['company_name'] ?? ($config['client_name'] ?? 'ISP-In-A-BOX'),
            'business_name' => $config['business_name'] ?? ($config['client_name'] ?? 'ISP-In-A-BOX'),
            'portal_title' => $config['portal_title'] ?? 'Administrator Portal',
            'logo_text' => $config['logo_text'] ?? '1WAN',
            'company_address' => $config['company_address'] ?? '',
            'support_email' => $config['company_email'] ?? '',
            'support_phone' => $config['company_phone'] ?? '',
            'tin' => $config['company_tin'] ?? '',
            'website' => $config['company_website'] ?? '',
            'logo_path' => $config['logo'] ?? '',
            'primary_color' => $config['primary_color'] ?? '#3b82f6',
        ];
    }
}
