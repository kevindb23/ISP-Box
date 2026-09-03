<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$header = file_get_contents($root . '/app/UI/Views/layouts/header.php');
$script = file_get_contents($root . '/public/assets/js/nx.js');
$failures = [];
$check = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$check(str_contains($header, 'id="globalSearchInput"'), 'Global navigation search input is missing.');
$check(str_contains($header, 'aria-controls="globalSearchSuggestions"'), 'Search input is not associated with its listbox.');
$check(str_contains($header, 'aria-expanded="false"'), 'Search input does not expose its expanded state.');
$check(str_contains($header, 'aria-autocomplete="list"'), 'Search input does not expose list autocomplete semantics.');
$check(str_contains($script, "#primarySidebar .sidebar-menu a[href]"), 'Search is not scoped to role-visible sidebar navigation.');
$check(str_contains($script, ".slice(0, 8)"), 'Search results are not capped to a usable menu size.');
$check(str_contains($script, "event.key === 'ArrowDown'"), 'Down-arrow navigation is missing.');
$check(str_contains($script, "event.key === 'ArrowUp'"), 'Up-arrow navigation is missing.');
$check(str_contains($script, "event.key === 'Enter'"), 'Enter activation is missing.');
$check(str_contains($script, "event.key === 'Escape'"), 'Escape dismissal is missing.');
$check(str_contains($script, "event.ctrlKey || event.metaKey"), 'Ctrl/Cmd+K shortcut is missing.');
$check(str_contains($script, 'document.createElement'), 'Search results must be constructed with safe DOM APIs.');
$check(!str_contains($script, 'matches.map((item) => `<button'), 'Search results still interpolate navigation values into HTML.');

if ($failures !== []) {
    fwrite(STDERR, "Global navigation search contract failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo 'global_navigation_search_contract=PASS' . PHP_EOL;
