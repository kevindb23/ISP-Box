<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$failures = [];
$check = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$migration = file_get_contents($root . '/database/migrations/20260904_000004_notification_reads.sql');
$repo = file_get_contents($root . '/app/Modules/Audit/Repositories/AuditRepository.php');
$controller = file_get_contents($root . '/app/Modules/Audit/Controllers/AuditApiController.php');
$routes = file_get_contents($root . '/app/Modules/Audit/Routes/web.php');
$header = file_get_contents($root . '/app/UI/Views/layouts/header.php');
$script = file_get_contents($root . '/public/assets/js/nx.js');

$check($migration !== false && str_contains($migration, 'notification_reads'), 'Notification read-state migration is missing.');
$check(str_contains($repo, 'unreadSecurityNotificationCount'), 'Audit repository does not expose unread security notifications.');
$check(str_contains($repo, 'markNotificationRead'), 'Audit repository does not expose mark-read persistence.');
$check(str_contains($controller, 'markRead'), 'Notification mark-read controller action is missing.');
$check(str_contains($routes, "/api/v1/notifications/{id}/read"), 'Notification mark-read route is missing.');
$check(str_contains($header, 'globalNotificationModal'), 'Notification detail modal is missing from the shared header.');
$check(str_contains($script, 'globalNotificationModal'), 'Notification drawer does not open the detail modal.');
$check(str_contains($script, '/read'), 'Notification drawer does not call the mark-read endpoint.');
$check(str_contains($script, 'unread_count'), 'Notification badge does not use the unread count.');

if ($failures !== []) {
    fwrite(STDERR, "notification_read_contract=FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}
echo "notification_read_contract=PASS\n";
