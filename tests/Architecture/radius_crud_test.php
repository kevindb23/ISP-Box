<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));

$routes = file_get_contents(BASE_PATH . '/app/Modules/Radius/Routes/api.php');
if ($routes === false) {
    throw new RuntimeException('Unable to read Radius API routes.');
}

$deletePosition = strpos($routes, "post('/api/v1/radius/settings/delete'");
$updatePosition = strpos($routes, "post('/api/v1/radius/settings/{id}'");

if ($deletePosition === false || $updatePosition === false) {
    throw new RuntimeException('Radius CRUD routes are incomplete.');
}

if ($deletePosition > $updatePosition) {
    throw new RuntimeException('Radius delete route must precede the dynamic update route.');
}

echo "radius_crud_routes=PASS\n";
