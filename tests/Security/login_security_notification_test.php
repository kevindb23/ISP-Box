<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));
spl_autoload_register(static function (string $class): void {
    foreach (['App\\' => BASE_PATH . '/app/', 'Framework\\' => BASE_PATH . '/framework/'] as $prefix => $base) {
        if (!str_starts_with($class, $prefix)) continue;
        $file = $base . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) require_once $file;
    }
});

use App\Modules\Auth\Services\LoginSecurityDetector;

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
    }
}

assertSameValue(true, LoginSecurityDetector::isSuspicious("' OR '1'='1", 'invalid'), 'Boolean SQLi should be detected.');
assertSameValue(true, LoginSecurityDetector::isSuspicious("admin'--", 'invalid'), 'SQL comment bypass should be detected.');
assertSameValue(true, LoginSecurityDetector::isSuspicious('admin', "x' OR 1=1 #"), 'Password bypass payload should be detected.');
assertSameValue(true, LoginSecurityDetector::isSuspicious('<script>alert(1)</script>', 'invalid'), 'Script-shaped input should be treated as suspicious.');
assertSameValue(false, LoginSecurityDetector::isSuspicious('admin', 'wrong-password'), 'Ordinary invalid credentials should not create a critical alert.');

$header = file_get_contents(__DIR__ . '/../../app/UI/Views/layouts/header.php');
$layout = file_get_contents(__DIR__ . '/../../app/UI/Views/layouts/app.php');
$routes = file_get_contents(__DIR__ . '/../../app/Modules/Audit/Routes/web.php');
$script = file_get_contents(__DIR__ . '/../../public/assets/js/nx.js');
$authController = file_get_contents(__DIR__ . '/../../app/Modules/Auth/Controllers/AuthController.php');
$auditValidator = file_get_contents(__DIR__ . '/../../app/Modules/Audit/Validators/AuditEventValidator.php');

assertSameValue(true, str_contains($header, 'id="globalNotifications"'), 'Header should provide the notification popover root.');
assertSameValue(true, str_contains($layout, '/assets/js/nx.js?v=9'), 'The notification handler asset version should invalidate stale browser caches.');
assertSameValue(true, str_contains($routes, "/api/v1/notifications"), 'Notification API route should be registered.');
assertSameValue(true, str_contains($script, 'globalNotifications'), 'Global JavaScript should wire the notification popover.');
assertSameValue(true, str_contains($script, 'nxNotificationsBound'), 'Notification wiring should be idempotent across SPA page loads.');
assertSameValue(true, str_contains($authController, 'LoginSecurityDetector'), 'Login controller should record suspicious input before credential validation.');
assertSameValue(true, str_contains($auditValidator, "'CRITICAL'"), 'Audit validation should allow critical security notifications.');

echo "login security notification tests passed\n";
