<?php
/**
 * Database configuration for XAMPP MySQL
 * Edit these values to match your XAMPP setup.
 * If you get "Access denied", set DB_PASS (or DB_PASS env var) to your MySQL password.
 */
define('DB_HOST', 'localhost');
define('DB_NAME', 'supply_db');
define('DB_USER', 'root');
define('DB_PASS', 'root'); // Set your MySQL password here when not using env var.

function getConfiguredDbPassword(): string
{
    $envPassword = getenv('DB_PASS');
    if (is_string($envPassword) && $envPassword !== '') {
        return $envPassword;
    }
    return DB_PASS;
}

function getConnection(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdoOptions = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];

        $passwordCandidates = [getConfiguredDbPassword()];
        // Common XAMPP setup uses root/root; try it automatically when configured password is empty.
        if (DB_USER === 'root' && $passwordCandidates[0] === '') {
            $passwordCandidates[] = 'root';
        }

        $lastException = null;
        foreach ($passwordCandidates as $password) {
            try {
                $pdo = new PDO($dsn, DB_USER, $password, $pdoOptions);
                break;
            } catch (PDOException $e) {
                $lastException = $e;
            }
        }

        if (!$pdo instanceof PDO) {
            die(
                'Database connection failed: ' . ($lastException ? $lastException->getMessage() : 'Unknown error') .
                '. Check DB credentials in config/database.php (DB_USER/DB_PASS) or set DB_PASS environment variable.'
            );
        }
    }
    return $pdo;
}
