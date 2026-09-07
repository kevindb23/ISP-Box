<?php

declare(strict_types=1);

$base = dirname(__DIR__, 2);
$root = $base . '/app/Modules';
$failures = [];

foreach (glob($root . '/*', GLOB_ONLYDIR) ?: [] as $modulePath) {
    // Vue/Nuxt modules mount into a stable root and render their controls
    // after PHP view output. Their selectors are validated by the frontend
    // tests, so the PHP selector audit should not report those runtime-only
    // IDs as missing from the server-rendered shell.
    $moduleSlug = strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', basename($modulePath)));
    $views = '';
    $javascript = '';
    foreach (glob($modulePath . '/Views/*.php') ?: [] as $file) $views .= file_get_contents($file) . "\n";
    foreach (glob($modulePath . '/Assets/js/*.js') ?: [] as $file) $javascript .= file_get_contents($file) . "\n";
    if ($javascript === '') continue;

    preg_match_all('/(?:getElementById|\$)\(\s*["\']([A-Za-z][A-Za-z0-9_:-]+)["\']\s*\)/', $javascript, $matches);
    foreach (array_unique($matches[1]) as $id) {
        if (!preg_match('/id\s*=\s*["\']' . preg_quote($id, '/') . '["\']/', $views . $javascript)) {
            if (preg_match('/data-nx-next-root\s*=\s*["\']' . preg_quote($moduleSlug, '/') . '["\']/', $views)) {
                continue;
            }
            $failures[] = basename($modulePath) . ": JavaScript references missing DOM ID {$id}";
        }
    }
}

foreach (glob($root . '/*/Views/*.php') ?: [] as $view) {
    $source = file_get_contents($view);
    preg_match_all('/\bid\s*=\s*["\']([A-Za-z][A-Za-z0-9_:-]+)["\']/', $source, $matches);
    foreach (array_count_values($matches[1]) as $id => $count) {
        // These elements occur in mutually exclusive PHP role branches.
        if ($id === 'staffMyLogs' && str_ends_with($view, '/StaffAttendance/Views/index.php')) continue;
        if ($count > 1) $failures[] = str_replace($base . '/', '', $view) . ": duplicate DOM ID {$id}";
    }
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}
echo 'dom_selector_contract=PASS' . PHP_EOL;
