<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/fare.php';
header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? 0;
if (!$user_id) {
    echo json_encode(['rides' => []]);
    exit;
}

$lat = floatval($_GET['lat'] ?? 0);
$lng = floatval($_GET['lng'] ?? 0);
$vehicle_type = $_GET['type'] ?? 'economy';

$radius_km = 50;

$stmt = $conn->prepare("SELECT r.id, r.pickup_lat, r.pickup_lng, r.pickup_address, r.vehicle_type, r.distance_km, r.fare, r.requested_at FROM rides r WHERE r.status = 'requested' AND r.vehicle_type = ? AND r.requested_at > DATE_SUB(NOW(), INTERVAL 30 MINUTE)");
$stmt->bind_param('s', $vehicle_type);
$stmt->execute();
$result = $stmt->get_result();

$rides = [];
while ($row = $result->fetch_assoc()) {
    if ($lat && $lng) {
        $dist = haversine_km($lat, $lng, $row['pickup_lat'], $row['pickup_lng']);
        if ($dist > $radius_km) continue;
        $row['distance_km'] = number_format($dist, 1);
    }
    $row['estimated_fare'] = number_format($row['fare'] ?? calculate_fare($row['distance_km'] ?? 5, $row['vehicle_type']), 2);
    $rides[] = $row;
}
$stmt->close();

usort($rides, function($a, $b) {
    return ($a['distance_km'] ?? 99) <=> ($b['distance_km'] ?? 99);
});

echo json_encode(['rides' => $rides]);
