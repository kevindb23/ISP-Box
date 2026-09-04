<?php

declare(strict_types=1);

$base = dirname(__DIR__, 2);
$migrationPath = $base . '/database/migrations/20260904_000002_mfa_pending_enrollment.sql';
$migration = is_file($migrationPath) ? file_get_contents($migrationPath) : '';
$repository = file_get_contents($base . '/app/Modules/Mfa/Repositories/MfaRepository.php');
$service = file_get_contents($base . '/app/Modules/Mfa/Services/MfaService.php');
$failures = [];

foreach (['pending_method', 'pending_secret_encrypted', 'pending_started_at'] as $column) {
    if (!str_contains((string)$migration, $column)) {
        $failures[] = "MFA migration must add {$column}";
    }
}

if (!str_contains((string)$repository, 'pending_method')
    || !str_contains((string)$repository, 'pending_secret_encrypted')) {
    $failures[] = 'MFA repository must preserve pending enrollment separately from active settings';
}

if (!str_contains((string)$repository, 'method = pending_method')
    || !str_contains((string)$repository, 'secret_encrypted = pending_secret_encrypted')) {
    $failures[] = 'MFA repository must promote pending settings only after verification';
}

if (!str_contains((string)$service, 'pending_method')
    || !str_contains((string)$service, 'pending_secret_encrypted')) {
    $failures[] = 'MFA service must verify the pending enrollment settings';
}

if ($failures !== []) {
    fwrite(STDERR, "MFA method switch contract failed:\n- " . implode("\n- ", $failures) . PHP_EOL);
    exit(1);
}

echo 'mfa_method_switch_contract=PASS' . PHP_EOL;
