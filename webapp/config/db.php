<?php

date_default_timezone_set('UTC');

function csa_config(): array
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/config.php';
    }
    return $config;
}

function csa_db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $db = csa_config()['db'];
        $dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$db['name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $db['user'], $db['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 10,
        ]);
        // Force UTC so NOW()/CURRENT_TIMESTAMP align with PHP's UTC-based time()/strtotime().
        $pdo->exec("SET time_zone = '+00:00'");
    }
    return $pdo;
}
