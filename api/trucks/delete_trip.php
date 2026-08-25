<?php

require_once '../../includes/auth_check.php';
requireRoleAccess('driver');

require_once '../../config/db.php';

$db = new Database();
$pdo = $db->connect();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid request.");
}

$driver_id = (int) Session::user()['id'];
$trip_id   = $_POST['trip_id'] ?? null;

if (!$trip_id) {

    $_SESSION['error'] =
        "Invalid trip.";

    header("Location: ../../dashboard/driver/active_trip.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| VERIFY TRIP
|--------------------------------------------------------------------------
*/

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
| ONLY CANCELLED TRIPS CAN BE DELETED
|--------------------------------------------------------------------------
*/

if ($trip['status'] !== 'cancelled') {

    $_SESSION['error'] =
        "Only cancelled trips can be deleted.";

    header("Location: ../../dashboard/driver/active_trip.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| DELETE TRIP
|--------------------------------------------------------------------------
*/

$delete = $pdo->prepare("
    DELETE FROM truck_requests
    WHERE id = ?
");

$success = $delete->execute([
    $trip_id
]);

if ($success) {

    $_SESSION['success'] =
        "Cancelled trip removed.";

} else {

    $_SESSION['error'] =
        "Failed to delete trip.";
}

header("Location: ../../dashboard/driver/active_trip.php");
exit;