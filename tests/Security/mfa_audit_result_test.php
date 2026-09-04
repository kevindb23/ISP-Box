<?php

declare(strict_types=1);

$base = dirname(__DIR__, 2);
$service = file_get_contents($base . '/app/Modules/Mfa/Services/MfaService.php');
$failures = [];

if (str_contains((string)$service, "result: 'PENDING'")) {
    $failures[] = 'MFA enrollment must not use the unsupported PENDING audit result';
}

if (!str_contains((string)$service, "action: 'ENROLLMENT_STARTED'")
    || !str_contains((string)$service, "result: 'SUCCESS'")) {
    $failures[] = 'MFA enrollment start must record a valid SUCCESS audit result';
}

if ($failures !== []) {
    fwrite(STDERR, "MFA audit result contract failed:\n- " . implode("\n- ", $failures) . PHP_EOL);
    exit(1);
}

echo 'mfa_audit_result_contract=PASS' . PHP_EOL;
