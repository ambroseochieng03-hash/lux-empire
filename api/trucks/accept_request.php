<?php

require_once '../../includes/auth_check.php';
requireRoleAccess('driver');

require_once '../../config/db.php';

$db = new Database();
$pdo = $db->connect();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid request.");
}

$driver_id = $_SESSION['user_id'];
$request_id = $_POST['request_id'] ?? null;

if (!$request_id) {
    $_SESSION['error'] = "Invalid request ID.";
    header("Location: ../../dashboard/driver/available_requests.php");
    exit;
}

// Check if request still pending
$stmt = $pdo->prepare("
    SELECT *
    FROM truck_requests
    WHERE id = ?
    AND status = 'pending'
    LIMIT 1
");

$stmt->execute([$request_id]);

$request = $stmt->fetch();

if (!$request) {

    $_SESSION['error'] =
        "This request is no longer available.";

    header("Location: ../../dashboard/driver/available_requests.php");
    exit;
}

// Assign driver + update status
$update = $pdo->prepare("
    UPDATE truck_requests
    SET
        driver_id = ?,
        status = 'accepted'
    WHERE id = ?
");

$success = $update->execute([
    $driver_id,
    $request_id
]);

if ($success) {

    $_SESSION['success'] =
        "🚚 Request accepted successfully.";

    header("Location: ../../dashboard/driver/active_trip.php");
    exit;

} else {

    $_SESSION['error'] =
        "Failed to accept request.";

    header("Location: ../../dashboard/driver/available_requests.php");
    exit;
}