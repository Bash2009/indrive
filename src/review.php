<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/db.php';
require_login();

$ride_id = intval($_GET['ride_id'] ?? 0);
$user_id = $_SESSION['user_id'];
$role = $_SESSION['user_role'] ?? 'rider';

if ($ride_id <= 0) {
    header('Location: /dashboard.php');
    exit;
}

if ($role === 'rider') {
    $stmt = $conn->prepare('SELECT r.id, r.fare, r.pickup_address, r.dropoff_address, r.completed_at, u.first_name AS d_first, u.last_name AS d_last FROM rides r LEFT JOIN driver_profiles dp ON r.driver_id = dp.id LEFT JOIN users u ON dp.user_id = u.id WHERE r.id = ? AND r.rider_id = ? AND r.status = "completed"');
} else {
    $stmt = $conn->prepare('SELECT r.id, r.fare, r.pickup_address, r.dropoff_address, r.completed_at, u.first_name AS d_first, u.last_name AS d_last FROM rides r LEFT JOIN users u ON r.rider_id = u.id WHERE r.id = ? AND r.driver_id = (SELECT id FROM driver_profiles WHERE user_id = ?) AND r.status = "completed"');
}
$stmt->bind_param('ii', $ride_id, $user_id);
$stmt->execute();
$ride = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$ride) {
    header('Location: /dashboard.php');
    exit;
}

$stmt = $conn->prepare('SELECT id FROM reviews WHERE ride_id = ? AND reviewer_id = ?');
$stmt->bind_param('ii', $ride_id, $user_id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->close();
    header('Location: /dashboard.php');
    exit;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Indrive — Rate your ride</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400&family=Inter:wght@400;500&family=Space+Grotesk:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/bootstrap.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="app-body" style="display:flex; align-items:center; justify-content:center; min-height:100vh; padding:1.5rem;">

    <div class="review-card">
        <a href="/dashboard.php" class="auth-logo" style="font-size:1.25rem; margin-bottom:1.5rem;">in<span>d</span>rive</a>

        <h1 class="review-title">Rate your ride</h1>

        <div class="review-route">
            <span><?php echo htmlspecialchars($ride['pickup_address']); ?></span>
            <span class="review-arrow">→</span>
            <span><?php echo htmlspecialchars($ride['dropoff_address']); ?></span>
        </div>

        <div class="review-meta">
            <span>Fare: $<?php echo number_format($ride['fare'] ?? 0, 2); ?></span>
            <span><?php echo htmlspecialchars($ride['d_first'] . ' ' . $ride['d_last']); ?></span>
        </div>

        <form id="review-form" onsubmit="submitReview(event)">
            <input type="hidden" name="ride_id" value="<?php echo $ride_id; ?>">
            <input type="hidden" name="rating" id="rating-input" value="0">

            <div class="star-rating" id="star-rating">
                <span class="star" data-value="1" onclick="setRating(1)">★</span>
                <span class="star" data-value="2" onclick="setRating(2)">★</span>
                <span class="star" data-value="3" onclick="setRating(3)">★</span>
                <span class="star" data-value="4" onclick="setRating(4)">★</span>
                <span class="star" data-value="5" onclick="setRating(5)">★</span>
            </div>

            <textarea class="form-control review-comment" name="comment" placeholder="Leave a comment (optional)" rows="3"></textarea>

            <button type="submit" class="btn-primary" id="submit-btn" disabled>Submit review</button>
        </form>

        <a href="/dashboard.php" class="review-skip">Skip for now</a>
    </div>

    <script>
    function setRating(val) {
        document.getElementById('rating-input').value = val;
        document.getElementById('submit-btn').disabled = false;
        document.querySelectorAll('.star').forEach(function(s) {
            s.classList.toggle('active', parseInt(s.getAttribute('data-value')) <= val);
        });
    }

    function submitReview(e) {
        e.preventDefault();
        var btn = document.getElementById('submit-btn');
        btn.disabled = true;
        btn.textContent = 'Submitting...';

        var form = document.getElementById('review-form');
        var data = new FormData(form);

        fetch('/handlers/submit_review.php', { method: 'POST', body: data })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                window.location.href = '/dashboard.php';
            });
    }
    </script>

</body>
</html>
