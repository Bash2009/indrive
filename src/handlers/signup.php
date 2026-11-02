<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';

redirect_if_logged_in();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /index.php?tab=signup');
    exit;
}

$result = signup_user($conn, $_POST);

if ($result['ok']) {
    $_SESSION['user_id']   = $result['user_id'];
    $_SESSION['user_role'] = $_POST['role'];
    $_SESSION['user_name'] = trim($_POST['first_name'] ?? '') . ' ' . trim($_POST['last_name'] ?? '');
    $_SESSION['user_email']= trim($_POST['email'] ?? '');
    header('Location: /dashboard.php');
    exit;
}

header('Location: /index.php?tab=signup&error=' . urlencode($result['error']));
exit;
