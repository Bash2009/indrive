<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Invalid request']);
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
$ride_id = intval($_POST['ride_id'] ?? 0);
$action  = $_POST['action'] ?? '';

if (!$user_id || $ride_id <= 0 || !$action) {
    echo json_encode(['ok' => false, 'error' => 'Missing data']);
    exit;
}

$valid_actions = ['arriving', 'in_progress', 'completed'];
if (!in_array($action, $valid_actions, true)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid action']);
    exit;
}

$stmt = $conn->prepare('SELECT r.id, r.status, dp.user_id FROM rides r JOIN driver_profiles dp ON r.driver_id = dp.id WHERE r.id = ? AND dp.user_id = ?');
$stmt->bind_param('ii', $ride_id, $user_id);
$stmt->execute();
$ride = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$ride) {
    echo json_encode(['ok' => false, 'error' => 'Ride not found']);
    exit;
}

$transitions = [
    'arriving'     => ['accepted'],
    'in_progress'  => ['arriving'],
    'completed'    => ['in_progress'],
];

$allowed_from = $transitions[$action] ?? [];
if (!in_array($ride['status'], $allowed_from, true)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid status transition']);
    exit;
}

$new_status = $action;
$timestamp_map = [
    'arriving' => 'arrived_at',
    'in_progress' => 'started_at',
    'completed' => 'completed_at',
];
$timestamp_col = $timestamp_map[$action] ?? null;

if ($timestamp_col) {
    $stmt = $conn->prepare("UPDATE rides SET status = ?, {$timestamp_col} = NOW() WHERE id = ?");
} else {
    $stmt = $conn->prepare("UPDATE rides SET status = ? WHERE id = ?");
}
$stmt->bind_param('si', $new_status, $ride_id);
$stmt->execute();
$stmt->close();

if ($action === 'completed') {
    $stmt = $conn->prepare('UPDATE driver_profiles SET is_available = 0, total_rides = total_rides + 1 WHERE id = (SELECT driver_id FROM rides WHERE id = ?)');
    $stmt->bind_param('i', $ride_id);
    $stmt->execute();
    $stmt->close();
}

echo json_encode(['ok' => true]);
