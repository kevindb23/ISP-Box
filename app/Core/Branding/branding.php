<?php

namespace App\Core;

use Framework\DatabaseConnection;
use Throwable;

class Branding
{
    public static function get(): array
    {
        $defaults = self::defaults();

        try {
            $connection = new DatabaseConnection();
            $db = $connection->get();

            $stmt = $db->query("
                SELECT *
                FROM branding
                ORDER BY id ASC
                LIMIT 1
            ");

            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

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

            return $branding;
        } catch (Throwable $e) {
            return $defaults;
        }
    }

    private static function defaults(): array
    {
        $config = require BASE_PATH . '/config/branding.php';

        return [
            'client_name' => $config['client_name'] ?? 'ISP-In-A-BOX',
            'company_name' => $config['company_name'] ?? ($config['client_name'] ?? 'ISP-In-A-BOX'),
            'business_name' => $config['business_name'] ?? ($config['client_name'] ?? 'ISP-In-A-BOX'),
            'portal_title' => $config['portal_title'] ?? 'Administrator Portal',
            'logo_text' => $config['logo_text'] ?? '1WAN',
            'company_address' => $config['company_address'] ?? '',
            'support_email' => $config['company_email'] ?? '',
            'support_phone' => $config['company_phone'] ?? '',
            'tin' => $config['company_tin'] ?? '',
            'website' => $config['company_website'] ?? '',
            'logo_path' => $config['logo'] ?? '/assets/img/light-background.png',
            'primary_color' => $config['primary_color'] ?? '#3b82f6',
            'powered_by' => $config['powered_by'] ?? (defined('POWERED_BY') ? POWERED_BY : '1WAN'),
        ];
    }
}
