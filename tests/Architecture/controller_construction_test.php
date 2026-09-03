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

require BASE_PATH . '/config/app.php';
require BASE_PATH . '/config/database.php';
$container = require BASE_PATH . '/bootstrap/container.php';
$router = new Framework\Router($container);
require BASE_PATH . '/bootstrap/modules.php';

$property = new ReflectionProperty($router, 'routes');
$property->setAccessible(true);
$controllers = [];
foreach ($property->getValue($router) as $items) {
    foreach ($items as $route) {
        if (is_array($route['action'])) $controllers[$route['action'][0]] = true;
    }
}

$failures = [];
foreach (array_keys($controllers) as $controller) {
    try {
        $container->get($controller);
    } catch (Throwable $e) {
        $failures[] = "{$controller}: {$e->getMessage()}";
    }
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo 'controller_construction=PASS controllers=' . count($controllers) . PHP_EOL;
