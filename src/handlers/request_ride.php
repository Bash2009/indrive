<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/fare.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Invalid request']);
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
if (!$user_id) {
    echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
    exit;
}

$pickup_lat     = floatval($_POST['pickup_lat'] ?? 0);
$pickup_lng     = floatval($_POST['pickup_lng'] ?? 0);
$pickup_address = trim($_POST['pickup_address'] ?? '');
$dropoff_lat    = floatval($_POST['dropoff_lat'] ?? 0);
$dropoff_lng    = floatval($_POST['dropoff_lng'] ?? 0);
$dropoff_address= trim($_POST['dropoff_address'] ?? '');
$vehicle_type   = $_POST['vehicle_type'] ?? 'economy';

if (!$pickup_lat || !$pickup_lng || !$dropoff_lat || !$dropoff_lng || !$pickup_address || !$dropoff_address) {
    echo json_encode(['ok' => false, 'error' => 'Missing location data']);
    exit;
}

if (!in_array($vehicle_type, ['economy', 'comfort', 'premium'], true)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid vehicle type']);
    exit;
}

$stmt = $conn->prepare('SELECT id FROM rides WHERE rider_id = ? AND status IN ("requested", "accepted", "arriving", "in_progress")');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->close();
    echo json_encode(['ok' => false, 'error' => 'You already have an active ride']);
    exit;
}
$stmt->close();

$distance = haversine_km($pickup_lat, $pickup_lng, $dropoff_lat, $dropoff_lng);
$fare = calculate_fare($distance, $vehicle_type);

$stmt = $conn->prepare('INSERT INTO rides (rider_id, pickup_lat, pickup_lng, pickup_address, dropoff_lat, dropoff_lng, dropoff_address, vehicle_type, fare, distance_km, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "requested")');
$stmt->bind_param('iddsddssdd', $user_id, $pickup_lat, $pickup_lng, $pickup_address, $dropoff_lat, $dropoff_lng, $dropoff_address, $vehicle_type, $fare, $distance);

if ($stmt->execute()) {
    echo json_encode(['ok' => true, 'ride_id' => $conn->insert_id, 'fare' => $fare]);
} else {
    echo json_encode(['ok' => false, 'error' => 'Failed to create ride']);
}
$stmt->close();
