<?php

require_once '../../includes/auth_check.php';
requireLogin();

require_once '../../config/db.php';

$db = new Database();
$pdo = $db->connect();

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

$message = $_POST['message'] ?? 'Emergency alert';

/*
|--------------------------------------------------------------------------
| TRY TO FIND ACTIVE CONTEXT
|--------------------------------------------------------------------------
*/

// Active trip (if driver or tenant in logistics)
$tripStmt = $pdo->prepare("
    SELECT id
    FROM truck_requests
    WHERE (tenant_id = ? OR driver_id = ?)
    AND status IN ('accepted', 'in_transit')
    LIMIT 1
");

$tripStmt->execute([$user_id, $user_id]);
$trip = $tripStmt->fetch();

$trip_id = $trip['id'] ?? null;

/*
|--------------------------------------------------------------------------
| INSERT EMERGENCY ALERT
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    INSERT INTO emergency_alerts
    (user_id, role, trip_id, message)
    VALUES (?, ?, ?, ?)
");

$stmt->execute([
    $user_id,
    $role,
    $trip_id,
    $message
]);

header("Location: ../../dashboard/" . $role . "/dashboard.php?emergency=sent");
exit();