<?php

$basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__);
$runtimePath = $basePath . '/.env.runtime.php';
$runtime = is_file($runtimePath) ? (require $runtimePath) : [];
$value = static function (string $environmentKey, array $path, mixed $default = null) use ($runtime): mixed {
    $environmentValue = getenv($environmentKey);
    if ($environmentValue !== false && $environmentValue !== '') return $environmentValue;
    $current = $runtime;
    foreach ($path as $segment) {
        if (!is_array($current) || !array_key_exists($segment, $current)) return $default;
        $current = $current[$segment];
    }
    return $current;
};

$required = static function (mixed $configured, string $name): string {
    $configured = trim((string)$configured);
    if ($configured === '') throw new RuntimeException("Required runtime setting {$name} is not configured.");
    return $configured;
};

return [

    /*
    |--------------------------------------------------------------------------
    | Portal Database (Billing / Admin Portal)
    |--------------------------------------------------------------------------
    */

    'portal_db' => [
        'host' => $required($value('PORTAL_DB_HOST', ['portal_db', 'host']), 'PORTAL_DB_HOST'),
        'user' => $required($value('PORTAL_DB_USER', ['portal_db', 'user']), 'PORTAL_DB_USER'),
        'pass' => $required($value('PORTAL_DB_PASS', ['portal_db', 'pass']), 'PORTAL_DB_PASS'),
        'name' => $required($value('PORTAL_DB_NAME', ['portal_db', 'name']), 'PORTAL_DB_NAME'),
    ],

    /*
    |--------------------------------------------------------------------------
    | ACCEL-PPP CoA Configuration
    |--------------------------------------------------------------------------
    */

    'coa' => [
        'host' => $required($value('COA_HOST', ['coa', 'host']), 'COA_HOST'),
        'port' => (int)$value('COA_PORT', ['coa', 'port'], 3799),
        'secret' => $required($value('COA_SECRET', ['coa', 'secret']), 'COA_SECRET'),
        'radclient_path' => (string)$value('RADCLIENT_PATH', ['coa', 'radclient_path'], '/usr/bin/radclient'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Network Logic
    |--------------------------------------------------------------------------
    */

    'walled_plan' => 'PLAN-WALLED',

    'overprovision_pct' => 5

];
