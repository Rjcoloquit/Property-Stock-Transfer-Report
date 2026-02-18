<?php
session_start();

// Redirect to home if logged in, otherwise to login
if (!empty($_SESSION['user_id'])) {
    header('Location: home.php');
    exit;
}
header('Location: login.php');
exit;
