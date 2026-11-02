<?php
require_once 'config/session.php';
require_once 'config/db.php';
require_login();

$role = $_SESSION['user_role'] ?? 'rider';

if ($role === 'admin') {
    header('Location: /admin/dashboard.php');
} elseif ($role === 'driver') {
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare('SELECT status FROM driver_profiles WHERE user_id = ?');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $driver = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($driver && $driver['status'] === 'approved') {
        header('Location: /driver/home.php');
    } else {
        header('Location: /driver/pending.php');
    }
} else {
    header('Location: /rider/home.php');
}
exit;
