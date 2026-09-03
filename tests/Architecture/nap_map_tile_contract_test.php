<?php

$root = dirname(__DIR__, 2);
$script = file_get_contents($root . '/app/Modules/NapManagement/Assets/js/NapManagement.js');
$styles = file_get_contents($root . '/app/Modules/NapManagement/Assets/css/NapManagement.css');
$csp = file_get_contents($root . '/public/index.php');
$failures = [];

if (str_contains($script, 'tile.openstreetmap.org')) {
    $failures[] = 'NAP maps must not use the blocked public OpenStreetMap tile endpoint.';
}
if (substr_count($script, 'World_Street_Map/MapServer/tile/{z}/{y}/{x}') < 2) {
    $failures[] = 'Both modal pickers and planner street maps must use the reachable ArcGIS tile service.';
}
if (!str_contains($csp, 'https://server.arcgisonline.com')) {
    $failures[] = 'The application CSP must allow the ArcGIS tile host.';
}
if (!str_contains($styles, '.nx-modal .nx-map-picker') || !str_contains($styles, 'min-height: 320px')) {
    $failures[] = 'NAP modal map pickers must own a non-collapsing module-scoped height.';
}

if ($failures) {
    fwrite(STDERR, "NAP map tile contract failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "NAP map tile contract passed.\n";
