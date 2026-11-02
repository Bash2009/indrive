<?php
$host = 'mysql';
$user = 'indrive';
$pass = 'indrivepass123';
$db   = 'indrive_db';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error . "\n");
}

$stmt = $conn->prepare('UPDATE driver_profiles SET status = ? WHERE status = ?');
$status = 'approved';
$pending = 'pending';
$stmt->bind_param('ss', $status, $pending);
$stmt->execute();
$affected = $stmt->affected_rows;
$stmt->close();
$conn->close();

echo "Approved {$affected} driver(s).\n";
