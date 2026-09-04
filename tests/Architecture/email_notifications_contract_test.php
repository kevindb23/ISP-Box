<?php

declare(strict_types=1);

$base = dirname(__DIR__, 2);
$files = [
    'email_routes' => file_get_contents($base . '/app/Modules/Email/Routes/api.php'),
    'email_service' => file_get_contents($base . '/app/Modules/Email/Services/EmailService.php'),
    'email_repo' => file_get_contents($base . '/app/Modules/Email/Repositories/EmailRepository.php'),
    'email_view' => file_get_contents($base . '/frontend-next/src/modules/email/EmailPage.vue'),
    'notifications_routes' => file_get_contents($base . '/app/Modules/Notifications/Routes/api.php'),
    'notifications_service' => file_get_contents($base . '/app/Modules/Notifications/Services/NotificationsService.php'),
    'notifications_repo' => file_get_contents($base . '/app/Modules/Notifications/Repositories/NotificationsRepository.php'),
    'mfa_node' => file_get_contents($base . '/app/Modules/Mfa/Services/NodeIntegrationService.php'),
    'notifications_view' => file_get_contents($base . '/frontend-next/src/modules/notifications/NotificationsPage.vue'),
    'notification_migration' => file_get_contents($base . '/database/migrations/20260904_000003_email_notifications.sql'),
    'permission' => file_get_contents($base . '/app/Core/Authorization/PermissionResolver.php'),
    'sidebar' => file_get_contents($base . '/app/UI/Views/layouts/sidebar.php'),
    'navigation' => file_get_contents($base . '/app/Core/UI/navigation.php'),
];
$checks = [
    'email settings endpoints' => str_contains((string)$files['email_routes'], '/api/v1/email')
        && str_contains((string)$files['email_routes'], '/api/v1/email/test')
        && str_contains((string)$files['email_routes'], '/api/v1/email/test-email'),
    'email stores secrets encrypted' => str_contains((string)$files['email_repo'], 'SecretCipher')
        && str_contains((string)$files['email_repo'], 'smtp_password_encrypted'),
    'email presets and form' => str_contains((string)$files['email_service'], 'gmail')
        && str_contains((string)$files['email_service'], 'webmail.emailsrvr.com')
        && str_contains((string)$files['email_view'], 'SMTP')
        && str_contains((string)$files['email_view'], 'Test email recipient'),
    'notifications settings endpoints' => str_contains((string)$files['notifications_routes'], '/api/v1/notifications/settings')
        && str_contains((string)$files['notifications_routes'], '/api/v1/notifications/test'),
    'notifications disabled by default' => str_contains((string)$files['notification_migration'], 'enabled TINYINT(1) NOT NULL DEFAULT 0')
        && str_contains((string)$files['notifications_view'], 'Telegram'),
    'notifications stores token encrypted' => str_contains((string)$files['notifications_repo'], 'SecretCipher')
        && str_contains((string)$files['notifications_repo'], 'telegram_bot_token_encrypted'),
    'MFA Email OTP uses Email module' => str_contains((string)$files['mfa_node'], 'EmailService')
        && str_contains((string)$files['mfa_node'], 'sendMessage'),
    'admin navigation' => str_contains((string)$files['sidebar'], "['email', '/email'")
        && str_contains((string)$files['sidebar'], "['notifications', '/notifications'")
        && str_contains((string)$files['navigation'], "'email'")
        && str_contains((string)$files['navigation'], "'notifications'"),
    'admin permissions' => str_contains((string)$files['permission'], "'email'")
        && str_contains((string)$files['permission'], "'notifications'"),
];
$failures = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
if ($failures !== []) {
    fwrite(STDERR, "Email/Notifications contract failed:\n- " . implode("\n- ", $failures) . PHP_EOL);
    exit(1);
}
echo 'email_notifications_contract=PASS checks=' . count($checks) . PHP_EOL;
