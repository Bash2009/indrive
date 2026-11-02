<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
require_admin();

$tab = $_GET['tab'] ?? 'pending';
$valid_tabs = ['pending', 'approved', 'suspended', 'all'];
if (!in_array($tab, $valid_tabs)) $tab = 'pending';

$where = '';
if ($tab !== 'all') {
    $where = "WHERE dp.status = '" . $conn->real_escape_string($tab) . "'";
}

$drivers = $conn->query("
    SELECT dp.id AS dp_id, dp.status, dp.license_number, dp.license_expiry, dp.rating_avg, dp.total_rides, dp.is_available,
           u.id AS user_id, u.first_name, u.last_name, u.email, u.phone, u.is_active,
           v.make, v.model, v.year, v.color, v.license_plate, v.vehicle_type
    FROM driver_profiles dp
    JOIN users u ON dp.user_id = u.id
    LEFT JOIN vehicles v ON v.driver_id = dp.id
    {$where}
    ORDER BY dp.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

$counts = [
    'pending'   => $conn->query("SELECT COUNT(*) FROM driver_profiles WHERE status = 'pending'")->fetch_row()[0],
    'approved'  => $conn->query("SELECT COUNT(*) FROM driver_profiles WHERE status = 'approved'")->fetch_row()[0],
    'suspended' => $conn->query("SELECT COUNT(*) FROM driver_profiles WHERE status = 'suspended'")->fetch_row()[0],
    'all'       => $conn->query("SELECT COUNT(*) FROM driver_profiles")->fetch_row()[0],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Drivers — Indrive Admin</title>
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
                <li><a href="/admin/drivers.php" class="active"><span class="admin-nav-icon">🚗</span> <span>Drivers</span></a></li>
                <li><a href="/admin/rides.php"><span class="admin-nav-icon">🗺️</span> <span>Rides</span></a></li>
            </ul>
            <div class="admin-sidebar-footer">
                <a href="/handlers/logout.php"><span>🚪</span> <span>Logout</span></a>
            </div>
        </aside>

        <main class="admin-main">
            <div class="admin-header">
                <h2>Manage Drivers</h2>
                <p>Review and manage driver applications</p>
            </div>

            <div class="admin-tabs">
                <a href="?tab=pending" class="admin-tab <?php echo $tab === 'pending' ? 'active' : ''; ?>">Pending (<?php echo $counts['pending']; ?>)</a>
                <a href="?tab=approved" class="admin-tab <?php echo $tab === 'approved' ? 'active' : ''; ?>">Approved (<?php echo $counts['approved']; ?>)</a>
                <a href="?tab=suspended" class="admin-tab <?php echo $tab === 'suspended' ? 'active' : ''; ?>">Suspended (<?php echo $counts['suspended']; ?>)</a>
                <a href="?tab=all" class="admin-tab <?php echo $tab === 'all' ? 'active' : ''; ?>">All (<?php echo $counts['all']; ?>)</a>
            </div>

            <?php if (empty($drivers)): ?>
                <div class="admin-empty"><p>No drivers found</p></div>
            <?php else: ?>
                <?php foreach ($drivers as $d): ?>
                <div class="driver-detail-card" id="driver-<?php echo $d['dp_id']; ?>">
                    <div class="driver-detail-grid">
                        <div class="detail-field">
                            <label>Name</label>
                            <span><?php echo htmlspecialchars($d['first_name'] . ' ' . $d['last_name']); ?></span>
                        </div>
                        <div class="detail-field">
                            <label>Email</label>
                            <span><?php echo htmlspecialchars($d['email']); ?></span>
                        </div>
                        <div class="detail-field">
                            <label>Phone</label>
                            <span><?php echo htmlspecialchars($d['phone']); ?></span>
                        </div>
                        <div class="detail-field">
                            <label>Status</label>
                            <span><span class="badge badge-<?php echo $d['status']; ?>"><?php echo $d['status']; ?></span></span>
                        </div>
                        <div class="detail-field">
                            <label>License</label>
                            <span><?php echo htmlspecialchars($d['license_number']); ?> (exp: <?php echo $d['license_expiry']; ?>)</span>
                        </div>
                        <div class="detail-field">
                            <label>Rating</label>
                            <span>⭐ <?php echo number_format($d['rating_avg'], 1); ?> (<?php echo $d['total_rides']; ?> rides)</span>
                        </div>
                        <div class="detail-field">
                            <label>Vehicle</label>
                            <span><?php echo htmlspecialchars($d['color'] . ' ' . $d['year'] . ' ' . $d['make'] . ' ' . $d['model']); ?></span>
                        </div>
                        <div class="detail-field">
                            <label>Plate</label>
                            <span><?php echo htmlspecialchars($d['license_plate']); ?></span>
                        </div>
                        <div class="detail-field">
                            <label>Type</label>
                            <span><?php echo ucfirst($d['vehicle_type']); ?></span>
                        </div>
                        <div class="detail-field">
                            <label>Account</label>
                            <span><span class="badge badge-<?php echo $d['is_active'] ? 'active' : 'inactive'; ?>"><?php echo $d['is_active'] ? 'Active' : 'Inactive'; ?></span></span>
                        </div>
                    </div>
                    <div class="action-row">
                        <?php if ($d['status'] === 'pending'): ?>
                            <button class="btn-admin btn-admin-approve" onclick="driverAction(<?php echo $d['dp_id']; ?>, 'approve')">Approve</button>
                            <button class="btn-admin btn-admin-suspend" onclick="driverAction(<?php echo $d['dp_id']; ?>, 'suspend')">Reject</button>
                        <?php elseif ($d['status'] === 'approved'): ?>
                            <button class="btn-admin btn-admin-suspend" onclick="driverAction(<?php echo $d['dp_id']; ?>, 'suspend')">Suspend</button>
                        <?php elseif ($d['status'] === 'suspended'): ?>
                            <button class="btn-admin btn-admin-reactivate" onclick="driverAction(<?php echo $d['dp_id']; ?>, 'approve')">Reactivate</button>
                        <?php endif; ?>
                        <button class="btn-admin <?php echo $d['is_active'] ? 'btn-admin-suspend' : 'btn-admin-approve'; ?>" onclick="toggleUser(<?php echo $d['user_id']; ?>, this)">
                            <?php echo $d['is_active'] ? 'Disable Account' : 'Enable Account'; ?>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </main>
    </div>

    <script>
    function driverAction(dpId, action) {
        var data = new FormData();
        data.append('action', action);
        data.append('driver_id', dpId);
        fetch('/handlers/admin_action.php', { method: 'POST', body: data })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (res.ok) {
                    window.location.reload();
                } else {
                    alert(res.error || 'Action failed');
                }
            });
    }

    function toggleUser(userId, btn) {
        var data = new FormData();
        data.append('action', 'toggle_user');
        data.append('user_id', userId);
        btn.disabled = true;
        fetch('/handlers/admin_action.php', { method: 'POST', body: data })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (res.ok) {
                    window.location.reload();
                } else {
                    alert(res.error || 'Action failed');
                    btn.disabled = false;
                }
            });
    }
    </script>
</body>
</html>
