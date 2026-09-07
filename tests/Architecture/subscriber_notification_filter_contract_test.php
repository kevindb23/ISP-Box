<?php

$service = file_get_contents(__DIR__ . '/../../app/Modules/Audit/Services/AuditService.php');
$controller = file_get_contents(__DIR__ . '/../../app/Modules/Audit/Controllers/AuditApiController.php');
$repository = file_get_contents(__DIR__ . '/../../app/Modules/Audit/Repositories/AuditRepository.php');
$script = file_get_contents(__DIR__ . '/../../public/assets/js/nx.js');
$resolverSource = file_get_contents(__DIR__ . '/../../app/Core/Authorization/PermissionResolver.php');
$feedMigration = file_get_contents(__DIR__ . '/../../database/migrations/20260904_000008_notification_feed_permission.sql');
$appLayout = file_get_contents(__DIR__ . '/../../app/UI/Views/layouts/app.php');

if (strpos($service, "role === 'SUBSCRIBER'") === false && strpos($service, 'SUBSCRIBER') === false) {
    fwrite(STDERR, "notification service has no subscriber filter\n");
    exit(1);
}

if (strpos($controller, 'notificationFeed') === false || strpos($controller, 'role') === false) {
    fwrite(STDERR, "notification controller does not pass role context\n");
    exit(1);
}

require_once __DIR__ . '/../../app/Core/Authorization/PermissionResolver.php';
$resolver = new \App\Core\Authorization\PermissionResolver();
$failures = [];
$check = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$check($resolver->forRoute('GET', '/api/v1/notifications') === 'notifications.feed', 'Subscriber notification feed does not resolve to its dedicated feed permission.');
$check($resolver->forRoute('POST', '/api/v1/notifications/42/read') === 'notifications.feed', 'Subscriber notification read endpoint does not resolve to its dedicated feed permission.');
$check(strpos((string)$resolverSource, "'notifications' => ['view','configure','feed']") !== false, 'Notification feed permission is missing from the permission resolver.');
$check(strpos((string)$feedMigration, "'notifications.feed'") !== false && strpos((string)$feedMigration, "r.code IN ('SUBSCRIBER', 'ADMINISTRATOR', 'SUPERADMIN')") !== false, 'Notification feed permission is not seeded for subscribers and administrators.');
$check(strpos((string)$service, 'maintenanceNotificationsForUser') !== false, 'Subscriber notification feed does not load maintenance notifications.');
$check(strpos((string)$repository, 'scheduled_downtime') !== false && strpos((string)$repository, 'system_maintenance') !== false, 'Notification repository does not query maintenance sources.');
$check(strpos((string)$service, "'id' => (string)") !== false, 'Subscriber maintenance notification IDs are exposed as precision-unsafe JSON numbers.');
$check(strpos((string)$script, 'item.title') !== false && strpos((string)$script, 'item.notification_type') !== false, 'Notification drawer still renders every item as a security alert.');
$check(strpos((string)$appLayout, '/assets/js/nx.js?v=13') !== false, 'Maintenance notification drawer asset cache version was not bumped.');

if ($failures !== []) {
    fwrite(STDERR, "subscriber maintenance notification contract failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "subscriber_notification_filter_contract=PASS\n";
