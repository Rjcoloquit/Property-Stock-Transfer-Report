<?php
/**
 * Database configuration for XAMPP MySQL
 * Edit these values to match your XAMPP setup.
 * If you get "Access denied (using password: NO)", set DB_PASS to your MySQL root password below.
 */
define('DB_HOST', 'localhost');
define('DB_NAME', 'supply_db');
define('DB_USER', 'root');
define('DB_PASS', '');  // Set your MySQL root password here (XAMPP default is often '' or 'root')

function getConnection(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            die('Database connection failed: ' . $e->getMessage());
        }
    }
    return $pdo;
}
