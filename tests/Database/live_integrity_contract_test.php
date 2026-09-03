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

$expected = [
    'uq_api_tokens_token', 'fk_api_tokens_user', 'fk_api_tokens_created_by',
    'fk_invoices_subscriber', 'fk_invoices_plan',
    'fk_payments_subscriber', 'fk_payments_service',
    'fk_payments_submitted_by', 'fk_payments_reviewed_by',
    'fk_bng_accel_staged_by', 'fk_bng_accel_activated_by',
    'fk_bng_accel_created_by', 'fk_bng_accel_updated_by',
    'fk_bng_accel_deployment_profile', 'fk_bng_accel_deployment_user',
    'fk_network_vlans_parent_svlan',
    'fk_router_core_updated_by', 'fk_router_frr_updated_by', 'fk_router_deployment_user',
    'uq_gateway_reference', 'fk_gateway_transaction_invoice',
    'fk_gateway_transaction_payment', 'fk_gateway_transaction_subscriber',
    'fk_billing_run_item_invoice', 'fk_billing_run_item_plan',
    'fk_billing_run_item_service', 'fk_billing_run_item_subscriber',
    'fk_staff_attendance_user', 'fk_staff_attendance_log_attendance',
    'fk_staff_attendance_log_user', 'fk_tickets_created_by_user',
    'fk_work_order_attachment_order', 'fk_work_order_attachment_user',
    'fk_work_order_note_order', 'fk_work_order_note_user',
    'fk_work_order_status_order', 'fk_work_order_status_user',
    'fk_work_order_task_order', 'fk_work_order_task_user',
    'fk_work_orders_ticket', 'fk_work_orders_assignee', 'fk_work_orders_creator',
];

$stmt = $pdo->prepare(
    'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS '
    . 'WHERE CONSTRAINT_SCHEMA = :schema'
);
$stmt->execute([':schema' => $db['name']]);
$actual = array_fill_keys(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN)), true);
$missing = array_values(array_filter($expected, static fn(string $name): bool => !isset($actual[$name])));

$column = $pdo->prepare(
    'SELECT DATA_TYPE FROM information_schema.COLUMNS '
    . 'WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table AND COLUMN_NAME = :column'
);
$column->execute([':schema' => $db['name'], ':table' => 'olt_devices', ':column' => 'password']);
$passwordType = strtolower((string)$column->fetchColumn());
if (!in_array($passwordType, ['text', 'mediumtext', 'longtext'], true)) {
    $missing[] = 'olt_devices.password encrypted-storage capacity';
}

foreach (['name','description','purpose','scopes','created_by','last_used_at','last_used_ip','revoked_at'] as $apiTokenColumn) {
    $column->execute([':schema' => $db['name'], ':table' => 'api_tokens', ':column' => $apiTokenColumn]);
    if (!$column->fetchColumn()) $missing[] = 'api_tokens.' . $apiTokenColumn;
}
$column->execute([':schema' => $db['name'], ':table' => 'system_config', ':column' => 'config_value']);
$instanceStmt = $pdo->query("SELECT config_value FROM system_config WHERE config_key = 'instance_id' LIMIT 1");
if (trim((string)$instanceStmt->fetchColumn()) === '') $missing[] = 'persistent system_config.instance_id';

if ($missing !== []) {
    fwrite(STDERR, 'Missing live database guarantees: ' . implode(', ', $missing) . PHP_EOL);
    exit(1);
}

echo 'live_integrity_contract=PASS constraints=' . count($expected) . PHP_EOL;
