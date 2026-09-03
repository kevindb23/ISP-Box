<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));
require BASE_PATH . '/framework/Router.php';

$router = new Framework\Router(new stdClass());
$method = new ReflectionMethod($router, 'roleCanAccess');
$method->setAccessible(true);
$cases = [
    ['GET', '/api/v1/billing/overview', 'BILLING', true],
    ['GET', '/api/v1/olt-management/devices', 'BILLING', false],
    ['GET', '/api/v1/olt-management/devices', 'NOC', true],
    ['GET', '/api/v1/routers', 'NOC', true],
    ['GET', '/api/v1/billing/overview', 'NOC', false],
    ['GET', '/api/v1/subscribers', 'SUPPORT', true],
    ['POST', '/api/v1/subscribers/update/1', 'SUPPORT', true],
    ['POST', '/api/v1/subscribers/delete', 'SUPPORT', false],
    ['GET', '/api/v1/technician-portal/dashboard', 'TECHNICIAN', true],
    ['GET', '/api/v1/work-orders', 'TECHNICIAN', false],
    ['GET', '/subscriber-portal/invoices', 'SUBSCRIBER', true],
    ['GET', '/billing', 'SUBSCRIBER', false],
    ['POST', '/api/v1/users/delete', 'SUPERADMIN', true],
    ['GET', '/system-settings', 'SUPERADMIN', true],
    ['POST', '/api/v1/system-settings/general', 'SUPERADMIN', true],
    ['GET', '/system-settings', 'NOC', false],
    ['GET', '/system-settings', 'SUPPORT', false],
    ['GET', '/system-settings', 'BILLING', false],
    ['GET', '/system-settings', 'TECHNICIAN', false],
    ['GET', '/system-settings', 'SUBSCRIBER', false],
    ['GET', '/dashboard', 'UNKNOWN', false],
];

$failures = [];
foreach ($cases as [$httpMethod, $path, $role, $expected]) {
    $actual = $method->invoke($router, $httpMethod, $path, $role);
    if ($actual !== $expected) $failures[] = "{$role} {$httpMethod} {$path}: expected " . ($expected ? 'allow' : 'deny');
}
if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}
echo 'role_authorization_contract=PASS cases=' . count($cases) . PHP_EOL;
