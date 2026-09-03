<?php

/*
|--------------------------------------------------------------------------
| Debug (TEMP)
|--------------------------------------------------------------------------
*/

$debugEnabled = filter_var(getenv('APP_DEBUG') ?: '0', FILTER_VALIDATE_BOOLEAN);
ini_set('display_errors', $debugEnabled ? '1' : '0');
ini_set('display_startup_errors', $debugEnabled ? '1' : '0');
error_reporting(E_ALL);


/*
|--------------------------------------------------------------------------
| Base Path
|--------------------------------------------------------------------------
*/

define('BASE_PATH', dirname(__DIR__));


/*
|--------------------------------------------------------------------------
| Secure Session
|--------------------------------------------------------------------------
*/

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();


/*
|--------------------------------------------------------------------------
| Security Headers
|--------------------------------------------------------------------------
*/

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=(self)');
header("Content-Security-Policy: default-src 'self'; base-uri 'self'; frame-ancestors 'self'; form-action 'self'; object-src 'none'; img-src 'self' data: blob: https://*.tile.openstreetmap.org https://server.arcgisonline.com; font-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; connect-src 'self' https://api.paymongo.com https://api.xendit.co https://nominatim.openstreetmap.org");
if ($isHttps) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}


/*
|--------------------------------------------------------------------------
| PSR-4 Autoloader
|--------------------------------------------------------------------------
*/

spl_autoload_register(function ($class) {

    $prefixes = [

        'App\\'       => BASE_PATH . '/app/',
        'Framework\\' => BASE_PATH . '/framework/',

    ];

    foreach ($prefixes as $prefix => $baseDir) {

        $len = strlen($prefix);

        if (strncmp($prefix, $class, $len) !== 0) {
            continue;
        }

        $relativeClass = substr($class, $len);

        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

        if (file_exists($file)) {
            require $file;
        }

    }

});

set_exception_handler([\Framework\ExceptionHandler::class, 'handle']);


/*
|--------------------------------------------------------------------------
| Load Config
|--------------------------------------------------------------------------
*/

require BASE_PATH . '/config/app.php';
require BASE_PATH . '/config/database.php';


/*
|--------------------------------------------------------------------------
| Dependency Injection Container
|--------------------------------------------------------------------------
*/

$container = require BASE_PATH . '/bootstrap/container.php';


/*
|--------------------------------------------------------------------------
| Router
|--------------------------------------------------------------------------
*/

use Framework\Router;

$router = new Router($container);


/*
|--------------------------------------------------------------------------
| Load Module Routes
|--------------------------------------------------------------------------
*/

require BASE_PATH . '/bootstrap/modules.php';


/*
|--------------------------------------------------------------------------
| Module Assets (CSS/JS Loader)
|--------------------------------------------------------------------------
*/

$router->get('/module-assets/{module}/{type}/{file}', function($module, $type, $file){

    // 🔒 SECURITY
    if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $module)) {
        http_response_code(400);
        exit;
    }

    if (!in_array($type, ['css', 'js'])) {
        http_response_code(400);
        exit;
    }

    if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $file)) {
        http_response_code(400);
        exit;
    }

    // 📁 BUILD PATH
    $filePath = BASE_PATH . "/app/Modules/{$module}/Assets/{$type}/{$file}";

    // 🔍 CHECK FILE
    if (!file_exists($filePath)) {
        http_response_code(404);
        exit;
    }

    // 📦 HEADERS
    if ($type === 'css') {
        header('Content-Type: text/css');
    }

    if ($type === 'js') {
        header('Content-Type: application/javascript');
    }

    // 🚀 OUTPUT FILE
    readfile($filePath);
    exit;
});


/*
|--------------------------------------------------------------------------
| Dispatch Request
|--------------------------------------------------------------------------
*/

$router->dispatch();
