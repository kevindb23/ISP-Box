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

$property = new ReflectionProperty($router, 'routes');
$property->setAccessible(true);
$routes = $property->getValue($router);
$seen = [];
$failures = [];
$count = 0;

foreach ($routes as $verb => $items) {
    foreach ($items as $route) {
        $count++;
        $key = $verb . ' ' . $route['uri'];
        if (isset($seen[$key])) $failures[] = "Duplicate route: {$key}";
        $seen[$key] = true;

        $action = $route['action'];
        if (!is_array($action)) continue;
        if (!class_exists($action[0])) $failures[] = "Missing controller for {$key}: {$action[0]}";
        elseif (!method_exists($action[0], $action[1])) $failures[] = "Missing method for {$key}: {$action[0]}::{$action[1]}";
    }
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "route_contract=PASS routes={$count}" . PHP_EOL;
