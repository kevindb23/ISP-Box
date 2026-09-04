<?php

$service = file_get_contents(__DIR__ . '/../../app/Modules/Audit/Services/AuditService.php');
$controller = file_get_contents(__DIR__ . '/../../app/Modules/Audit/Controllers/AuditApiController.php');

if (strpos($service, "role === 'SUBSCRIBER'") === false && strpos($service, 'SUBSCRIBER') === false) {
    fwrite(STDERR, "notification service has no subscriber filter\n");
    exit(1);
}

if (strpos($controller, 'notificationFeed') === false || strpos($controller, 'role') === false) {
    fwrite(STDERR, "notification controller does not pass role context\n");
    exit(1);
}

echo "subscriber_notification_filter_contract=PASS\n";
