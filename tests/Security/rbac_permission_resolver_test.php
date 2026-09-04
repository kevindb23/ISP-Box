<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));
spl_autoload_register(static function (string $class): void {
    foreach (['App\\' => BASE_PATH . '/app/', 'Framework\\' => BASE_PATH . '/framework/'] as $prefix => $base) {
        if (!str_starts_with($class, $prefix)) continue;
        $file = $base . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) require $file;
    }
});

$resolver = new App\Core\Authorization\PermissionResolver();
$cases = [
    ['GET', '/api/v1/billing/overview', 'billing.view'],
    ['POST', '/api/v1/billing/payments/4/review', 'billing.approve'],
    ['POST', '/api/v1/users/delete', 'users.delete'],
    ['POST', '/api/v1/users/4/permissions', 'users.configure'],
    ['POST', '/api/v1/bng/accel/deploy', 'bng.deploy'],
    ['POST', '/api/v1/subscribers/store', 'subscribers.create'],
    ['POST', '/api/v1/subscribers/update/4', 'subscribers.update'],
    ['GET', '/subscriber-portal/invoices', 'subscriber-portal.view'],
    ['POST', '/logout', null],
];
$failures = [];
foreach ($cases as [$method, $uri, $expected]) {
    $actual = $resolver->forRoute($method, $uri);
    if ($actual !== $expected) $failures[] = "{$method} {$uri}: expected " . ($expected ?? 'null') . ", got " . ($actual ?? 'null');
}
$known = $resolver->knownPermissions();
foreach (array_filter(array_column($cases, 2)) as $permission) {
    if (!in_array($permission, $known, true)) $failures[] = "Resolved permission {$permission} is missing from the assignable catalog.";
}
if (count($known) !== count(array_unique($known))) $failures[] = 'Assignable permission catalog contains duplicates.';
if (count($known) !== 80) $failures[] = 'Unexpected assignable permission count; review route coverage.';
foreach (glob(BASE_PATH . '/app/Modules/*/Routes/*.php') ?: [] as $routeFile) {
    $source = file_get_contents($routeFile);
    preg_match_all('/\$router->(get|post)\(\s*[\'\"]([^\'\"]+)/', $source, $routes, PREG_SET_ORDER);
    foreach ($routes as $route) {
        $permission = $resolver->forRoute(strtoupper($route[1]), $route[2]);
        if ($permission !== null && !in_array($permission, $known, true)) {
            $failures[] = basename(dirname($routeFile, 2)) . ": {$permission} is resolved but not assignable.";
        }
    }
}
if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}
echo 'rbac_permission_resolver=PASS cases=' . count($cases) . PHP_EOL;
