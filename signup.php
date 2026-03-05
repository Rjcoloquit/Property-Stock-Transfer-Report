<?php
session_start();

// If already logged in, redirect to home
if (!empty($_SESSION['user_id'])) {
    header('Location: home.php');
    exit;
}

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    // Match DB enum in `users.role` (Admin, Encoder)
    $role = 'Encoder';

    // Validation
    if ($full_name === '' || $username === '' || $password === '' || $password_confirm === '') {
        $error = 'Please fill in all required fields.';
    } elseif (strlen($username) < 3) {
        $error = 'Username must be at least 3 characters long.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($password !== $password_confirm) {
        $error = 'Passwords do not match.';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        require_once __DIR__ . '/config/database.php';
        $pdo = getConnection();

        try {
            // Check if username already exists
            $stmt = $pdo->prepare('SELECT user_id FROM users WHERE username = ? LIMIT 1');
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $error = 'Username already exists. Please choose a different username.';
            } else {
                // Check if email already exists (if provided)
                if ($email !== '') {
                    $stmt = $pdo->prepare('SELECT user_id FROM users WHERE email = ? LIMIT 1');
                    $stmt->execute([$email]);
                    if ($stmt->fetch()) {
                        $error = 'Email already exists. Please use a different email.';
                    }
                }

                // If no errors, create the user
                if ($error === '') {
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    
                    $stmt = $pdo->prepare(
                        'INSERT INTO users (full_name, username, email, password_hash, role, status)
                         VALUES (?, ?, ?, ?, ?, ?)'
                    );
                    $stmt->execute([
                        $full_name,
                        $username,
                        $email !== '' ? $email : null,
                        $password_hash,
                        $role,
                        'Active'
                    ]);

                    $success = true;
                }
            }
        } catch (PDOException $e) {
            $error = 'Registration failed. Please try again.';
            // Helpful message for common enum mismatch issue.
            if (stripos($e->getMessage(), 'Data truncated for column \'role\'') !== false) {
                $error = 'Registration failed due to an invalid default role configuration.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Supply</title>
    <link rel="stylesheet" href="style.css?v=20260305">
</head>
<body class="auth-page">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card app-card p-4">
                    <h1 class="h4 mb-3 text-center">Sign Up</h1>
                    <?php if ($error): ?>
                        <div class="alert alert-danger py-2 mb-3"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success py-2 mb-3">
                            Account created successfully! You can now log in.
                        </div>
                        <div class="text-center">
                            <a href="login.php" class="btn btn-outline-primary btn-sm">Go to Login</a>
                        </div>
                    <?php else: ?>
                        <form method="post" action="signup.php" novalidate>
                            <div class="mb-3">
                                <label for="full_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input
                                    type="text"
                                    id="full_name"
                                    name="full_name"
                                    class="form-control"
                                    value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
                                    autocomplete="name"
                                    required
                                    autofocus
                                >
                            </div>
                            <div class="mb-3">
                                <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                                <input
                                    type="text"
                                    id="username"
                                    name="username"
                                    class="form-control"
                                    value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                                    autocomplete="username"
                                    required
                                    minlength="3"
                                >
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">
                                    Email <span class="text-muted">(optional)</span>
                                </label>
                                <input
                                    type="email"
                                    id="email"
                                    name="email"
                                    class="form-control"
                                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                    autocomplete="email"
                                >
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    class="form-control"
                                    autocomplete="new-password"
                                    required
                                    minlength="6"
                                >
                            </div>
                            <div class="mb-3">
                                <label for="password_confirm" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                                <input
                                    type="password"
                                    id="password_confirm"
                                    name="password_confirm"
                                    class="form-control"
                                    autocomplete="new-password"
                                    required
                                    minlength="6"
                                >
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Sign Up</button>
                        </form>
                        <div class="text-center mt-3 app-link-muted">
                            Already have an account?
                            <a href="login.php" class="fw-semibold">Log in</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
