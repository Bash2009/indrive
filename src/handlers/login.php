<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';

redirect_if_logged_in();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /index.php');
    exit;
}

$email    = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

$user = login_user($conn, $email, $password);

if (isset($user['id'])) {
    $_SESSION['user_id']    = $user['id'];
    $_SESSION['user_role']  = $user['role'];
    $_SESSION['user_name']  = $user['first_name'] . ' ' . $user['last_name'];
    $_SESSION['user_email'] = $user['email'];
    header('Location: /dashboard.php');
    exit;
}

header('Location: /index.php?tab=login&error=' . urlencode($user['error']));
exit;
