<?php

declare(strict_types=1);

$base = dirname(__DIR__, 2);
$runtimePath = $base . '/storage/runtime/mfa.php';
$runtime = is_file($runtimePath) ? require $runtimePath : [];
$mfa = is_array($runtime['mfa'] ?? null) ? $runtime['mfa'] : $runtime;
$service = file_get_contents($base . '/app/Modules/Mfa/Services/MfaService.php');
$node = file_get_contents($base . '/app/Modules/Mfa/Services/NodeIntegrationService.php');
$failures = [];

$key = (string)($mfa['encryption_key'] ?? '');
if (base64_decode($key, true) === false || strlen((string)base64_decode($key, true)) !== 32) {
    $failures[] = 'protected runtime MFA encryption key must decode to 32 bytes';
}

if (!str_contains((string)$service, 'runtimeMfaConfiguration')) {
    $failures[] = 'MfaService must resolve MFA settings from protected runtime configuration';
}

if (!str_contains((string)$node, 'runtimeMfaConfiguration')) {
    $failures[] = 'NodeIntegrationService must pass protected runtime MFA settings to Node helpers';
}

if (!str_contains((string)$node, 'Email OTP is not configured')) {
    $failures[] = 'missing SMTP settings must produce a safe Email OTP configuration message';
}

if ($failures !== []) {
    fwrite(STDERR, "MFA runtime configuration contract failed:\n- " . implode("\n- ", $failures) . PHP_EOL);
    exit(1);
}

echo 'mfa_runtime_configuration_contract=PASS' . PHP_EOL;
