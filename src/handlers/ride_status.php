<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
header('Content-Type: application/json');

$ride_id = intval($_GET['ride_id'] ?? 0);
$user_id = $_SESSION['user_id'] ?? 0;

if ($ride_id <= 0 || !$user_id) {
    echo json_encode(['status' => 'unknown']);
    exit;
}

$stmt = $conn->prepare('SELECT r.status, r.cancelled_by, r.fare, r.vehicle_type, u.first_name, u.last_name, v.make, v.model, v.year, v.color, v.license_plate, dp.current_lat, dp.current_lng FROM rides r LEFT JOIN driver_profiles dp ON r.driver_id = dp.id LEFT JOIN users u ON dp.user_id = u.id LEFT JOIN vehicles v ON r.vehicle_id = v.id WHERE r.id = ? AND (r.rider_id = ? OR dp.user_id = ?)');
$stmt->bind_param('iii', $ride_id, $user_id, $user_id);
$stmt->execute();
$ride = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$ride) {
    echo json_encode(['status' => 'not_found']);
    exit;
}

$response = [
    'status' => $ride['status'],
    'fare' => $ride['fare'],
];

if ($ride['first_name']) {
    $response['driver_name'] = trim($ride['first_name'] . ' ' . $ride['last_name']);
    $response['driver_car'] = trim(($ride['color'] ?? '') . ' ' . ($ride['year'] ?? '') . ' ' . ($ride['make'] ?? '') . ' ' . ($ride['model'] ?? ''));
    $response['driver_plate'] = $ride['license_plate'];
    $response['vehicle_type'] = ucfirst($ride['vehicle_type'] ?? 'economy');
}

if ($ride['current_lat'] && $ride['current_lng']) {
    $response['driver_lat'] = $ride['current_lat'];
    $response['driver_lng'] = $ride['current_lng'];
}

if ($ride['cancelled_by']) {
    $response['cancelled_by'] = $ride['cancelled_by'];
}

echo json_encode($response);
