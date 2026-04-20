<?php
session_start();
require_once __DIR__ . '/config/rbac.php';

// Redirect to home if logged in, otherwise to login
if (!empty($_SESSION['user_id'])) {
    if (ptr_current_role() === 'Admin') {
        header('Location: home.php');
    } else {
        header('Location: create_ptr.php');
    }
    exit;
}
header('Location: login.php');
exit;
