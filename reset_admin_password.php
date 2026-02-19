<?php
/**
 * One-time script to fix admin login when password was stored as plain text.
 * Run in browser once: http://localhost/PHO/PHO-Supply-PTR/reset_admin_password.php
 * Then delete this file for security.
 */
require_once __DIR__ . '/config/database.php';

$username = 'admin';
$new_password = 'admin';  // Set this to the password you want for admin

$hash = password_hash($new_password, PASSWORD_DEFAULT);

try {
    $pdo = getConnection();
    $stmt = $pdo->prepare('UPDATE users SET password_hash = ?, status = ? WHERE username = ?');
    $stmt->execute([$hash, 'Active', $username]);
    if ($stmt->rowCount() > 0) {
        $message = 'Password updated. You can now log in with username: ' . htmlspecialchars($username) . ' and password: ' . htmlspecialchars($new_password);
        $isError = false;
    } else {
        $message = 'No user found with username "' . htmlspecialchars($username) . '". Make sure the user exists in the database.';
        $isError = true;
    }
} catch (PDOException $e) {
    $message = 'Error: ' . htmlspecialchars($e->getMessage());
    $isError = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Admin Password</title>
    <style>
        body { font-family: system-ui, sans-serif; padding: 2rem; max-width: 500px; margin: 0 auto; }
        .ok { color: #0a0; }
        .err { color: #c00; }
    </style>
</head>
<body>
    <p class="<?= $isError ? 'err' : 'ok' ?>"><?= htmlspecialchars($message) ?></p>
    <p><a href="login.php">Go to login</a></p>
</body>
</html>
