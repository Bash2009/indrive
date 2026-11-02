<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false]);
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
if (!$user_id) {
    echo json_encode(['ok' => false]);
    exit;
}

$lat = floatval($_POST['lat'] ?? 0);
$lng = floatval($_POST['lng'] ?? 0);
$is_available = intval($_POST['is_available'] ?? 0);

if (!$lat || !$lng) {
    echo json_encode(['ok' => false]);
    exit;
}

$stmt = $conn->prepare('UPDATE driver_profiles SET current_lat = ?, current_lng = ?, is_available = ? WHERE user_id = ?');
$stmt->bind_param('ddii', $lat, $lng, $is_available, $user_id);
$stmt->execute();
$stmt->close();

echo json_encode(['ok' => true]);
