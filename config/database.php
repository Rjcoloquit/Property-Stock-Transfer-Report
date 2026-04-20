<?php
/**
 * Database configuration for XAMPP MySQL
 * Edit these values to match your XAMPP setup.
 * If you get "Access denied", set DB_PASS (or DB_PASS env var) to your MySQL password.
 */
define('DB_HOST', 'localhost');
define('DB_NAME', 'supply_db');
define('DB_USER', 'root');
define('DB_PASS', ''); // Set your MySQL password here when not using env var.

function ensureUsersTable(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS users (
            user_id INT NOT NULL AUTO_INCREMENT,
            full_name VARCHAR(150) NOT NULL,
            username VARCHAR(100) NOT NULL,
            email VARCHAR(150) DEFAULT NULL,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM('Admin','Encoder') NOT NULL DEFAULT 'Encoder',
            status ENUM('Active','Inactive') DEFAULT 'Active',
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id),
            UNIQUE KEY username (username),
            UNIQUE KEY email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
}

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

        $configuredPassword = getConfiguredDbPassword();
        $passwordCandidates = [$configuredPassword];
        // Support common local XAMPP defaults across different machines.
        if (DB_USER === 'root') {
            $passwordCandidates[] = '';
            $passwordCandidates[] = 'root';
        }
        $passwordCandidates = array_values(array_unique($passwordCandidates));

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

        // Keep login/signup operational on fresh databases that do not have users yet.
        ensureUsersTable($pdo);
    }
    return $pdo;
}
