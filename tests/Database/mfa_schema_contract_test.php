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

$requiredTables = ['user_mfa', 'mfa_challenges'];
$missing = [];
$table = $pdo->prepare(
    'SELECT COUNT(*) FROM information_schema.TABLES '
    . 'WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table'
);

foreach ($requiredTables as $name) {
    $table->execute(['schema' => $db['name'], 'table' => $name]);
    if ((int)$table->fetchColumn() !== 1) $missing[] = $name;
}

if ($missing !== []) {
    fwrite(STDERR, 'Missing live MFA tables: ' . implode(', ', $missing) . PHP_EOL);
    exit(1);
}

echo 'mfa_schema_contract=PASS tables=' . count($requiredTables) . PHP_EOL;
