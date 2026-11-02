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

if (!$user_id || $ride_id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Missing data']);
    exit;
}

$stmt = $conn->prepare("SELECT id, status, rider_id FROM rides WHERE id = ? AND status IN ('requested', 'accepted', 'arriving')");
$stmt->bind_param('i', $ride_id);
$stmt->execute();
$ride = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$ride) {
    echo json_encode(['ok' => false, 'error' => 'Ride not found or cannot be cancelled']);
    exit;
}

$is_rider = ($ride['rider_id'] == $user_id);

if (!$is_rider) {
    $stmt = $conn->prepare('SELECT id FROM driver_profiles WHERE user_id = ? AND id = (SELECT driver_id FROM rides WHERE id = ?)');
    $stmt->bind_param('ii', $user_id, $ride_id);
    $stmt->execute();
    $is_driver = $stmt->get_result()->num_rows > 0;
    $stmt->close();
} else {
    $is_driver = false;
}

if (!$is_rider && !$is_driver) {
    echo json_encode(['ok' => false, 'error' => 'Not authorized']);
    exit;
}

$cancelled_by = $is_rider ? 'rider' : 'driver';

$stmt = $conn->prepare("UPDATE rides SET status = 'cancelled', cancelled_at = NOW(), cancelled_by = ? WHERE id = ?");
$stmt->bind_param('si', $cancelled_by, $ride_id);
$stmt->execute();
$stmt->close();

echo json_encode(['ok' => true]);
