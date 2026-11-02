<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
require_admin();

$name = $_SESSION['user_name'] ?? 'Admin';

$total_riders = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'rider'")->fetch_row()[0];
$total_drivers = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'driver'")->fetch_row()[0];
$active_drivers = $conn->query("SELECT COUNT(*) FROM driver_profiles WHERE is_available = 1 AND status = 'approved'")->fetch_row()[0];
$pending_drivers = $conn->query("SELECT COUNT(*) FROM driver_profiles WHERE status = 'pending'")->fetch_row()[0];

$total_rides = $conn->query("SELECT COUNT(*) FROM rides")->fetch_row()[0];
$completed_rides = $conn->query("SELECT COUNT(*) FROM rides WHERE status = 'completed'")->fetch_row()[0];
$cancelled_rides = $conn->query("SELECT COUNT(*) FROM rides WHERE status = 'cancelled'")->fetch_row()[0];
$active_rides = $conn->query("SELECT COUNT(*) FROM rides WHERE status IN ('requested', 'accepted', 'arriving', 'in_progress')")->fetch_row()[0];

$total_revenue = $conn->query("SELECT COALESCE(SUM(fare), 0) FROM rides WHERE status = 'completed'")->fetch_row()[0];
$avg_rating = $conn->query("SELECT COALESCE(AVG(rating), 0) FROM reviews")->fetch_row()[0];
$total_reviews = $conn->query("SELECT COUNT(*) FROM reviews")->fetch_row()[0];

$recent_rides = $conn->query("
    SELECT r.id, r.status, r.fare, r.vehicle_type, r.requested_at,
           CONCAT(ru.first_name, ' ', ru.last_name) AS rider_name,
           CONCAT(du.first_name, ' ', du.last_name) AS driver_name
    FROM rides r
    LEFT JOIN users ru ON r.rider_id = ru.id
    LEFT JOIN driver_profiles dp ON r.driver_id = dp.id
    LEFT JOIN users du ON dp.user_id = du.id
    ORDER BY r.created_at DESC LIMIT 10
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard — Indrive</title>
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
                <li><a href="/admin/dashboard.php" class="active"><span class="admin-nav-icon">📊</span> <span>Dashboard</span></a></li>
                <li><a href="/admin/drivers.php"><span class="admin-nav-icon">🚗</span> <span>Drivers</span></a></li>
                <li><a href="/admin/rides.php"><span class="admin-nav-icon">🗺️</span> <span>Rides</span></a></li>
            </ul>
            <div class="admin-sidebar-footer">
                <a href="/handlers/logout.php"><span>🚪</span> <span>Logout</span></a>
            </div>
        </aside>

        <main class="admin-main">
            <div class="admin-header">
                <h2>Dashboard</h2>
                <p>Welcome back, <?php echo htmlspecialchars($name); ?></p>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-card-label">Total Riders</div>
                    <div class="stat-card-value"><?php echo number_format($total_riders); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-label">Total Drivers</div>
                    <div class="stat-card-value indigo"><?php echo number_format($total_drivers); ?></div>
                    <div class="stat-card-sub"><?php echo $active_drivers; ?> online &middot; <?php echo $pending_drivers; ?> pending</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-label">Total Rides</div>
                    <div class="stat-card-value"><?php echo number_format($total_rides); ?></div>
                    <div class="stat-card-sub"><?php echo $completed_rides; ?> completed &middot; <?php echo $cancelled_rides; ?> cancelled</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-label">Active Rides</div>
                    <div class="stat-card-value green"><?php echo number_format($active_rides); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-label">Revenue</div>
                    <div class="stat-card-value coral">$<?php echo number_format($total_revenue, 2); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-label">Avg Rating</div>
                    <div class="stat-card-value"><?php echo number_format($avg_rating, 1); ?></div>
                    <div class="stat-card-sub"><?php echo number_format($total_reviews); ?> reviews</div>
                </div>
            </div>

            <?php if ($pending_drivers > 0): ?>
            <div style="background: rgba(234, 179, 8, 0.1); border: 1px solid rgba(234, 179, 8, 0.3); border-radius: 12px; padding: 1rem 1.25rem; margin-bottom: 2rem; display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <strong style="color: #eab308;"><?php echo $pending_drivers; ?> driver<?php echo $pending_drivers > 1 ? 's' : ''; ?> pending approval</strong>
                    <span style="color: var(--id-text-muted); margin-left: 0.5rem;">Review and approve driver applications</span>
                </div>
                <a href="/admin/drivers.php?tab=pending" class="btn-admin btn-admin-approve">Review</a>
            </div>
            <?php endif; ?>

            <div class="admin-table-wrap">
                <div class="admin-table-header">
                    <h3>Recent Rides</h3>
                    <a href="/admin/rides.php" class="btn-admin btn-admin-view">View all</a>
                </div>
                <?php if (empty($recent_rides)): ?>
                    <div class="admin-empty"><p>No rides yet</p></div>
                <?php else: ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Rider</th>
                            <th>Driver</th>
                            <th>Type</th>
                            <th>Fare</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_rides as $ride): ?>
                        <tr>
                            <td>#<?php echo $ride['id']; ?></td>
                            <td><?php echo htmlspecialchars($ride['rider_name'] ?? '--'); ?></td>
                            <td><?php echo htmlspecialchars($ride['driver_name'] ?? '--'); ?></td>
                            <td><?php echo ucfirst($ride['vehicle_type']); ?></td>
                            <td><?php echo $ride['fare'] ? '$' . number_format($ride['fare'], 2) : '--'; ?></td>
                            <td><span class="badge badge-<?php echo $ride['status']; ?>"><?php echo str_replace('_', ' ', $ride['status']); ?></span></td>
                            <td><?php echo date('M d, H:i', strtotime($ride['requested_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
