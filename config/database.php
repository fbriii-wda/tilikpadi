<?php

declare(strict_types=1);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function database(): mysqli
{
    static $connection = null;

    if ($connection instanceof mysqli) {
        return $connection;
    }

    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $username = getenv('DB_USERNAME') ?: 'root';
    $password = getenv('DB_PASSWORD') ?: '';
    $database = getenv('DB_NAME') ?: 'tilikpadi';
    $port = (int) (getenv('DB_PORT') ?: 3306);

    $connection = new mysqli($host, $username, $password, $database, $port);
    $connection->set_charset('utf8mb4');

    return $connection;
}
