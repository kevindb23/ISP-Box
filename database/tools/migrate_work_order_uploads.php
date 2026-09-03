<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));
$config = require BASE_PATH . '/config/database.php';
$db = $config['portal_db'];
$pdo = new PDO("mysql:host={$db['host']};dbname={$db['name']};charset=utf8mb4", $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$rows = $pdo->query("SELECT id, work_order_id, file_path FROM work_order_attachments WHERE file_path LIKE '/uploads/work-orders/%'")->fetchAll(PDO::FETCH_ASSOC) ?: [];
$copied = [];
$pdo->beginTransaction();
try {
    $update = $pdo->prepare('UPDATE work_order_attachments SET file_path = :path WHERE id = :id');
    foreach ($rows as $row) {
        $relative = ltrim((string)$row['file_path'], '/');
        $source = BASE_PATH . '/public/' . $relative;
        if (!is_file($source)) throw new RuntimeException('A referenced work-order attachment file is missing.');
        $fileName = basename($source);
        $workOrderId = (int)$row['work_order_id'];
        $directory = BASE_PATH . '/storage/uploads/work-orders/' . $workOrderId;
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) throw new RuntimeException('Unable to create private upload directory.');
        $destination = $directory . '/' . $fileName;
        if (!copy($source, $destination) || hash_file('sha256', $source) !== hash_file('sha256', $destination)) {
            throw new RuntimeException('Unable to securely copy a work-order attachment.');
        }
        chmod($destination, 0640);
        $copied[] = [$source, $destination];
        $update->execute(['path' => 'private:work-orders/' . $workOrderId . '/' . $fileName, 'id' => (int)$row['id']]);
    }
    $pdo->commit();
    foreach ($copied as [$source]) {
        if (!unlink($source)) error_log('[Upload migration] Unable to remove legacy public copy: ' . $source);
    }
    echo 'private_work_order_uploads_migrated=' . count($copied) . PHP_EOL;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    foreach ($copied as [, $destination]) if (is_file($destination)) unlink($destination);
    throw $e;
}
