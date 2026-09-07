<?php

declare(strict_types=1);

$base = dirname(__DIR__, 2);
$service = file_get_contents($base . '/app/Modules/Subscribers/Services/SubscriberService.php');
$failures = [];

if (!str_contains((string)$service, 'syncRadiusPlan(')) {
    $failures[] = 'subscriber update must synchronize Radius service state';
}
if (str_contains((string)$service, 'if ($radiusPlanChanged)')) {
    $failures[] = 'subscriber update must not skip Radius synchronization for profile changes';
}

if ($failures !== []) {
    fwrite(STDERR, "Subscriber update Radius sync contract failed:\n- " . implode("\n- ", $failures) . PHP_EOL);
    exit(1);
}

echo 'subscriber_update_radius_sync_contract=PASS' . PHP_EOL;
