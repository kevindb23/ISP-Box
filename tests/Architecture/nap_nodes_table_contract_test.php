<?php

$root = dirname(__DIR__, 2);
$script = file_get_contents($root . '/app/Modules/NapManagement/Assets/js/NapManagement.js');
$styles = file_get_contents($root . '/app/Modules/NapManagement/Assets/css/NapManagement.css');
$failures = [];

foreach (['nap.nodeTableRow', 'nap-nodes-table', 'Location / Coordinates', 'Port Usage', 'data-action="edit-box"', 'data-action="delete-box"'] as $contract) {
    if (!str_contains($script, $contract)) $failures[] = "Nodes table is missing {$contract}.";
}
if (!str_contains($script, "renderComponent('nap.nodeTableRow'")) $failures[] = 'Nodes pagination must render table rows rather than cards.';
if (!str_contains($styles, 'min-width: 1120px') || !str_contains($styles, 'min-width: 180px')) $failures[] = 'Nodes table and Actions column must preserve enough width for all row controls.';

if ($failures) {
    fwrite(STDERR, "NAP nodes table contract failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}
echo "NAP nodes table contract passed.\n";
