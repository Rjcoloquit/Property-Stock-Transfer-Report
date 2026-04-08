<?php
session_start();
require_once __DIR__ . '/config/rbac.php';

// If already logged in, redirect to home
if (!empty($_SESSION['user_id'])) {
    if (ptr_current_role() === 'Admin') {
        header('Location: home.php');
    } else {
        header('Location: create_ptr.php');
    }
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } else {
        if ($username === PTR_ADMIN_USERNAME && $password === PTR_ADMIN_PASSWORD) {
            // Must be non-empty so existing auth checks pass.
            $_SESSION['user_id'] = -1;
            $_SESSION['username'] = PTR_ADMIN_USERNAME;
            $_SESSION['full_name'] = 'Administrator';
            $_SESSION['role'] = 'Admin';
            header('Location: home.php');
            exit;
        }

        require_once __DIR__ . '/config/database.php';
        $pdo = getConnection();

        $stmt = $pdo->prepare('SELECT user_id, username, password_hash, full_name, role, status FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user) {
            $status = strtolower(trim((string) ($user['status'] ?? '')));
            $storedHash = (string) ($user['password_hash'] ?? '');
            $passwordInfo = password_get_info($storedHash);
            $isHashedPassword = isset($passwordInfo['algo']) && $passwordInfo['algo'] !== null && $passwordInfo['algo'] !== 0;
            $passwordValid = false;

            if ($isHashedPassword) {
                $passwordValid = password_verify($password, $storedHash);
            } else {
                // Backward compatibility for old plain-text passwords, then migrate to hash.
                $passwordValid = hash_equals($storedHash, $password);
                if ($passwordValid) {
                    $newHash = password_hash($password, PASSWORD_DEFAULT);
                    $updateStmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?');
                    $updateStmt->execute([$newHash, (int) $user['user_id']]);
                }
            }

            $role = strtolower(trim((string) ($user['role'] ?? '')));

            if ($status === 'active' && $passwordValid && $role !== 'admin') {
                $_SESSION['user_id'] = (int) $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = 'Encoder';
                header('Location: home.php');
                exit;
            }
        }

        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Supply</title>
    <link rel="stylesheet" href="style.css?v=20260305">
</head>
<body class="auth-page">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5 col-lg-4">
                <div class="card app-card p-4">
                    <h1 class="h4 mb-3 text-center">Login</h1>
                    <?php if ($error): ?>
                        <div class="alert alert-danger py-2 mb-3"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    <form method="post" action="login.php" novalidate>
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input
                                type="text"
                                id="username"
                                name="username"
                                class="form-control"
                                value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                                autocomplete="username"
                                required
                                autofocus
                            >
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input
                                type="password"
                                id="password"
                                name="password"
                                class="form-control"
                                autocomplete="current-password"
                                required
                            >
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Log in</button>
                    </form>
                    <div class="text-center mt-3 app-link-muted">
                        Don't have an account?
                        <a href="signup.php" class="fw-semibold">Sign up</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="assets/js/smooth_motion.js?v=20260325"></script>
</body>
</html>
