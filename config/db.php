<?php

declare(strict_types=1);

require_once __DIR__ . '/env.php';

\App\Config\Environment::load(dirname(__DIR__), ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS']);

$dbHost = getenv('DB_HOST');
$dbName = getenv('DB_NAME');
$dbUser = getenv('DB_USER');
$dbPass = getenv('DB_PASS');

if ($dbHost === false || $dbHost === '') {
    throw new RuntimeException('Missing DB_HOST. Set it in the project .env file or the PHP process environment.');
}

if ($dbName === false || $dbName === '') {
    throw new RuntimeException('Missing DB_NAME. Set it in the project .env file or the PHP process environment.');
}

if ($dbUser === false || $dbUser === '') {
    throw new RuntimeException('Missing DB_USER. Set it in the project .env file or the PHP process environment.');
}

if ($dbPass === false) {
    throw new RuntimeException('Missing DB_PASS. Set it in the project .env file or the PHP process environment.');
}

$dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $dbHost, $dbName);

return new PDO($dsn, $dbUser, $dbPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
