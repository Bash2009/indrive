<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/mapbox.php';
require_once __DIR__ . '/../config/fare.php';
require_login();

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT id FROM rides WHERE rider_id = ? AND status IN ('requested', 'accepted', 'arriving', 'in_progress') ORDER BY id DESC LIMIT 1");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$active = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($active) {
    header('Location: /rider/ride.php?ride_id=' . $active['id']);
    exit;
}

$token = get_mapbox_token();
$name = $_SESSION['user_name'] ?? 'User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Indrive — Book a ride</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400&family=Inter:wght@400;500&family=Space+Grotesk:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/bootstrap.css">
    <link rel="stylesheet" href="<?php echo mapbox_css_url(); ?>">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="app-body">

    <!-- Header -->
    <header class="app-header">
        <a href="/dashboard.php" class="auth-logo" style="font-size:1.25rem; margin-bottom:0;">in<span>d</span>rive</a>
        <div class="app-header-right">
            <span class="app-user-name"><?php echo htmlspecialchars($name); ?></span>
            <a href="/handlers/logout.php" class="app-logout">Sign out</a>
        </div>
    </header>

    <!-- Map -->
    <div id="map" class="ride-map"></div>

    <!-- Search Panel -->
    <div class="search-panel" id="search-panel">
        <div class="search-panel-inner">
            <div class="search-box-group">
                <div class="search-dot pickup-dot"></div>
                <input type="text" id="pickup-input" class="search-input" placeholder="Pickup location" autocomplete="off">
                <div class="search-dot-line"></div>
                <div class="search-dot dropoff-dot"></div>
                <input type="text" id="dropoff-input" class="search-input" placeholder="Where to?" autocomplete="off">
            </div>
            <div id="pickup-suggestions" class="suggestions-list"></div>
            <div id="dropoff-suggestions" class="suggestions-list"></div>
        </div>
    </div>

    <!-- Vehicle Selection + Fare (hidden until destination selected) -->
    <div class="vehicle-panel" id="vehicle-panel">
        <div class="vehicle-panel-inner">
            <p class="vehicle-panel-title">Choose a ride</p>
            <div class="vehicle-options" id="vehicle-options">
                <button class="vehicle-card active" data-type="economy" onclick="selectVehicle('economy')">
                    <div class="vehicle-icon">🚗</div>
                    <div class="vehicle-info">
                        <span class="vehicle-name">Economy</span>
                        <span class="vehicle-eta" id="eta-economy">--</span>
                    </div>
                    <span class="vehicle-fare" id="fare-economy">--</span>
                </button>
                <button class="vehicle-card" data-type="comfort" onclick="selectVehicle('comfort')">
                    <div class="vehicle-icon">🚙</div>
                    <div class="vehicle-info">
                        <span class="vehicle-name">Comfort</span>
                        <span class="vehicle-eta" id="eta-comfort">--</span>
                    </div>
                    <span class="vehicle-fare" id="fare-comfort">--</span>
                </button>
                <button class="vehicle-card" data-type="premium" onclick="selectVehicle('premium')">
                    <div class="vehicle-icon">🏎️</div>
                    <div class="vehicle-info">
                        <span class="vehicle-name">Premium</span>
                        <span class="vehicle-eta" id="eta-premium">--</span>
                    </div>
                    <span class="vehicle-fare" id="fare-premium">--</span>
                </button>
            </div>
            <button class="btn-primary ride-request-btn" id="request-btn" onclick="requestRide()" disabled>
                Request ride
            </button>
        </div>
    </div>

    <!-- Status Overlay (shown after ride requested) -->
    <div class="status-overlay" id="status-overlay" style="display:none;">
        <div class="status-panel">
            <div class="status-spinner"></div>
            <p class="status-text" id="status-text">Looking for a driver...</p>
            <div class="driver-info" id="driver-info" style="display:none;">
                <div class="driver-card">
                    <div class="driver-avatar" id="driver-avatar">D</div>
                    <div class="driver-details">
                        <span class="driver-name" id="driver-name">--</span>
                        <span class="driver-car" id="driver-car">--</span>
                        <span class="driver-plate" id="driver-plate">--</span>
                        <span class="vehicle-type-badge" id="vehicle-type-badge" style="display:inline-block; font-size:0.75rem; background:#4F46E5; color:#fff; padding:2px 8px; border-radius:4px; margin-top:4px;"></span>
                    </div>
                </div>
            </div>
            <button class="btn-cancel" id="cancel-btn" onclick="cancelRide()">Cancel ride</button>
        </div>
    </div>

    <script src="<?php echo mapbox_js_url(); ?>"></script>
    <script>
    mapboxgl.accessToken = '<?php echo $token; ?>';

    var map, pickupMarker, dropoffMarker, routeLayer;
    var userLat = 40.7128, userLng = -74.0060;
    var pickupLat = null, pickupLng = null, pickupAddress = '';
    var dropoffLat = null, dropoffLng = null, dropoffAddress = '';
    var selectedVehicle = 'economy';
    var currentRideId = null;
    var pollInterval = null;

    var fares = <?php echo json_encode([
        'economy' => ['base' => 2.50, 'per_km' => 0.80],
        'comfort' => ['base' => 4.00, 'per_km' => 1.20],
        'premium' => ['base' => 6.00, 'per_km' => 1.80],
    ]); ?>;

    map = new mapboxgl.Map({
        container: 'map',
        style: 'mapbox://styles/mapbox/dark-v11',
        center: [userLng, userLat],
        zoom: 14
    });

    map.on('load', function() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function(pos) {
                userLat = pos.coords.latitude;
                userLng = pos.coords.longitude;
                map.setCenter([userLng, userLat]);
                setPickup(userLat, userLng, 'Current location');
            });
        } else {
            setPickup(userLat, userLng, 'Current location');
        }
    });

    function setPickup(lat, lng, address) {
        pickupLat = lat;
        pickupLng = lng;
        pickupAddress = address;
        document.getElementById('pickup-input').value = address;
        if (pickupMarker) pickupMarker.remove();
        pickupMarker = new mapboxgl.Marker({color: '#4F46E5'})
            .setLngLat([lng, lat])
            .addTo(map);
        if (dropoffLat) {
            updateRoute();
            updateFares();
        }
    }

    function setDropoff(lat, lng, address) {
        console.log('setDropoff called:', lat, lng, address);
        dropoffLat = lat;
        dropoffLng = lng;
        dropoffAddress = address;
        document.getElementById('dropoff-input').value = address;
        if (dropoffMarker) dropoffMarker.remove();
        dropoffMarker = new mapboxgl.Marker({color: '#F43F5E'})
            .setLngLat([lng, lat])
            .addTo(map);
        if (!pickupLat || !pickupLng) {
            pickupLat = userLat;
            pickupLng = userLng;
            pickupAddress = 'Current location';
            document.getElementById('pickup-input').value = pickupAddress;
            if (pickupMarker) pickupMarker.remove();
            pickupMarker = new mapboxgl.Marker({color: '#4F46E5'})
                .setLngLat([pickupLng, pickupLat])
                .addTo(map);
        }
        updateRoute();
        updateFares();
    }

    function updateRoute() {
        if (!pickupLat || !dropoffLat) return;
        drawRoute();
    }

    function drawRoute() {
        if (!pickupLat || !dropoffLat) return;

        if (map.getLayer('route')) map.removeLayer('route');
        if (map.getSource('route')) map.removeSource('route');

        var url = 'https://api.mapbox.com/directions/v5/mapbox/driving/'
            + pickupLng + ',' + pickupLat + ';' + dropoffLng + ',' + dropoffLat
            + '?geometries=geojson&access_token=' + mapboxgl.accessToken;

        fetch(url)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.code !== 'Ok' || !data.routes || !data.routes.length) {
                    console.error('Directions API error:', data);
                    showVehiclePanel();
                    return;
                }
                var route = data.routes[0];
                var geom = route.geometry;

                try {
                    map.addSource('route', {
                        type: 'geojson',
                        data: { type: 'Feature', geometry: geom }
                    });
                    map.addLayer({
                        id: 'route',
                        type: 'line',
                        source: 'route',
                        layout: { 'line-join': 'round', 'line-cap': 'round' },
                        paint: { 'line-color': '#4F46E5', 'line-width': 4, 'line-opacity': 0.8 }
                    });

                    var bounds = new mapboxgl.LngLatBounds();
                    geom.coordinates.forEach(function(c) { bounds.extend(c); });
                    map.fitBounds(bounds, { padding: 80 });
                } catch (e) {
                    console.error('Failed to add route layer:', e);
                }

                showVehiclePanel();
            })
            .catch(function(err) {
                console.error('Route fetch failed:', err);
                showVehiclePanel();
            });
    }

    function showVehiclePanel() {
        document.getElementById('vehicle-panel').style.display = 'block';
        document.getElementById('request-btn').disabled = false;
    }

    function updateFares() {
        if (!pickupLat || !dropoffLat) return;
        var dist = getDistance(pickupLat, pickupLng, dropoffLat, dropoffLng);
        ['economy', 'comfort', 'premium'].forEach(function(type) {
            var fare = (fares[type].base + dist * fares[type].per_km).toFixed(2);
            document.getElementById('fare-' + type).textContent = '$' + fare;
        });
    }

    function getDistance(lat1, lng1, lat2, lng2) {
        var R = 6371;
        var dLat = (lat2 - lat1) * Math.PI / 180;
        var dLng = (lng2 - lng1) * Math.PI / 180;
        var a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                Math.sin(dLng/2) * Math.sin(dLng/2);
        return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    }

    function selectVehicle(type) {
        selectedVehicle = type;
        document.querySelectorAll('.vehicle-card').forEach(function(c) {
            c.classList.toggle('active', c.getAttribute('data-type') === type);
        });
    }

    function requestRide() {
        if (!pickupLat || !dropoffLat) return;
        var btn = document.getElementById('request-btn');
        btn.disabled = true;
        btn.textContent = 'Requesting...';

        var data = new FormData();
        data.append('pickup_lat', pickupLat);
        data.append('pickup_lng', pickupLng);
        data.append('pickup_address', pickupAddress);
        data.append('dropoff_lat', dropoffLat);
        data.append('dropoff_lng', dropoffLng);
        data.append('dropoff_address', dropoffAddress);
        data.append('vehicle_type', selectedVehicle);

        fetch('/handlers/request_ride.php', { method: 'POST', body: data })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (res.ok) {
                    currentRideId = res.ride_id;
                    document.getElementById('search-panel').style.display = 'none';
                    document.getElementById('vehicle-panel').style.display = 'none';
                    document.getElementById('status-overlay').style.display = '';
                    startPolling();
                } else {
                    alert(res.error || 'Could not request ride');
                    btn.disabled = false;
                    btn.textContent = 'Request ride';
                }
            });
    }

    function startPolling() {
        pollInterval = setInterval(function() {
            if (!currentRideId) return;
            fetch('/handlers/ride_status.php?ride_id=' + currentRideId)
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.status === 'accepted') {
                        document.getElementById('status-text').textContent = 'Driver is on the way!';
                        document.getElementById('status-spinner').style.display = 'none';
                        document.getElementById('driver-info').style.display = '';
                        document.getElementById('driver-name').textContent = data.driver_name || '--';
                        document.getElementById('driver-car').textContent = data.driver_car || '--';
                        document.getElementById('driver-plate').textContent = data.driver_plate || '--';
                        document.getElementById('driver-avatar').textContent = (data.driver_name || 'D')[0].toUpperCase();
                        document.getElementById('vehicle-type-badge').textContent = data.vehicle_type || '';
                        document.getElementById('cancel-btn').style.display = 'none';
                    } else if (data.status === 'arriving') {
                        document.getElementById('status-text').textContent = 'Your driver has arrived!';
                    } else if (data.status === 'in_progress') {
                        clearInterval(pollInterval);
                        window.location.href = '/rider/ride.php?ride_id=' + currentRideId;
                    } else if (data.status === 'cancelled') {
                        clearInterval(pollInterval);
                        resetUI();
                    } else if (data.status === 'completed') {
                        clearInterval(pollInterval);
                        window.location.href = '/review.php?ride_id=' + currentRideId;
                    }
                });
        }, 3000);
    }

    function cancelRide() {
        if (!currentRideId) return;
        var data = new FormData();
        data.append('ride_id', currentRideId);
        fetch('/handlers/cancel_ride.php', { method: 'POST', body: data })
            .then(function(r) { return r.json(); })
            .then(function() {
                clearInterval(pollInterval);
                resetUI();
            });
    }

    function resetUI() {
        currentRideId = null;
        document.getElementById('search-panel').style.display = '';
        document.getElementById('vehicle-panel').style.display = 'none';
        document.getElementById('status-overlay').style.display = 'none';
        document.getElementById('request-btn').disabled = true;
        document.getElementById('request-btn').textContent = 'Request ride';
        document.getElementById('driver-info').style.display = 'none';
        document.getElementById('cancel-btn').style.display = '';
        document.getElementById('status-spinner').style.display = '';
        document.getElementById('status-text').textContent = 'Looking for a driver...';
        dropoffLat = null;
        dropoffLng = null;
        dropoffAddress = '';
        document.getElementById('dropoff-input').value = '';
        if (dropoffMarker) dropoffMarker.remove();
        if (map.getLayer('route')) map.removeLayer('route');
        if (map.getSource('route')) map.removeSource('route');
    }

    // Autocomplete for search inputs
    var searchTimeout = null;
    function setupAutocomplete(inputId, suggestionsId, type) {
        var input = document.getElementById(inputId);
        var list = document.getElementById(suggestionsId);
        input.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            var q = input.value.trim();
            if (q.length < 2) { list.innerHTML = ''; list.style.display = 'none'; return; }
            searchTimeout = setTimeout(function() {
                var cLng = pickupLng || userLng;
                var cLat = pickupLat || userLat;
                fetch('https://api.mapbox.com/geocoding/v5/mapbox.places/' + encodeURIComponent(q)
                    + '.json?access_token=' + mapboxgl.accessToken + '&limit=5&proximity=' + cLng + ',' + cLat)
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        list.innerHTML = '';
                        if (!data.features || !data.features.length) { list.style.display = 'none'; return; }
                        list.style.display = 'block';
                        data.features.forEach(function(f) {
                            var item = document.createElement('div');
                            item.className = 'suggestion-item';
                            item.textContent = f.place_name;
                            item.addEventListener('click', function() {
                                var coords = f.center;
                                if (type === 'pickup') {
                                    setPickup(coords[1], coords[0], f.place_name);
                                } else {
                                    setDropoff(coords[1], coords[0], f.place_name);
                                }
                                list.innerHTML = '';
                                list.style.display = 'none';
                            });
                            list.appendChild(item);
                        });
                    });
            }, 300);
        });
        input.addEventListener('blur', function() {
            setTimeout(function() { list.style.display = 'none'; }, 200);
        });
    }

    setupAutocomplete('pickup-input', 'pickup-suggestions', 'pickup');
    setupAutocomplete('dropoff-input', 'dropoff-suggestions', 'dropoff');
    </script>

</body>
</html>
