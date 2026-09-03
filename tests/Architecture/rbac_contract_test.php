<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$migration = file_get_contents($root . '/database/migrations/20260827_000004_rbac_foundation.sql');
$router = file_get_contents($root . '/framework/Router.php');
$sidebar = file_get_contents($root . '/app/UI/Views/layouts/sidebar.php');
$service = file_get_contents($root . '/app/Modules/Users/Services/UsersService.php');
$view = file_get_contents($root . '/app/Modules/Users/Views/index.php');
$javascript = file_get_contents($root . '/app/Modules/Users/Assets/js/Users.js');
$failures = [];
$check = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

foreach (['CREATE TABLE IF NOT EXISTS roles', 'CREATE TABLE IF NOT EXISTS permissions', 'CREATE TABLE IF NOT EXISTS role_permissions', 'CREATE TABLE IF NOT EXISTS user_permission_overrides'] as $table) {
    $check(str_contains($migration, $table), "Missing RBAC schema: {$table}");
}
$check(str_contains($router, 'AuthorizationService::class'), 'Router does not enforce centralized RBAC.');
$check(str_contains($router, 'ACCESS_DENIED'), 'Denied authorization is not audited.');
$check(str_contains($sidebar, 'AuthorizationContext::can'), 'Sidebar does not use effective permissions.');
$check(str_contains($service, 'You cannot disable your own account'), 'Backend self-disable protection is missing.');
$check(str_contains($service, 'final active Superadmin'), 'Final Superadmin protection is missing.');
$check(str_contains($view, 'userPermissionsModal'), 'Users access editor modal is missing.');
$check(str_contains($javascript, "['INHERIT','ALLOW','DENY']"), 'Tri-state permission controls are missing.');

if ($failures) {
    fwrite(STDERR, "RBAC contract failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}
echo 'rbac_contract=PASS' . PHP_EOL;
