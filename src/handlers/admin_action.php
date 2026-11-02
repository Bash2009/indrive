<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_admin();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Invalid request']);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'approve' || $action === 'suspend') {
    $dp_id = intval($_POST['driver_id'] ?? 0);
    if ($dp_id <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Invalid driver ID']);
        exit;
    }

    $new_status = $action === 'approve' ? 'approved' : 'suspended';
    $stmt = $conn->prepare('UPDATE driver_profiles SET status = ? WHERE id = ?');
    $stmt->bind_param('si', $new_status, $dp_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['ok' => true]);
} elseif ($action === 'toggle_user') {
    $user_id = intval($_POST['user_id'] ?? 0);
    if ($user_id <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Invalid user ID']);
        exit;
    }

    $stmt = $conn->prepare("SELECT is_active FROM users WHERE id = ? AND role != 'admin'");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        echo json_encode(['ok' => false, 'error' => 'User not found']);
        exit;
    }

    $new_status = $user['is_active'] ? 0 : 1;
    $stmt = $conn->prepare('UPDATE users SET is_active = ? WHERE id = ?');
    $stmt->bind_param('ii', $new_status, $user_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['ok' => true]);
} else {
    echo json_encode(['ok' => false, 'error' => 'Unknown action']);
}
