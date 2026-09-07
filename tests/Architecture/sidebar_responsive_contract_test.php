<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$script = (string) file_get_contents($root . '/public/assets/js/nx.js');
$styles = (string) file_get_contents($root . '/resources/css/app.css');

$failures = [];
$check = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$check(str_contains($script, "sidebar-mobile-open"), 'Sidebar JavaScript does not manage the mobile-open state.');
$check(str_contains($styles, 'sidebar-mobile-open .sidebar'), 'Mobile-open sidebar styling is missing.');
$check(str_contains($styles, 'sidebar-mobile-open .main-wrapper'), 'Mobile-open content offset styling is missing.');
$check(str_contains($styles, 'sidebar-mobile-open .sidebar-scrim'), 'Mobile-open scrim styling is missing.');

if ($failures !== []) {
    fwrite(STDERR, "sidebar responsive contract failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "sidebar_responsive_contract=PASS\n";
