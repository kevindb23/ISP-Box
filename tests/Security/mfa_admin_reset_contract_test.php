<?php

declare(strict_types=1);

$base = dirname(__DIR__, 2);
$routes = file_get_contents($base . '/app/Modules/Mfa/Routes/api.php');
$controller = file_get_contents($base . '/app/Modules/Mfa/Controllers/MfaApiController.php');
$service = file_get_contents($base . '/app/Modules/Mfa/Services/MfaService.php');
$asset = file_get_contents($base . '/app/Modules/Mfa/Assets/js/mfa.js');
$view = file_get_contents($base . '/app/Modules/Mfa/Views/index.php');
$failures = [];

if (!str_contains((string)$routes, "'/api/v1/mfa/{id}/reset'")) {
    $failures[] = 'Admin MFA must expose a reset endpoint';
}
if (!str_contains((string)$controller, 'public function reset($id)') || !str_contains((string)$controller, '$this->service->reset((int)$id)')) {
    $failures[] = 'Admin MFA controller must handle reset through the service';
}
if (!str_contains((string)$service, 'function reset(int $userId)') || !str_contains((string)$service, "'RESET'")) {
    $failures[] = 'MFA service must reset the account and audit the action';
}
if (!str_contains((string)$asset, 'data-action="reset"') || !str_contains((string)$asset, '/reset')) {
    $failures[] = 'Admin MFA table must expose reset as its only account action';
}
if (str_contains((string)$asset, 'data-action="disable"') || str_contains((string)$asset, 'data-action="enroll"')) {
    $failures[] = 'Admin MFA table must not expose disable or enrollment actions';
}
if (str_contains((string)$asset, 'Choose MFA method') || str_contains((string)$view, 'mfaSetupModal')) {
    $failures[] = 'Admin MFA dashboard must not contain setup controls';
}
if (!str_contains((string)$view, 'mfa.js?v=2')) {
    $failures[] = 'Admin MFA asset must be cache-busted after the action change';
}

if ($failures !== []) {
    fwrite(STDERR, "MFA admin reset contract failed:\n- " . implode("\n- ", $failures) . PHP_EOL);
    exit(1);
}

echo 'mfa_admin_reset_contract=PASS' . PHP_EOL;
