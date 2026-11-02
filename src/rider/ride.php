<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/mapbox.php';
require_login();

$ride_id = intval($_GET['ride_id'] ?? 0);
$user_id = $_SESSION['user_id'];
$token = get_mapbox_token();

if ($ride_id <= 0) {
    header('Location: /rider/home.php');
    exit;
}

$stmt = $conn->prepare('SELECT r.*, dp.rating_avg, u.first_name, u.last_name, v.make, v.model, v.color, v.license_plate FROM rides r LEFT JOIN driver_profiles dp ON r.driver_id = dp.id LEFT JOIN users u ON dp.user_id = u.id LEFT JOIN vehicles v ON r.vehicle_id = v.id WHERE r.id = ? AND r.rider_id = ?');
$stmt->bind_param('ii', $ride_id, $user_id);
$stmt->execute();
$ride = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$ride) {
    header('Location: /rider/home.php');
    exit;
}

$driver_name = ($ride['first_name'] ?? '') . ' ' . ($ride['last_name'] ?? '');
$driver_car = trim(($ride['color'] ?? '') . ' ' . ($ride['year'] ?? '') . ' ' . ($ride['make'] ?? '') . ' ' . ($ride['model'] ?? ''));
$driver_plate = $ride['license_plate'] ?? '--';
$driver_rating = $ride['rating_avg'] ?? '0.00';
$fare = $ride['fare'] ?? '--';
$status = $ride['status'];
$pickup_address = $ride['pickup_address'] ?? '';
$dropoff_address = $ride['dropoff_address'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Indrive — Ride in progress</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400&family=Inter:wght@400;500&family=Space+Grotesk:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/bootstrap.css">
    <link rel="stylesheet" href="<?php echo mapbox_css_url(); ?>">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="app-body">

    <header class="app-header">
        <a href="/dashboard.php" class="auth-logo" style="font-size:1.25rem; margin-bottom:0;">in<span>d</span>rive</a>
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
                <span class="ride-address" id="pickup-addr"><?php echo htmlspecialchars($pickup_address); ?></span>
                <span class="ride-address" id="dropoff-addr"><?php echo htmlspecialchars($dropoff_address); ?></span>
            </div>
        </div>

        <div class="driver-card ride-driver-card" id="driver-card">
            <div class="driver-avatar" id="driver-avatar"><?php echo htmlspecialchars(substr($driver_name, 0, 1)); ?></div>
            <div class="driver-details">
                <span class="driver-name"><?php echo htmlspecialchars($driver_name); ?></span>
                <span class="driver-car"><?php echo htmlspecialchars($driver_car); ?></span>
                <span class="driver-plate"><?php echo htmlspecialchars($driver_plate); ?></span>
            </div>
            <div class="driver-rating">⭐ <?php echo number_format($driver_rating, 1); ?></div>
        </div>

        <div class="ride-fare-row">
            <span class="ride-fare-label">Fare</span>
            <span class="ride-fare-amount" id="fare-display">$<?php echo htmlspecialchars($fare); ?></span>
        </div>

        <div class="ride-fare-row" id="eta-row" style="display:none;">
            <span class="ride-fare-label" id="eta-label">ETA</span>
            <span class="ride-fare-amount" id="eta-value" style="font-size:0.9375rem;">--</span>
        </div>
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

    var rideStatus = '<?php echo $status; ?>';
    var rideId = <?php echo $ride_id; ?>;
    var driverMarker = null;
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

    // Draw route
    map.on('load', function() {
        var pickup = [<?php echo $ride['pickup_lng'] ?? 0; ?>, <?php echo $ride['pickup_lat'] ?? 0; ?>];
        var dropoff = [<?php echo $ride['dropoff_lng'] ?? 0; ?>, <?php echo $ride['dropoff_lat'] ?? 0; ?>];

        if (pickup[0] && dropoff[0]) {
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
    });

    // Poll for updates
    setInterval(function() {
        fetch('/handlers/ride_status.php?ride_id=' + rideId)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                document.getElementById('status-badge').textContent = data.status.replace('_', ' ').replace(/\b\w/g, function(l) { return l.toUpperCase(); });
                if (data.status === 'completed') {
                    window.location.href = '/review.php?ride_id=' + rideId;
                } else if (data.status === 'cancelled') {
                    window.location.href = '/rider/home.php';
                }
                if (data.driver_lat && data.driver_lng) {
                    var pos = [parseFloat(data.driver_lng), parseFloat(data.driver_lat)];
                    if (!driverMarker) {
                        driverMarker = new mapboxgl.Marker({color: '#22c55e'}).setLngLat(pos).addTo(map);
                    } else {
                        driverMarker.setLngLat(pos);
                    }
                    if (data.status === 'accepted' || data.status === 'arriving') {
                        fetchETA(pos, pickupCoords, 'Driver arriving in');
                    } else if (data.status === 'in_progress') {
                        fetchETA(pos, dropoffCoords, 'Arriving in');
                    }
                }
            });
    }, 3000);
    </script>

</body>
</html>
