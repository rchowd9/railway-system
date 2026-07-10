<?php
define('DB_DSN', 'mysql:host=127.0.0.1;dbname=railway_db;charset=utf8mb4');
define('DB_USER', 'root');
define('DB_PASS', ''); // Adjust matching local environments
define('REDIS_HOST', '127.0.0.1');
define('REDIS_PORT', 6379);

function GetDatabaseConnection() {
    try {
        return new PDO(DB_DSN, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    } catch (PDOException $e) {
        die("Critical Infrastructure DB Offline: " . $e->getMessage());
    }
}