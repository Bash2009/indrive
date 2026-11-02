<?php
session_start();

function require_login() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: /index.php');
        exit;
    }
}

function redirect_if_logged_in() {
    if (isset($_SESSION['user_id'])) {
        header('Location: /dashboard.php');
        exit;
    }
}

function require_admin() {
    require_login();
    if (($_SESSION['user_role'] ?? '') !== 'admin') {
        header('Location: /dashboard.php');
        exit;
    }
}
