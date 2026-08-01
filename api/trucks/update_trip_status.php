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

$trip_id = $_POST['trip_id'] ?? null;
$status  = $_POST['status'] ?? null;

$allowedStatuses = [
    'in_transit',
    'completed'
];

if (
    !$trip_id ||
    !$status ||
    !in_array($status, $allowedStatuses)
) {

    $_SESSION['error'] =
        "Invalid trip update request.";

    header("Location: ../../dashboard/driver/active_trip.php");
    exit;
}

$stmt = $pdo->prepare("
    SELECT *
    FROM truck_requests
    WHERE id = ?
    AND driver_id = ?
    LIMIT 1
");

$stmt->execute([
    $trip_id,
    $driver_id
]);

$trip = $stmt->fetch();

if (!$trip) {

    $_SESSION['error'] =
        "Trip not found.";

    header("Location: ../../dashboard/driver/active_trip.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| BLOCK CANCELLED TRIPS
|--------------------------------------------------------------------------
*/

if ($trip['status'] === 'cancelled') {

    $_SESSION['error'] =
        "Tenant cancelled this trip.";

    header("Location: ../../dashboard/driver/active_trip.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| BLOCK COMPLETED TRIPS
|--------------------------------------------------------------------------
*/

if ($trip['status'] === 'completed') {

    $_SESSION['error'] =
        "Trip already completed.";

    header("Location: ../../dashboard/driver/active_trip.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| START TRIP RULE
|--------------------------------------------------------------------------
*/

if (
    $status === 'in_transit' &&
    $trip['status'] !== 'accepted'
) {

    $_SESSION['error'] =
        "Trip already started.";

    header("Location: ../../dashboard/driver/active_trip.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| COMPLETE TRIP RULE
|--------------------------------------------------------------------------
*/

if (
    $status === 'completed' &&
    $trip['status'] !== 'in_transit'
) {

    $_SESSION['error'] =
        "Trip must first be started.";

    header("Location: ../../dashboard/driver/active_trip.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| EMERGENCY CHECK ON COMPLETION
|--------------------------------------------------------------------------
*/

if ($status === 'completed') {

    $emergencyCheck = $pdo->prepare("
        SELECT id
        FROM emergency_alerts
        WHERE trip_id = ?
        AND status IN ('active', 'responding')
        LIMIT 1
    ");

    $emergencyCheck->execute([$trip_id]);

    $activeEmergency = $emergencyCheck->fetch();

    if ($activeEmergency) {

        $_SESSION['error'] =
            "🚨 Trip cannot be completed during an active emergency.";

        header("Location: ../../dashboard/driver/active_trip.php");
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| UPDATE STATUS
|--------------------------------------------------------------------------
*/

$update = $pdo->prepare("
    UPDATE truck_requests
    SET status = ?
    WHERE id = ?
");

$success = $update->execute([
    $status,
    $trip_id
]);

if ($success) {

    if ($status === 'in_transit') {

        $_SESSION['success'] =
            "🚚 Trip started successfully.";

    } elseif ($status === 'completed') {

        $_SESSION['success'] =
            "✅ Trip completed successfully.";
    }

} else {

    $_SESSION['error'] =
        "Failed to update trip.";
}

header("Location: ../../dashboard/driver/active_trip.php");
exit;