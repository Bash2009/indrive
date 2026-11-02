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
$rating  = intval($_POST['rating'] ?? 0);
$comment = trim($_POST['comment'] ?? '');

if (!$user_id || $ride_id <= 0 || $rating < 1 || $rating > 5) {
    echo json_encode(['ok' => false, 'error' => 'Invalid data']);
    exit;
}

$stmt = $conn->prepare('SELECT id, rider_id, driver_id FROM rides WHERE id = ? AND status = "completed"');
$stmt->bind_param('i', $ride_id);
$stmt->execute();
$ride = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$ride) {
    echo json_encode(['ok' => false, 'error' => 'Ride not found']);
    exit;
}

$stmt = $conn->prepare('SELECT id FROM reviews WHERE ride_id = ? AND reviewer_id = ?');
$stmt->bind_param('ii', $ride_id, $user_id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->close();
    echo json_encode(['ok' => false, 'error' => 'Already reviewed']);
    exit;
}
$stmt->close();

$role = $_SESSION['user_role'] ?? 'rider';
if ($role === 'rider') {
    $reviewee_id = $ride['driver_id'];
    $stmt2 = $conn->prepare('SELECT user_id FROM driver_profiles WHERE id = ?');
    $stmt2->bind_param('i', $reviewee_id);
    $stmt2->execute();
    $row = $stmt2->get_result()->fetch_assoc();
    $stmt2->close();
    $reviewee_user_id = $row ? $row['user_id'] : 0;
} else {
    $reviewee_user_id = $ride['rider_id'];
}

if (!$reviewee_user_id) {
    echo json_encode(['ok' => false, 'error' => 'Could not find reviewee']);
    exit;
}

$stmt = $conn->prepare('INSERT INTO reviews (ride_id, reviewer_id, reviewee_id, rating, comment) VALUES (?, ?, ?, ?, ?)');
$stmt->bind_param('iiiss', $ride_id, $user_id, $reviewee_user_id, $rating, $comment);

if ($stmt->execute()) {
    if ($role === 'driver') {
        $stmt2 = $conn->prepare('UPDATE driver_profiles SET rating_avg = (SELECT avg_rating FROM (SELECT AVG(rating) AS avg_rating FROM reviews WHERE reviewee_id = ?) AS tmp) WHERE id = ?');
        $stmt2->bind_param('ii', $reviewee_user_id, $ride['driver_id']);
        $stmt2->execute();
        $stmt2->close();
    }
    echo json_encode(['ok' => true]);
} else {
    echo json_encode(['ok' => false, 'error' => 'Failed to submit review']);
}
$stmt->close();
