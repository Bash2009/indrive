<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/mapbox.php';
require_login();

$user_id = $_SESSION['user_id'];
$token = get_mapbox_token();

$stmt = $conn->prepare('SELECT dp.id AS driver_id, dp.is_available, dp.status, v.id AS vehicle_id, v.vehicle_type FROM driver_profiles dp LEFT JOIN vehicles v ON v.driver_id = dp.id WHERE dp.user_id = ?');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$driver = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$driver || $driver['status'] !== 'approved') {
    header('Location: /dashboard.php');
    exit;
}

$driver_id = $driver['driver_id'];
$is_available = $driver['is_available'] ? 'true' : 'false';
$vehicle_id = $driver['vehicle_id'] ?? 0;
$name = $_SESSION['user_name'] ?? 'Driver';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Indrive — Driver</title>
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
            <span class="app-user-name"><?php echo htmlspecialchars($name); ?></span>
            <a href="/handlers/logout.php" class="app-logout">Sign out</a>
        </div>
    </header>

    <div id="map" class="ride-map"></div>

    <!-- Online Toggle -->
    <div class="online-toggle-wrap" id="online-toggle-wrap">
        <button class="online-toggle" id="online-toggle" onclick="toggleOnline()">
            <span class="online-dot" id="online-dot"></span>
            <span id="online-label">Offline</span>
        </button>
    </div>

    <!-- Requests Panel -->
    <div class="requests-panel" id="requests-panel" style="display:none;">
        <p class="requests-title">Nearby ride requests</p>
        <div class="requests-list" id="requests-list">
            <p class="requests-empty">No requests nearby</p>
        </div>
    </div>

    <script src="<?php echo mapbox_js_url(); ?>"></script>
    <script>
    mapboxgl.accessToken = '<?php echo $token; ?>';

    var map = new mapboxgl.Map({
        container: 'map',
        style: 'mapbox://styles/mapbox/dark-v11',
        center: [-74.006, 40.7128],
        zoom: 14
    });

    var driverId = <?php echo $driver_id; ?>;
    var vehicleId = <?php echo $vehicle_id; ?>;
    var vehicleType = '<?php echo $driver['vehicle_type'] ?? 'economy'; ?>';
    var isOnline = <?php echo $is_available; ?>;
    var userLat = 40.7128, userLng = -74.006;
    var locationInterval = null;
    var requestsInterval = null;
    var driverMarker = null;

    updateToggleUI();

    map.on('load', function() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function(pos) {
                userLat = pos.coords.latitude;
                userLng = pos.coords.longitude;
                map.setCenter([userLng, userLat]);
                driverMarker = new mapboxgl.Marker({color: '#22c55e'})
                    .setLngLat([userLng, userLat])
                    .addTo(map);
                updateLocation();
            });
        }
    });

    function toggleOnline() {
        isOnline = !isOnline;
        updateToggleUI();
        var data = new FormData();
        data.append('is_available', isOnline ? '1' : '0');
        data.append('lat', userLat);
        data.append('lng', userLng);
        fetch('/handlers/driver_location.php', { method: 'POST', body: data })
            .then(function(r) { return r.json(); })
            .then(function() {
                if (isOnline) {
                    startLocationUpdates();
                    startRequestPolling();
                } else {
                    stopLocationUpdates();
                    stopRequestPolling();
                    document.getElementById('requests-panel').style.display = 'none';
                }
            });
    }

    function updateToggleUI() {
        var dot = document.getElementById('online-dot');
        var label = document.getElementById('online-label');
        var wrap = document.getElementById('online-toggle-wrap');
        if (isOnline) {
            dot.className = 'online-dot online';
            label.textContent = 'Online';
            wrap.className = 'online-toggle-wrap online';
        } else {
            dot.className = 'online-dot';
            label.textContent = 'Offline';
            wrap.className = 'online-toggle-wrap';
        }
    }

    function updateLocation() {
        if (!isOnline) return;
        var data = new FormData();
        data.append('is_available', '1');
        data.append('lat', userLat);
        data.append('lng', userLng);
        fetch('/handlers/driver_location.php', { method: 'POST', body: data });
    }

    function startLocationUpdates() {
        if (locationInterval) clearInterval(locationInterval);
        if (navigator.geolocation) {
            navigator.geolocation.watchPosition(function(pos) {
                userLat = pos.coords.latitude;
                userLng = pos.coords.longitude;
                if (driverMarker) driverMarker.setLngLat([userLng, userLat]);
                map.setCenter([userLng, userLat]);
            });
        }
        locationInterval = setInterval(updateLocation, 10000);
    }

    function stopLocationUpdates() {
        if (locationInterval) { clearInterval(locationInterval); locationInterval = null; }
    }

    function startRequestPolling() {
        if (requestsInterval) clearInterval(requestsInterval);
        fetchRequests();
        requestsInterval = setInterval(fetchRequests, 5000);
        document.getElementById('requests-panel').style.display = '';
    }

    function stopRequestPolling() {
        if (requestsInterval) { clearInterval(requestsInterval); requestsInterval = null; }
    }

    function fetchRequests() {
        fetch('/handlers/available_rides.php?lat=' + userLat + '&lng=' + userLng + '&type=' + vehicleType)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                var list = document.getElementById('requests-list');
                if (!data.rides || !data.rides.length) {
                    list.innerHTML = '<p class="requests-empty">No requests nearby</p>';
                    return;
                }
                list.innerHTML = '';
                data.rides.forEach(function(ride) {
                    var card = document.createElement('div');
                    card.className = 'request-card';
                    card.innerHTML =
                        '<div class="request-info">' +
                            '<span class="request-address">' + escapeHtml(ride.pickup_address) + '</span>' +
                            '<span class="request-meta">' + ride.distance_km + ' km away · ' + ride.vehicle_type + ' · $' + ride.estimated_fare + '</span>' +
                        '</div>' +
                        '<div class="request-actions">' +
                            '<button class="btn-accept" onclick="acceptRide(' + ride.id + ')">Accept</button>' +
                        '</div>';
                    list.appendChild(card);
                });
            });
    }

    function acceptRide(rideId) {
        var data = new FormData();
        data.append('ride_id', rideId);
        data.append('vehicle_id', vehicleId);
        fetch('/handlers/accept_ride.php', { method: 'POST', body: data })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (res.ok) {
                    stopRequestPolling();
                    window.location.href = '/driver/ride.php?ride_id=' + rideId;
                } else {
                    alert(res.error || 'Could not accept ride');
                }
            });
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // Start immediately if online
    if (isOnline) {
        startLocationUpdates();
        startRequestPolling();
    }
    </script>

</body>
</html>
