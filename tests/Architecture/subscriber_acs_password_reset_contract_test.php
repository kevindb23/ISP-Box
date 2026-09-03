<?php

declare(strict_types=1);

$base = dirname(__DIR__, 2);
$service = file_get_contents($base . '/app/Modules/Subscribers/Services/SubscriberService.php');
$repo = file_get_contents($base . '/app/Modules/Subscribers/Repositories/SubscriberRepository.php');
$failures = [];

foreach (['findPppCredentialContext', 'updateServicePppPassword'] as $method) {
    if (!str_contains($repo, "function {$method}")) $failures[] = "missing secret command query {$method}";
}
foreach (['findDeviceBySerial', 'setPppCredentials', 'syncRadiusPassword', 'updateServicePppPassword'] as $call) {
    if (!str_contains($service, $call)) $failures[] = "PPP reset omits {$call}";
}
if (!str_contains(file_get_contents($base . '/app/Modules/OntDevices/Services/AcsService.php'), "'delivery' => \$online ? 'IMMEDIATE' : 'QUEUED'")) {
    $failures[] = 'ACS PPP write does not distinguish immediate execution from offline queueing';
}
if (!str_contains($service, 'RADIUS rollback also failed')) {
    $failures[] = 'PPP reset lacks explicit compensation failure handling';
}
if (strpos($service, 'setPppCredentials') > strpos($service, '$this->disconnect($pppUsername)')) {
    $failures[] = 'PPP session disconnect happens before ACS credentials are pushed';
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}
echo 'subscriber_acs_password_reset_contract=PASS' . PHP_EOL;
