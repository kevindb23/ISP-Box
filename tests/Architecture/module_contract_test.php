<?php

declare(strict_types=1);

$base = dirname(__DIR__, 2);
$modulesRoot = $base . '/app/Modules';
$excluded = ['Api'];
$requiredLayers = ['Controllers', 'DTOs', 'Validators', 'Services', 'Repositories', 'Entities', 'Routes'];
$failures = [];

foreach (glob($modulesRoot . '/*', GLOB_ONLYDIR) ?: [] as $modulePath) {
    $module = basename($modulePath);
    if (in_array($module, $excluded, true)) continue;

    foreach ($requiredLayers as $layer) {
        $files = glob($modulePath . '/' . $layer . '/*.php') ?: [];
        $nonEmpty = array_filter($files, static fn(string $file): bool => filesize($file) > 0);
        if ($nonEmpty === []) $failures[] = "{$module}: {$layer} has no implementation.";
    }

    foreach (['DTOs', 'Validators', 'Entities'] as $layer) {
        $classes = array_map(
            static fn(string $file): string => basename($file, '.php'),
            glob($modulePath . '/' . $layer . '/*.php') ?: []
        );
        $consumers = '';
        foreach (glob($modulePath . '/*/*.php') ?: [] as $candidate) {
            if (str_contains($candidate, '/' . $layer . '/')) continue;
            $consumers .= (string)file_get_contents($candidate);
        }
        if (!array_filter($classes, static fn(string $class): bool => str_contains($consumers, $class))) {
            $failures[] = "{$module}: {$layer} exists but is not used by the module.";
        }
    }
}

foreach (glob($modulesRoot . '/*/Controllers/*.php') ?: [] as $controller) {
    $source = (string)file_get_contents($controller);
    if (preg_match('/new\s+[\\\\A-Za-z0-9_]+(?:Service|Repository|DatabaseConnection|Validator)\s*\(/', $source)) {
        $failures[] = str_replace($base . '/', '', $controller) . ': constructs a dependency directly.';
    }
    if (preg_match('/\$_(?:GET|POST|REQUEST|COOKIE|FILES)\b/', $source)) {
        $failures[] = str_replace($base . '/', '', $controller) . ': bypasses Framework\\Request.';
    }
}

foreach (glob($modulesRoot . '/*/Services/*.php') ?: [] as $service) {
    $source = (string)file_get_contents($service);
    if (preg_match('/(?:->|::)(?:prepare|query|exec)\s*\(/', $source)) {
        $failures[] = str_replace($base . '/', '', $service) . ': issues SQL outside a repository.';
    }
    if (preg_match('/new\s+[\\\\A-Za-z0-9_]+(?:Service|Repository|DatabaseConnection|Validator)\s*\(/', $source)) {
        $failures[] = str_replace($base . '/', '', $service) . ': constructs an injectable dependency directly.';
    }
}

$router = (string)file_get_contents($base . '/framework/Router.php');
$audit = (string)file_get_contents($base . '/app/Core/Audit/RequestAuditTrail.php');
if (!str_contains($router, 'RequestAuditTrail::class') || !str_contains($router, '->begin($method, $uri)')) {
    $failures[] = 'Router does not initialize the central request audit trail.';
}
foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
    if (!str_contains($audit, "'{$method}'")) $failures[] = "Audit trail does not cover {$method}.";
}
foreach (['password', 'secret', 'token', 'authorization', 'cookie', 'private[_-]?key'] as $secret) {
    if (!str_contains($audit, $secret)) $failures[] = "Audit sanitizer does not cover {$secret}.";
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo 'module_contract=PASS' . PHP_EOL;
