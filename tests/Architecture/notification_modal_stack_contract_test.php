<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$script = (string) file_get_contents($root . '/public/assets/js/nx.js');
$styles = (string) file_get_contents($root . '/resources/css/app.css');

$failures = [];
$check = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$check(str_contains($script, 'notification-modal-open'), 'Notification modal JavaScript does not manage the open state.');
$check(str_contains($styles, 'notification-modal-open .topbar'), 'Modal-open topbar stacking override is missing.');
$check(str_contains($styles, 'notification-modal-open .nx-notification-modal'), 'Modal-open notification stacking override is missing.');

if ($failures !== []) {
    fwrite(STDERR, "notification modal stack contract failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "notification_modal_stack_contract=PASS\n";
