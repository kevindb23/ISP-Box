<?php

declare(strict_types=1);

$base = dirname(__DIR__, 2);
$authRepository = (string)file_get_contents($base . '/app/Modules/Auth/Repositories/AdminRepository.php');
$subscriberRepository = (string)file_get_contents($base . '/app/Modules/Subscribers/Repositories/SubscriberRepository.php');
$failures = [];

if (!str_contains($authRepository, 's.email = :subscriber_email')) {
    $failures[] = 'subscriber portal login does not resolve the linked subscriber email';
}
if (!str_contains($authRepository, 'INNER JOIN subscribers s')) {
    $failures[] = 'subscriber portal login email fallback is missing the subscriber identity join';
}
if (!str_contains($subscriberRepository, 'username = COALESCE(NULLIF(?, \'\'), username)')) {
    $failures[] = 'portal password reset does not repair the linked portal username';
}
if (!str_contains($subscriberRepository, "email = COALESCE(NULLIF(?, ''), email)")) {
    $failures[] = 'portal password reset does not repair the linked portal email';
}

if ($failures !== []) {
    fwrite(STDERR, "Subscriber portal login identity contract failed:\n- " . implode("\n- ", $failures) . PHP_EOL);
    exit(1);
}

echo 'subscriber_portal_login_identity_contract=PASS checks=4' . PHP_EOL;
