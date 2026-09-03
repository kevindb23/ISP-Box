<?php

use Framework\Container;
use Framework\DatabaseConnection;

$container = new Container();

// Services that participate in one business transaction must share the same
// PDO handle. This binding also lets the container construct repository/service
// graphs without controllers manually bypassing dependency injection.
$container->bind(\PDO::class, static function (Container $container): \PDO {
    static $connection = null;
    return $connection ??= $container->get(DatabaseConnection::class)->get();
});

return $container;
