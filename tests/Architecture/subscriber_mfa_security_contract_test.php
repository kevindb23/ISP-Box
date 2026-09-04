<?php

declare(strict_types=1);

$base = dirname(__DIR__, 2);
$files = [
    'web_routes' => file_get_contents($base . '/app/Modules/SubscriberPortal/Routes/web.php'),
    'api_routes' => file_get_contents($base . '/app/Modules/SubscriberPortal/Routes/api.php'),
    'controller' => file_get_contents($base . '/app/Modules/SubscriberPortal/Controllers/SubscriberPortalApiController.php'),
    'page_controller' => file_get_contents($base . '/app/Modules/SubscriberPortal/Controllers/SubscriberPortalController.php'),
    'view' => is_file($base . '/app/Modules/SubscriberPortal/Views/security.php')
        ? file_get_contents($base . '/app/Modules/SubscriberPortal/Views/security.php')
        : '',
    'sidebar' => file_get_contents($base . '/app/UI/Views/layouts/sidebar.php'),
    'asset' => is_file($base . '/app/Modules/SubscriberPortal/Assets/js/SubscriberPortalSecurity.js')
        ? file_get_contents($base . '/app/Modules/SubscriberPortal/Assets/js/SubscriberPortalSecurity.js')
        : '',
    'stylesheet' => is_file($base . '/app/Modules/SubscriberPortal/Assets/css/SubscriberPortalSecurity.css')
        ? file_get_contents($base . '/app/Modules/SubscriberPortal/Assets/css/SubscriberPortalSecurity.css')
        : '',
];

$checks = [
    'subscriber security page route' => str_contains((string)$files['web_routes'], "'/subscriber-portal/security'"),
    'subscriber security API route' => str_contains((string)$files['api_routes'], "'/api/v1/subscriber-portal/security'"),
    'subscriber enrollment API route' => str_contains((string)$files['api_routes'], "'/api/v1/subscriber-portal/security/enroll'"),
    'subscriber completion API route' => str_contains((string)$files['api_routes'], "'/api/v1/subscriber-portal/security/complete'"),
    'subscriber disable API route' => str_contains((string)$files['api_routes'], "'/api/v1/subscriber-portal/security/disable'"),
    'subscriber API derives session identity' => str_contains((string)$files['controller'], 'SessionManager::user()')
        && str_contains((string)$files['controller'], "'SUBSCRIBER'"),
    'security page uses MFA choices' => str_contains((string)$files['view'], 'Authenticator app')
        && str_contains((string)$files['view'], 'Email OTP'),
    'security page is linked in subscriber navigation' => str_contains((string)$files['sidebar'], "'/subscriber-portal/security'")
        && str_contains((string)$files['sidebar'], "'Security'"),
    'security page has client behavior' => str_contains((string)$files['asset'], '/api/v1/subscriber-portal/security')
        && str_contains((string)$files['asset'], '/api/v1/subscriber-portal/security/enroll')
        && str_contains((string)$files['asset'], '/api/v1/subscriber-portal/security/complete'),
    'security script waits for shared API initialization' => str_contains((string)$files['view'], 'SubscriberPortalSecurity.js?v=3" defer'),
    'MFA choices expose a visible selected state' => str_contains((string)$files['asset'], 'is-selected')
        && str_contains((string)$files['asset'], "addEventListener('change'")
        && str_contains((string)$files['stylesheet'], '.sp-security-method.is-selected')
        && str_contains((string)$files['stylesheet'], 'input[type="radio"]:checked'),
    'security assets are cache-busted after UI changes' => str_contains((string)$files['view'], 'SubscriberPortalSecurity.css?v=3')
        && str_contains((string)$files['view'], 'SubscriberPortalSecurity.js?v=3'),
    'enabled MFA keeps method choices available' => str_contains((string)$files['asset'], 'methodForm.hidden = false;')
        && str_contains((string)$files['asset'], 'setupPanel.hidden = !setup;')
        && str_contains((string)$files['asset'], 'Change MFA method'),
];

$failures = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
if ($failures !== []) {
    fwrite(STDERR, "Subscriber MFA security contract failed:\n- " . implode("\n- ", $failures) . PHP_EOL);
    exit(1);
}

echo 'subscriber_mfa_security_contract=PASS checks=' . count($checks) . PHP_EOL;
