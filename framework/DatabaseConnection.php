<?php

namespace Framework;

use PDO;

class DatabaseConnection
{

    private PDO $pdo;

    public function __construct()
    {

        $config = require BASE_PATH . '/config/database.php';

        $db = $config['portal_db'];

        $dsn = "mysql:host={$db['host']};dbname={$db['name']};charset=utf8mb4";

        $this->pdo = new PDO(
            $dsn,
            $db['user'],
            $db['pass'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );

    }

    public function get(): PDO
    {
        return $this->pdo;
    }

}
