<?php

namespace App\Modules\Branding\Repositories;

use Framework\DatabaseConnection;
use PDO;

class BrandingRepository
{
    private PDO $db;

    public function __construct(DatabaseConnection $connection)
    {
        $this->db = $connection->get();
    }

    public function getBranding(): ?array
    {
        $stmt = $this->db->query("
            SELECT *
            FROM branding
            ORDER BY id ASC
            LIMIT 1
        ");

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function saveBranding(array $data): bool
    {
        $existing = $this->getBranding();

        if ($existing) {
            return $this->updateBranding((int)$existing['id'], $data);
        }

        return $this->createBranding($data);
    }

    private function createBranding(array $data): bool
    {
        $stmt = $this->db->prepare("
            INSERT INTO branding (
                company_name,
                business_name,
                portal_title,
                company_address,
                support_email,
                support_phone,
                tin,
                website,
                logo_path,
                primary_color
            ) VALUES (
                :company_name,
                :business_name,
                :portal_title,
                :company_address,
                :support_email,
                :support_phone,
                :tin,
                :website,
                :logo_path,
                :primary_color
            )
        ");

        return $stmt->execute($this->payload($data));
    }

    private function updateBranding(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE branding
            SET
                company_name = :company_name,
                business_name = :business_name,
                portal_title = :portal_title,
                company_address = :company_address,
                support_email = :support_email,
                support_phone = :support_phone,
                tin = :tin,
                website = :website,
                logo_path = :logo_path,
                primary_color = :primary_color,
                updated_at = NOW()
            WHERE id = :id
            LIMIT 1
        ");

        $payload = $this->payload($data);
        $payload[':id'] = $id;

        return $stmt->execute($payload);
    }

    private function payload(array $data): array
    {
        return [
            ':company_name' => $data['company_name'] ?? '',
            ':business_name' => $data['business_name'] ?? '',
            ':portal_title' => $data['portal_title'] ?? '',
            ':company_address' => $data['company_address'] ?? '',
            ':support_email' => $data['support_email'] ?? '',
            ':support_phone' => $data['support_phone'] ?? '',
            ':tin' => $data['tin'] ?? '',
            ':website' => $data['website'] ?? '',
            ':logo_path' => $data['logo_path'] ?? '',
            ':primary_color' => $data['primary_color'] ?? '#3b82f6',
        ];
    }
}