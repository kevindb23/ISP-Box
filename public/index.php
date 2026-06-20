<?php

/*
|--------------------------------------------------------------------------
| Debug (TEMP)
|--------------------------------------------------------------------------
*/

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
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

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => false,
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
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin');


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
