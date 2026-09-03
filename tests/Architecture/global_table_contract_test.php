<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$layout = file_get_contents($root . '/app/UI/Views/layouts/app.php');
$failures = [];
$check = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$check(str_contains($layout, '.table-responsive,') && str_contains($layout, '.nx-table-wrap'), 'Shared table wrappers are not normalized.');
$check(str_contains($layout, 'overflow-x: auto'), 'Tables do not have responsive horizontal overflow.');
$check(str_contains($layout, ':has(thead th:nth-child(6))'), 'Medium-width table threshold is missing.');
$check(str_contains($layout, ':has(thead th:nth-child(9))'), 'Wide table threshold is missing.');
$check(str_contains($layout, ':has(thead th:nth-child(12))'), 'Extra-wide table threshold is missing.');
$check(str_contains($layout, 'table tbody td') && str_contains($layout, 'overflow-wrap: anywhere'), 'Table cells do not have a readable wrapping contract.');
$check(str_contains($layout, 'white-space: normal !important'), 'Legacy no-wrap table cells are not overridden.');
$check(str_contains($layout, 'table-layout: fixed !important'), 'Ordinary medium-width tables do not have a congestion-resistant layout.');
$check(str_contains($layout, 'html:not([data-theme="dark"])') && str_contains($layout, 'html[data-theme="dark"]'), 'Both table themes are not explicitly covered.');
$check(str_contains($layout, '--card-surface: var(--card') && str_contains($layout, '[class$="-card"]'), 'Custom module cards are not normalized to the active theme surface.');
$check(str_contains($layout, '#nxMainContent#nxMainContent .card'), 'Late module card styles can override the shared theme surface.');
$check(str_contains($layout, '#ticketsDataTable table') && str_contains($layout, 'min-width: 1200px'), 'Tickets table does not preserve readable workflow columns.');
$check(str_contains($layout, '#workOrdersDataTable table') && str_contains($layout, 'min-width: 1320px'), 'Work Orders table does not preserve readable workflow columns.');
$check(str_contains($layout, '.nx-table-footer__controls'), 'Responsive table pagination is not covered.');

foreach (glob($root . '/app/Modules/*/Views/*.php') ?: [] as $view) {
    if (str_contains($view, '_print.php') || str_contains($view, 'receipt.php')) continue;
    $source = file_get_contents($view);
    if (!str_contains($source, '<table')) continue;
    $hasWrapper = str_contains($source, 'table-responsive') || str_contains($source, 'nx-table-wrap');
    $check($hasWrapper, str_replace($root . '/', '', $view) . ' contains a table without a responsive wrapper.');
}

if ($failures) {
    fwrite(STDERR, "Global table contract failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}
echo 'global_table_contract=PASS' . PHP_EOL;
