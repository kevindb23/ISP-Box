<?php

declare(strict_types=1);

$base = dirname(__DIR__, 2);
$navigation = (string) file_get_contents($base . '/app/Core/UI/navigation.php');
$permissions = (string) file_get_contents($base . '/app/Core/Authorization/PermissionResolver.php');

$failures = [];
$check = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$check(str_contains($navigation, "'scheduled-downtime' => 'Scheduled Downtime'"), 'Scheduled Downtime breadcrumb label is missing.');
$check(str_contains($navigation, "'system-maintenance' => 'System Maintenance'"), 'System Maintenance breadcrumb label is missing.');
$check(str_contains($navigation, "'scheduled-downtime' => 'Maintenance'"), 'Scheduled Downtime is not grouped under Maintenance.');
$check(str_contains($navigation, "'system-maintenance' => 'Maintenance'"), 'System Maintenance is not grouped under Maintenance.');
$check(str_contains($permissions, "'scheduled-downtime' => ['view','create','update','delete']"), 'Scheduled Downtime permission actions are incomplete.');
$check(str_contains($permissions, "'system-maintenance' => ['view','configure']"), 'System Maintenance permission actions are incomplete.');
$check(str_contains($permissions, "'scheduled-downtime' => 'scheduled-downtime'"), 'Scheduled Downtime route prefix is missing.');
$check(str_contains($permissions, "'system-maintenance' => 'system-maintenance'"), 'System Maintenance route prefix is missing.');

require_once $base . '/app/Core/Authorization/PermissionResolver.php';
$resolver = new App\Core\Authorization\PermissionResolver();
$check($resolver->forRoute('GET', '/api/v1/scheduled-downtime') === 'scheduled-downtime.view', 'Scheduled Downtime GET does not resolve to .view.');
$check($resolver->forRoute('POST', '/api/v1/scheduled-downtime/create') === 'scheduled-downtime.create', 'Scheduled Downtime create route does not resolve to .create.');
$check($resolver->forRoute('POST', '/api/v1/scheduled-downtime/update/1') === 'scheduled-downtime.update', 'Scheduled Downtime update route does not resolve to .update.');
$check($resolver->forRoute('POST', '/api/v1/scheduled-downtime/delete') === 'scheduled-downtime.delete', 'Scheduled Downtime delete route does not resolve to .delete.');
$check($resolver->forRoute('PUT', '/api/v1/scheduled-downtime/1') === 'scheduled-downtime.update', 'Scheduled Downtime PUT does not resolve to .update.');
$check($resolver->forRoute('DELETE', '/api/v1/scheduled-downtime/1') === 'scheduled-downtime.delete', 'Scheduled Downtime DELETE does not resolve to .delete.');
$check($resolver->forRoute('GET', '/api/v1/system-maintenance') === 'system-maintenance.view', 'System Maintenance GET does not resolve to .view.');
$check($resolver->forRoute('POST', '/api/v1/system-maintenance/configure') === 'system-maintenance.configure', 'System Maintenance configure route does not resolve to .configure.');

if ($failures !== []) {
    fwrite(STDERR, "Maintenance navigation and permission contract failed:\n- " . implode("\n- ", $failures) . PHP_EOL);
    exit(1);
}

echo 'maintenance_navigation_permissions_contract=PASS checks=' . (4 + 4 + 6) . PHP_EOL;
