<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/mapbox.php';
require_login();

$ride_id = intval($_GET['ride_id'] ?? 0);
$user_id = $_SESSION['user_id'];
$token = get_mapbox_token();

if ($ride_id <= 0) {
    header('Location: /driver/home.php');
    exit;
}

$stmt = $conn->prepare('SELECT r.*, u.first_name, u.last_name, u.phone, dp.id AS dp_id, v.make, v.model, v.color, v.license_plate FROM rides r LEFT JOIN users u ON r.rider_id = u.id LEFT JOIN driver_profiles dp ON r.driver_id = dp.id LEFT JOIN vehicles v ON r.vehicle_id = v.id WHERE r.id = ? AND dp.user_id = ?');
$stmt->bind_param('ii', $ride_id, $user_id);
$stmt->execute();
$ride = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$ride) {
    header('Location: /driver/home.php');
    exit;
}

$rider_name = ($ride['first_name'] ?? '') . ' ' . ($ride['last_name'] ?? '');
$fare = $ride['fare'] ?? '--';
$status = $ride['status'];
$pickup_address = $ride['pickup_address'] ?? '';
$dropoff_address = $ride['dropoff_address'] ?? '';
$car_info = trim(($ride['make'] ?? '') . ' ' . ($ride['color'] ?? '') . ' ' . ($ride['year'] ?? '') . ' ' . ($ride['model'] ?? ''));

$next_action = '';
$next_label = '';
if ($status === 'accepted') {
    $next_action = 'arriving';
    $next_label = 'Head to pickup';
} elseif ($status === 'arriving') {
    $next_action = 'in_progress';
    $next_label = 'Start ride';
} elseif ($status === 'in_progress') {
    $next_action = 'completed';
    $next_label = 'Complete ride';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Indrive — Active ride</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400&family=Inter:wght@400;500&family=Space+Grotesk:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/bootstrap.css">
    <link rel="stylesheet" href="<?php echo mapbox_css_url(); ?>">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="app-body">

    <header class="app-header">
        <a href="/driver/home.php" class="auth-logo" style="font-size:1.25rem; margin-bottom:0;">in<span>d</span>rive</a>
        <div class="app-header-right">
            <span class="ride-status-badge" id="status-badge"><?php echo ucfirst(str_replace('_', ' ', $status)); ?></span>
        </div>
    </header>

    <div id="map" class="ride-map"></div>

    <!-- Ride Info Panel -->
    <div class="ride-info-panel" id="ride-panel">
        <div class="ride-route-info">
            <div class="ride-route-dots">
                <div class="route-dot pickup-dot"></div>
                <div class="route-dot-line"></div>
                <div class="route-dot dropoff-dot"></div>
            </div>
            <div class="ride-route-addresses">
                <span class="ride-address"><?php echo htmlspecialchars($pickup_address); ?></span>
                <span class="ride-address"><?php echo htmlspecialchars($dropoff_address); ?></span>
            </div>
        </div>

        <div class="driver-card ride-driver-card">
            <div class="driver-avatar"><?php echo htmlspecialchars(substr($rider_name, 0, 1)); ?></div>
            <div class="driver-details">
                <span class="driver-name"><?php echo htmlspecialchars($rider_name); ?></span>
                <span class="driver-car"><?php echo htmlspecialchars($car_info); ?></span>
                <span class="driver-car">Plate: <?php echo htmlspecialchars($ride['license_plate'] ?? ''); ?> &middot; Cash</span>
            </div>
        </div>

        <div class="ride-fare-row">
            <span class="ride-fare-label">Fare</span>
            <span class="ride-fare-amount">$<?php echo htmlspecialchars($fare); ?></span>
        </div>

        <div class="ride-fare-row" id="eta-row" style="display:none;">
            <span class="ride-fare-label" id="eta-label">ETA</span>
            <span class="ride-fare-amount" id="eta-value" style="font-size:0.9375rem;">--</span>
        </div>

        <?php if ($next_action): ?>
        <button class="btn-primary ride-action-btn" id="action-btn" onclick="performAction()">
            <?php echo htmlspecialchars($next_label); ?>
        </button>
        <?php endif; ?>

        <?php if (in_array($status, ['accepted', 'arriving'])): ?>
        <button class="btn-cancel" style="width:100%; margin-top:0.5rem;" onclick="cancelRide()">Cancel ride</button>
        <?php endif; ?>
    </div>

    <script src="<?php echo mapbox_js_url(); ?>"></script>
    <script>
    mapboxgl.accessToken = '<?php echo $token; ?>';

    var map = new mapboxgl.Map({
        container: 'map',
        style: 'mapbox://styles/mapbox/dark-v11',
        center: [<?php echo $ride['pickup_lng'] ?? -74.006; ?>, <?php echo $ride['pickup_lat'] ?? 40.7128; ?>],
        zoom: 14
    });

    var rideId = <?php echo $ride_id; ?>;
    var rideStatus = '<?php echo $status; ?>';
    var riderMarker = null;
    var pickupCoords = [<?php echo $ride['pickup_lng'] ?? 0; ?>, <?php echo $ride['pickup_lat'] ?? 0; ?>];
    var dropoffCoords = [<?php echo $ride['dropoff_lng'] ?? 0; ?>, <?php echo $ride['dropoff_lat'] ?? 0; ?>];

    function fetchETA(from, to, label) {
        var url = 'https://api.mapbox.com/directions/v5/mapbox/driving/'
            + from[0] + ',' + from[1] + ';' + to[0] + ',' + to[1]
            + '?access_token=' + mapboxgl.accessToken;
        fetch(url).then(function(r) { return r.json(); }).then(function(data) {
            if (data.routes && data.routes.length) {
                var mins = Math.ceil(data.routes[0].duration / 60);
                document.getElementById('eta-row').style.display = '';
                document.getElementById('eta-label').textContent = label;
                document.getElementById('eta-value').textContent = mins + ' min';
            }
        });
    }

    function updateETA() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function(pos) {
                var driverPos = [pos.coords.longitude, pos.coords.latitude];
                if (rideStatus === 'accepted' || rideStatus === 'arriving') {
                    fetchETA(driverPos, pickupCoords, 'To pickup');
                } else if (rideStatus === 'in_progress') {
                    fetchETA(driverPos, dropoffCoords, 'To destination');
                }
            });
        }
    }

    updateETA();
    setInterval(updateETA, 30000);

    map.on('load', function() {
        var pickup = [<?php echo $ride['pickup_lng'] ?? 0; ?>, <?php echo $ride['pickup_lat'] ?? 0; ?>];
        var dropoff = [<?php echo $ride['dropoff_lng'] ?? 0; ?>, <?php echo $ride['dropoff_lat'] ?? 0; ?>];

        if (pickup[0] && dropoff[0]) {
            var target = rideStatus === 'in_progress' ? dropoff : pickup;
            var url = 'https://api.mapbox.com/directions/v5/mapbox/driving/'
                + pickup[0] + ',' + pickup[1] + ';' + dropoff[0] + ',' + dropoff[1]
                + '?geometries=geojson&access_token=' + mapboxgl.accessToken;

            fetch(url).then(function(r) { return r.json(); }).then(function(data) {
                if (data.routes && data.routes.length) {
                    var geom = data.routes[0].geometry;
                    map.addSource('route', { type: 'geojson', data: { type: 'Feature', geometry: geom } });
                    map.addLayer({
                        id: 'route', type: 'line', source: 'route',
                        layout: { 'line-join': 'round', 'line-cap': 'round' },
                        paint: { 'line-color': '#4F46E5', 'line-width': 3, 'line-opacity': 0.8 }
                    });
                    var bounds = new mapboxgl.LngLatBounds();
                    geom.coordinates.forEach(function(c) { bounds.extend(c); });
                    map.fitBounds(bounds, { padding: 80 });
                }
            });
        }

        new mapboxgl.Marker({color: '#4F46E5'}).setLngLat(pickup).addTo(map);
        new mapboxgl.Marker({color: '#F43F5E'}).setLngLat(dropoff).addTo(map);

        // Track rider location
        setInterval(function() {
            fetch('/handlers/ride_status.php?ride_id=' + rideId)
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.status === 'cancelled') {
                        window.location.href = '/driver/home.php';
                    } else if (data.status === 'completed') {
                        window.location.href = '/review.php?ride_id=' + rideId;
                    }
                });
        }, 5000);
    });

    function performAction() {
        var btn = document.getElementById('action-btn');
        btn.disabled = true;
        btn.textContent = 'Updating...';

        var data = new FormData();
        data.append('ride_id', rideId);
        data.append('action', '<?php echo $next_action; ?>');

        fetch('/handlers/update_ride.php', { method: 'POST', body: data })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (res.ok) {
                    if ('<?php echo $next_action; ?>' === 'completed') {
                        window.location.href = '/review.php?ride_id=' + rideId;
                    } else {
                        window.location.reload();
                    }
                } else {
                    alert(res.error || 'Action failed');
                    btn.disabled = false;
                    btn.textContent = '<?php echo htmlspecialchars($next_label); ?>';
                }
            });
    }

    function cancelRide() {
        if (!confirm('Are you sure you want to cancel this ride?')) return;
        var data = new FormData();
        data.append('ride_id', rideId);
        fetch('/handlers/cancel_ride.php', { method: 'POST', body: data })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (res.ok) {
                    window.location.href = '/driver/home.php';
                } else {
                    alert(res.error || 'Could not cancel ride');
                }
            });
    }
    </script>

</body>
</html>
