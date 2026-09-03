<?php

/*
|--------------------------------------------------------------------------
| Load Modules
|--------------------------------------------------------------------------
*/

$modulesPath = BASE_PATH . '/app/Modules';

$modules = scandir($modulesPath);

foreach ($modules as $module) {

    if ($module === '.' || $module === '..') {
        continue;
    }

    $modulePath = $modulesPath . '/' . $module;

    if (!is_dir($modulePath) || preg_match('/(?:\.bak|\.backup|~)$/i', $module)) {
        continue;
    }

    /*
    |--------------------------------------------------------------------------
    | Load Module Services
    |--------------------------------------------------------------------------
    */

    $moduleClass = "App\\Modules\\{$module}\\Module";

    if (class_exists($moduleClass)) {

        $moduleInstance = new $moduleClass();

        if (method_exists($moduleInstance, 'register')) {
            $moduleInstance->register($container);
        }

    }

    /*
    |--------------------------------------------------------------------------
    | Load Web Routes
    |--------------------------------------------------------------------------
    */

    $webRoutes = $modulePath . '/Routes/web.php';

    if (file_exists($webRoutes)) {
        require $webRoutes;
    }

    /*
    |--------------------------------------------------------------------------
    | Load API Routes (Module based)
    |--------------------------------------------------------------------------
    */

    $apiRoutes = $modulePath . '/Routes/api.php';

    if (file_exists($apiRoutes)) {
        require $apiRoutes;
    }

}

/*
|--------------------------------------------------------------------------
| Load Global API Versions
|--------------------------------------------------------------------------
*/

$apiRoot = BASE_PATH . '/app/Modules/Api';

if (is_dir($apiRoot)) {

    $versions = scandir($apiRoot);

    foreach ($versions as $version) {

        if ($version === '.' || $version === '..') {
            continue;
        }

        $routes = $apiRoot . '/' . $version . '/Routes/api.php';

        if (file_exists($routes)) {
            require $routes;
        }

    }

}
