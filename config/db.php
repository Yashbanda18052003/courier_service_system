<?php
// ========================================================
// Database Connection Configuration (config/db.php)
// Uses PDO (PHP Data Objects) for security & prepared statements
// ========================================================

$host = '127.0.0.1';
$dbname = 'courier_db';
$username = 'root';
$password = '';

try {
    $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    die("Database Connection Error: " . $e->getMessage());
}

/**
 * Helper function to retrieve the active PDO database connection
 */
function getDB() {
    global $pdo;
    return $pdo;
}
