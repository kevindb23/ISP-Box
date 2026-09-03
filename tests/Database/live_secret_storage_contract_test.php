<?php

declare(strict_types=1);

$base = dirname(__DIR__, 2);
define('BASE_PATH', $base);
$config = require $base . '/config/database.php';
$db = $config['portal_db'];
$pdo = new PDO(
    sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $db['host'], $db['name']),
    $db['user'],
    $db['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$targets = [
    ['olt_devices', 'password', null],
    ['olt_tr069_profiles', 'acs_password', null],
    ['bng_settings', 'password', null],
    ['router_core_settings', 'password', null],
    ['radius_settings', 'db_password', null],
    ['payment_gateway_settings', 'setting_value', ['paymongo_secret_key', 'paymongo_webhook_secret']],
    ['billing_settings', 'setting_value', ['xendit_secret_key_live', 'xendit_secret_key_test', 'xendit_webhook_token_live', 'xendit_webhook_token_test']],
];

$violations = [];
$checked = 0;
foreach ($targets as [$table, $column, $keys]) {
    $sql = "SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` IS NOT NULL AND `{$column}` <> ''";
    $params = [];
    if ($keys !== null) {
        $sql .= ' AND setting_key IN (' . implode(',', array_fill(0, count($keys), '?')) . ')';
        $params = $keys;
    }
    $count = $pdo->prepare($sql);
    $count->execute($params);
    $checked += (int)$count->fetchColumn();

    $sql .= " AND `{$column}` NOT LIKE 'enc:v1:%'";
    $plain = $pdo->prepare($sql);
    $plain->execute($params);
    if ((int)$plain->fetchColumn() > 0) $violations[] = "{$table}.{$column}";
}

if ($violations !== []) {
    fwrite(STDERR, 'Unencrypted stored secrets remain in: ' . implode(', ', $violations) . PHP_EOL);
    exit(1);
}

echo "live_secret_storage_contract=PASS values={$checked}" . PHP_EOL;
