<?php

declare(strict_types=1);

$dbHost = getenv('DB_HOST');
$dbName = getenv('DB_NAME');
$dbUser = getenv('DB_USER');
$dbPass = getenv('DB_PASS');

if ($dbHost === false || $dbHost === '') {
    throw new RuntimeException('Missing required database environment variable: DB_HOST.');
}

if ($dbName === false || $dbName === '') {
    throw new RuntimeException('Missing required database environment variable: DB_NAME.');
}

if ($dbUser === false || $dbUser === '') {
    throw new RuntimeException('Missing required database environment variable: DB_USER.');
}

if ($dbPass === false) {
    throw new RuntimeException('Missing required database environment variable: DB_PASS.');
}

$dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $dbHost, $dbName);

return new PDO($dsn, $dbUser, $dbPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
