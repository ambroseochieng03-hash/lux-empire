<?php

require_once '../../includes/auth_check.php';
requireRoleAccess('driver');

require_once '../../config/db.php';
require_once '../../classes/Notification.php';

$db = new Database();
$pdo = $db->connect();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid request.");
}

$driver_id = (int) Session::user()['id'];
$driver_name = Session::user()['full_name'] ?? 'Your driver';
$request_id = $_POST['request_id'] ?? null;

if (!$request_id) {
    $_SESSION['error'] = "Invalid request ID.";
    header("Location: ../../dashboard/driver/available_requests.php");
    exit;
}

$stmt = $pdo->prepare("
    SELECT * FROM truck_requests
    WHERE id = ? AND status = 'pending'
    LIMIT 1
");
$stmt->execute([$request_id]);
$request = $stmt->fetch();

if (!$request) {
    $_SESSION['error'] = "This request is no longer available.";
    header("Location: ../../dashboard/driver/available_requests.php");
    exit;
}

$update = $pdo->prepare("
    UPDATE truck_requests
    SET driver_id = ?, status = 'accepted'
    WHERE id = ? AND status = 'pending'
");
$update->execute([$driver_id, $request_id]);

// rowCount() is the real signal here — execute() returning true just
// means the statement ran, not that a row actually matched. With the
// status='pending' guard added above, a second driver's UPDATE now
// matches zero rows instead of overwriting the first driver's claim.
$success = $update->rowCount() > 0;

if (!$success) {
    $_SESSION['error'] = "This request was just accepted by another driver.";
    header("Location: " . BASE_URL . "/dashboard/driver/available_requests.php");
    exit;
}

if ($success) {

    $notification = new Notification();
    $notification->create(
        (int) $request['tenant_id'],
        'driver_assigned',
        'Driver Assigned',
        $driver_name . ' has accepted your move request and is heading to your pickup location.',
        BASE_URL . '/tenant/track-driver'
    );

    $_SESSION['success'] = "Request accepted successfully.";
    header("Location: " . BASE_URL . "/dashboard/driver/active_trip.php");
    exit;

} else {

    $_SESSION['error'] = "Failed to accept request.";
    header("Location: " . BASE_URL . "/dashboard/driver/available_requests.php");
    exit;
}