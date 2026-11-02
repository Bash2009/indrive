<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
require_login();

$user_id = $_SESSION['user_id'];
$name = $_SESSION['user_name'] ?? 'Driver';

$stmt = $conn->prepare('SELECT status FROM driver_profiles WHERE user_id = ?');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$driver = $stmt->get_result()->fetch_assoc();
$stmt->close();

$status = $driver['status'] ?? 'pending';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Indrive — Application Status</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400&family=Inter:wght@400;500&family=Space+Grotesk:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/bootstrap.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body style="display:flex; align-items:center; justify-content:center; min-height:100vh; padding:2rem;">

    <div style="text-align:center; max-width:400px; width:100%;">
        <a href="/dashboard.php" class="auth-logo" style="display:block; margin-bottom:2rem;">in<span>d</span>rive</a>

        <div style="background:var(--id-surface); border:1px solid var(--id-border); border-radius:12px; padding:2rem;">
            <?php if ($status === 'pending'): ?>
                <div style="font-size:2.5rem; margin-bottom:1rem;">⏳</div>
                <h2 style="font-family:var(--id-font-display); font-weight:700; font-size:1.25rem; color:var(--id-text); margin:0 0 0.5rem;">Application Under Review</h2>
                <p style="font-size:0.875rem; color:var(--id-text-muted); margin:0 0 1.5rem; line-height:1.6;">
                    Hi <?php echo htmlspecialchars($name); ?>, your driver application is being reviewed. We'll notify you once it's approved.
                </p>
            <?php elseif ($status === 'rejected'): ?>
                <div style="font-size:2.5rem; margin-bottom:1rem;">✕</div>
                <h2 style="font-family:var(--id-font-display); font-weight:700; font-size:1.25rem; color:var(--id-text); margin:0 0 0.5rem;">Application Not Approved</h2>
                <p style="font-size:0.875rem; color:var(--id-text-muted); margin:0 0 1.5rem; line-height:1.6;">
                    Unfortunately, your driver application was not approved at this time.
                </p>
            <?php else: ?>
                <div style="font-size:2.5rem; margin-bottom:1rem;">🚗</div>
                <h2 style="font-family:var(--id-font-display); font-weight:700; font-size:1.25rem; color:var(--id-text); margin:0 0 0.5rem;">Complete Your Profile</h2>
                <p style="font-size:0.875rem; color:var(--id-text-muted); margin:0 0 1.5rem; line-height:1.6;">
                    Please complete your driver profile to get started.
                </p>
            <?php endif; ?>

            <a href="/handlers/logout.php" style="display:inline-block; padding:0.65rem 1.5rem; font-family:var(--id-font-body); font-size:0.875rem; font-weight:500; color:var(--id-text-muted); background:transparent; border:1px solid var(--id-border); border-radius:8px; text-decoration:none; transition:border-color 0.2s, color 0.2s;">Sign out</a>
        </div>
    </div>

</body>
</html>
