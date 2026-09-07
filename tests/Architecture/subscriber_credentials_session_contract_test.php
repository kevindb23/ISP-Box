<?php

declare(strict_types=1);

$base = dirname(__DIR__, 2);
$files = [
    'session' => file_get_contents($base . '/framework/SessionManager.php'),
    'router' => file_get_contents($base . '/framework/Router.php'),
    'subscriber_repo' => file_get_contents($base . '/app/Modules/Subscribers/Repositories/SubscriberRepository.php'),
    'subscriber_service' => file_get_contents($base . '/app/Modules/Subscribers/Services/SubscriberService.php'),
    'portal_service' => file_get_contents($base . '/app/Modules/SubscriberPortal/Services/SubscriberPortalService.php'),
    'portal_controller' => file_get_contents($base . '/app/Modules/SubscriberPortal/Controllers/SubscriberPortalApiController.php'),
];
$checks = [
    'sessions carry credential identity version' => str_contains((string)$files['session'], 'identity_updated_at'),
    'router rejects stale credential sessions' => str_contains((string)$files['router'], 'identityUpdatedAt()'),
    'stale sessions return to authentication' => str_contains((string)$files['router'], "Your session has expired. Please sign in again."),
    'subscriber email updates portal login identity' => str_contains((string)$files['subscriber_repo'], 'UPDATE users')
        && str_contains((string)$files['subscriber_repo'], 'username = ?')
        && str_contains((string)$files['subscriber_repo'], 'email = ?'),
    'subscriber updates always sync Radius service state' => str_contains((string)$files['subscriber_service'], 'syncRadiusPlan(')
        && !str_contains((string)$files['subscriber_service'], 'if ($radiusPlanChanged)'),
    'portal password change reports logout' => str_contains((string)$files['portal_controller'], 'logout_required'),
    'portal password changes advance the user identity version' => str_contains((string)$files['portal_service'], 'updateUserPassword')
        && str_contains((string)file_get_contents($base . '/app/Modules/SubscriberPortal/Repositories/SubscriberPortalRepository.php'), 'updated_at = NOW()'),
    'fresh logins store the post-login identity version' => str_contains((string)file_get_contents($base . '/app/Modules/Auth/Services/AuthService.php'), 'findById($userId)')
        && str_contains((string)file_get_contents($base . '/app/Modules/Auth/Services/AuthService.php'), 'updateLastLogin'),
];
$failures = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
if ($failures !== []) {
    fwrite(STDERR, "Subscriber credential/session contract failed:\n- " . implode("\n- ", $failures) . PHP_EOL);
    exit(1);
}
echo 'subscriber_credentials_session_contract=PASS checks=' . count($checks) . PHP_EOL;
