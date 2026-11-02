<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
require_admin();

$filter = $_GET['filter'] ?? 'all';
$valid_filters = ['all', 'completed', 'cancelled', 'active'];
if (!in_array($filter, $valid_filters)) $filter = 'all';

$where = '';
if ($filter === 'completed') $where = "WHERE r.status = 'completed'";
elseif ($filter === 'cancelled') $where = "WHERE r.status = 'cancelled'";
elseif ($filter === 'active') $where = "WHERE r.status IN ('requested', 'accepted', 'arriving', 'in_progress')";

$rides = $conn->query("
    SELECT r.id, r.status, r.fare, r.vehicle_type, r.distance_km, r.pickup_address, r.dropoff_address,
           r.requested_at, r.completed_at, r.cancelled_by,
           CONCAT(ru.first_name, ' ', ru.last_name) AS rider_name,
           CONCAT(du.first_name, ' ', du.last_name) AS driver_name,
           v.make, v.model, v.color, v.license_plate
    FROM rides r
    LEFT JOIN users ru ON r.rider_id = ru.id
    LEFT JOIN driver_profiles dp ON r.driver_id = dp.id
    LEFT JOIN users du ON dp.user_id = du.id
    LEFT JOIN vehicles v ON r.vehicle_id = v.id
    {$where}
    ORDER BY r.created_at DESC
    LIMIT 50
")->fetch_all(MYSQLI_ASSOC);

$counts = [
    'all'       => $conn->query("SELECT COUNT(*) FROM rides")->fetch_row()[0],
    'completed' => $conn->query("SELECT COUNT(*) FROM rides WHERE status = 'completed'")->fetch_row()[0],
    'cancelled' => $conn->query("SELECT COUNT(*) FROM rides WHERE status = 'cancelled'")->fetch_row()[0],
    'active'    => $conn->query("SELECT COUNT(*) FROM rides WHERE status IN ('requested', 'accepted', 'arriving', 'in_progress')")->fetch_row()[0],
];
$total_revenue = $conn->query("SELECT COALESCE(SUM(fare), 0) FROM rides WHERE status = 'completed'")->fetch_row()[0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ride History — Indrive Admin</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <div class="admin-layout">
        <aside class="admin-sidebar">
            <div class="admin-sidebar-brand">
                <h1>In<span>drive</span></h1>
                <small>Admin Panel</small>
            </div>
            <ul class="admin-nav">
                <li><a href="/admin/dashboard.php"><span class="admin-nav-icon">📊</span> <span>Dashboard</span></a></li>
                <li><a href="/admin/drivers.php"><span class="admin-nav-icon">🚗</span> <span>Drivers</span></a></li>
                <li><a href="/admin/rides.php" class="active"><span class="admin-nav-icon">🗺️</span> <span>Rides</span></a></li>
            </ul>
            <div class="admin-sidebar-footer">
                <a href="/handlers/logout.php"><span>🚪</span> <span>Logout</span></a>
            </div>
        </aside>

        <main class="admin-main">
            <div class="admin-header">
                <h2>Ride History</h2>
                <p>All platform rides &middot; $<?php echo number_format($total_revenue, 2); ?> total revenue</p>
            </div>

            <div class="admin-tabs">
                <a href="?filter=all" class="admin-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">All (<?php echo $counts['all']; ?>)</a>
                <a href="?filter=active" class="admin-tab <?php echo $filter === 'active' ? 'active' : ''; ?>">Active (<?php echo $counts['active']; ?>)</a>
                <a href="?filter=completed" class="admin-tab <?php echo $filter === 'completed' ? 'active' : ''; ?>">Completed (<?php echo $counts['completed']; ?>)</a>
                <a href="?filter=cancelled" class="admin-tab <?php echo $filter === 'cancelled' ? 'active' : ''; ?>">Cancelled (<?php echo $counts['cancelled']; ?>)</a>
            </div>

            <?php if (empty($rides)): ?>
                <div class="admin-empty"><p>No rides found</p></div>
            <?php else: ?>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Rider</th>
                            <th>Driver</th>
                            <th>Vehicle</th>
                            <th>Route</th>
                            <th>Type</th>
                            <th>Distance</th>
                            <th>Fare</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rides as $r): ?>
                        <tr>
                            <td>#<?php echo $r['id']; ?></td>
                            <td><?php echo htmlspecialchars($r['rider_name'] ?? '--'); ?></td>
                            <td><?php echo htmlspecialchars($r['driver_name'] ?? '--'); ?></td>
                            <td><?php echo $r['color'] ? htmlspecialchars($r['color'] . ' ' . $r['make']) : '--'; ?></td>
                            <td style="max-width:200px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="<?php echo htmlspecialchars($r['pickup_address'] . ' → ' . $r['dropoff_address']); ?>">
                                <?php echo htmlspecialchars(substr($r['pickup_address'], 0, 20)); ?> → <?php echo htmlspecialchars(substr($r['dropoff_address'], 0, 20)); ?>
                            </td>
                            <td><?php echo ucfirst($r['vehicle_type']); ?></td>
                            <td><?php echo $r['distance_km'] ? number_format($r['distance_km'], 1) . ' km' : '--'; ?></td>
                            <td><?php echo $r['fare'] ? '$' . number_format($r['fare'], 2) : '--'; ?></td>
                            <td><span class="badge badge-<?php echo $r['status']; ?>"><?php echo str_replace('_', ' ', $r['status']); ?></span></td>
                            <td><?php echo date('M d, H:i', strtotime($r['requested_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
