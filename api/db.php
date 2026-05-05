<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

$host = 'localhost';
$db   = 'jobsearch';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // For development, create DB if not exists
    try {
        $pdo = new PDO("mysql:host=$host;charset=$charset", $user, $pass, $options);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db`");
        $pdo->exec("USE `$db`");
        
        // Execute schema from database.sql if possible, or just rely on manual import
        // Here we just return connection
    } catch (\PDOException $e2) {
        throw new \PDOException($e2->getMessage(), (int)$e2->getCode());
    }
}

// Auto-migration: Ensure needed columns exist
try {
    $pdo->exec("ALTER TABLE vacancies ADD COLUMN city VARCHAR(255) NULL");
} catch (PDOException $e) {}

try {
    $pdo->exec("ALTER TABLE users ADD COLUMN avatar VARCHAR(255) NULL");
} catch (PDOException $e) {}
?>
