<?php

declare(strict_types=1);

$base = dirname(__DIR__, 2);
$failures = [];
foreach (glob($base . '/app/Modules/*/Assets/js/*.js') ?: [] as $file) {
    $source = file_get_contents($file);
    if (!str_contains($source, 'fetch(')) continue;
    $relative = str_replace($base . '/', '', $file);
    $allowedExternal = $relative === 'app/Modules/NapManagement/Assets/js/NapManagement.js'
        && str_contains($source, 'nominatim.openstreetmap.org');
    if (!$allowedExternal) $failures[] = "{$relative} bypasses shared NX.api";
}
if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}
echo 'frontend_api_access_contract=PASS' . PHP_EOL;
