<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));
spl_autoload_register(static function (string $class): void {
    foreach (['App\\' => BASE_PATH . '/app/', 'Framework\\' => BASE_PATH . '/framework/'] as $prefix => $dir) {
        if (strncmp($class, $prefix, strlen($prefix)) !== 0) continue;
        $file = $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) require_once $file;
    }
});

$container = require BASE_PATH . '/bootstrap/container.php';
$router = new Framework\Router($container);
require BASE_PATH . '/bootstrap/modules.php';

$trailReflection = new ReflectionClass(App\Core\Audit\RequestAuditTrail::class);
$trail = $trailReflection->newInstanceWithoutConstructor();
$shouldAudit = $trailReflection->getMethod('shouldAudit');
$sanitize = $trailReflection->getMethod('sanitize');
$failures = [];

$routesProperty = new ReflectionProperty($router, 'routes');
$routesProperty->setAccessible(true);
$mutationCount = 0;
foreach ($routesProperty->getValue($router) as $verb => $routes) {
    if (in_array($verb, ['GET', 'HEAD'], true)) continue;
    foreach ($routes as $route) {
        $mutationCount++;
        if (!$shouldAudit->invoke($trail, $verb, $route['uri'])) {
            $failures[] = "Mutation is not centrally audited: {$verb} {$route['uri']}";
        }
    }
}

foreach (['/audit', '/users', '/billing', '/radius', '/olt-management', '/subscribers', '/tickets'] as $uri) {
    if (!$shouldAudit->invoke($trail, 'GET', $uri)) {
        $failures[] = "Sensitive read is not centrally audited: GET {$uri}";
    }
}

$clean = $sanitize->invoke($trail, [
    'password' => 'visible',
    'nested' => ['api_key' => 'visible', 'authorization' => 'visible', 'safe' => 'kept'],
]);
if (($clean['password'] ?? null) !== '[REDACTED]'
    || ($clean['nested']['api_key'] ?? null) !== '[REDACTED]'
    || ($clean['nested']['authorization'] ?? null) !== '[REDACTED]'
    || ($clean['nested']['safe'] ?? null) !== 'kept') {
    $failures[] = 'Nested audit metadata sanitization failed.';
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "audit_contract=PASS mutations={$mutationCount}" . PHP_EOL;
