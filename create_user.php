<?php
/**
 * One-time script to create a user for the supply database.
 * Run from command line: php create_user.php
 * Or open in browser once: http://localhost/PTR/create_user.php
 * Then delete or protect this file in production.
 */
require_once __DIR__ . '/config/database.php';

$full_name = 'Administrator';
$username = 'admin';
$password = 'password';  // Change this
$email = 'admin@example.com';
$role = 'Admin';  // or 'InventoryManager'
$status = 'Active';

$hash = password_hash($password, PASSWORD_DEFAULT);

try {
    $pdo = getConnection();
    $stmt = $pdo->prepare('
        INSERT INTO users (full_name, username, email, password_hash, role, status)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            password_hash = VALUES(password_hash),
            full_name = VALUES(full_name),
            role = VALUES(role),
            status = VALUES(status)
    ');
    $stmt->execute([$full_name, $username, $email, $hash, $role, $status]);
    $message = 'User created/updated. You can log in with username: ' . htmlspecialchars($username) . ' and your chosen password.';
} catch (PDOException $e) {
    $message = 'Error: ' . htmlspecialchars($e->getMessage());
}

if (php_sapi_name() === 'cli') {
    echo $message . "\n";
    exit(strpos($message, 'Error') !== false ? 1 : 0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create user</title>
    <style>
        body { font-family: system-ui, sans-serif; padding: 2rem; max-width: 500px; margin: 0 auto; }
        .ok { color: #0a0; }
        .err { color: #c00; }
    </style>
</head>
<body>
    <p class="<?= strpos($message, 'Error') !== false ? 'err' : 'ok' ?>"><?= $message ?></p>
    <p><a href="login.php">Go to login</a></p>
</body>
</html>
