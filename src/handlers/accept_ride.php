<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Invalid request']);
    exit;
}

$user_id  = $_SESSION['user_id'] ?? 0;
$ride_id  = intval($_POST['ride_id'] ?? 0);
$vehicle_id = intval($_POST['vehicle_id'] ?? 0);

if (!$user_id || $ride_id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Missing data']);
    exit;
}

$stmt = $conn->prepare("SELECT id FROM driver_profiles WHERE user_id = ? AND status = 'approved'");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$driver = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$driver) {
    echo json_encode(['ok' => false, 'error' => 'Driver profile not found']);
    exit;
}

$driver_id = $driver['id'];

$stmt = $conn->prepare("SELECT id, status FROM rides WHERE id = ? AND status = 'requested'");
$stmt->bind_param('i', $ride_id);
$stmt->execute();
$ride = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$ride) {
    echo json_encode(['ok' => false, 'error' => 'Ride not available']);
    exit;
}

$stmt = $conn->prepare("UPDATE rides SET driver_id = ?, vehicle_id = ?, status = 'accepted', accepted_at = NOW() WHERE id = ? AND status = 'requested'");
$stmt->bind_param('iii', $driver_id, $vehicle_id, $ride_id);
$stmt->execute();
$affected = $stmt->affected_rows;
$stmt->close();

if ($affected > 0) {
    echo json_encode(['ok' => true]);
} else {
    echo json_encode(['ok' => false, 'error' => 'Ride was already taken']);
}
