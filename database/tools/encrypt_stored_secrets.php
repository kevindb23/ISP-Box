<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));
require BASE_PATH . '/app/Infrastructure/Security/SecretCipher.php';

use App\Infrastructure\Security\SecretCipher;

$config = require BASE_PATH . '/config/database.php';
$db = $config['portal_db'];
$pdo = new PDO(
    "mysql:host={$db['host']};dbname={$db['name']};charset=utf8mb4",
    $db['user'],
    $db['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
$cipher = new SecretCipher();
$targets = [
    ['olt_devices', 'id', 'password', null],
    ['olt_tr069_profiles', 'id', 'acs_password', null],
    ['bng_settings', 'id', 'password', null],
    ['radius_settings', 'id', 'db_password', null],
    ['payment_gateway_settings', 'id', 'setting_value', ['paymongo_secret_key', 'paymongo_webhook_secret']],
    ['billing_settings', 'id', 'setting_value', ['xendit_secret_key_live', 'xendit_secret_key_test', 'xendit_webhook_token_live', 'xendit_webhook_token_test']],
];
$updated = 0;
$pdo->beginTransaction();
try {
    foreach ($targets as [$table, $idColumn, $valueColumn, $settingKeys]) {
        $where = "`{$valueColumn}` IS NOT NULL AND `{$valueColumn}` <> ''";
        $params = [];
        if ($settingKeys !== null) {
            $placeholders = implode(',', array_fill(0, count($settingKeys), '?'));
            $where .= " AND setting_key IN ({$placeholders})";
            $params = $settingKeys;
        }
        $select = $pdo->prepare("SELECT `{$idColumn}` AS row_id, `{$valueColumn}` AS secret_value FROM `{$table}` WHERE {$where} FOR UPDATE");
        $select->execute($params);
        $update = $pdo->prepare("UPDATE `{$table}` SET `{$valueColumn}` = :value WHERE `{$idColumn}` = :id");
        foreach ($select->fetchAll() as $row) {
            $value = (string)$row['secret_value'];
            if ($cipher->isEncrypted($value)) continue;
            $update->execute(['value' => $cipher->encrypt($value), 'id' => $row['row_id']]);
            $updated++;
        }
    }
    $pdo->commit();
    echo "Encrypted {$updated} stored secret value(s).\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $e;
}
