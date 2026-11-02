<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/fare.php';
header('Content-Type: application/json');

$lat = floatval($_GET['lat'] ?? 0);
$lng = floatval($_GET['lng'] ?? 0);
$vehicle_type = $_GET['type'] ?? 'economy';

$radius_km = 10;

$sql = 'SELECT u.first_name, u.last_name, dp.current_lat, dp.current_lng, v.make, v.model, v.color, v.license_plate, v.vehicle_type, dp.rating_avg FROM driver_profiles dp JOIN users u ON dp.user_id = u.id JOIN vehicles v ON v.driver_id = dp.id WHERE dp.is_available = 1 AND dp.status = "approved" AND v.vehicle_type = ? AND v.is_active = 1';

$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $vehicle_type);
$stmt->execute();
$result = $stmt->get_result();

$drivers = [];
while ($row = $result->fetch_assoc()) {
    if ($lat && $lng && $row['current_lat'] && $row['current_lng']) {
        $dist = haversine_km($lat, $lng, $row['current_lat'], $row['current_lng']);
        if ($dist > $radius_km) continue;
        $row['distance_km'] = number_format($dist, 1);
    }
    $drivers[] = $row;
}
$stmt->close();

echo json_encode(['drivers' => $drivers, 'count' => count($drivers)]);
