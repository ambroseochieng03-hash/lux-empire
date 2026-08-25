<?php

require_once '../../includes/auth_check.php';
requireRoleAccess('tenant');

require_once '../../config/db.php';

$db = new Database();
$pdo = $db->connect();

/*
|--------------------------------------------------------------------------
| ONLY POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    die("Invalid request.");
}

/*
|--------------------------------------------------------------------------
| GET DATA
|--------------------------------------------------------------------------
*/

$tenant_id = (int) Session::user()['id'];

$trip_id = $_POST['trip_id'] ?? null;

if (!$trip_id) {

    $_SESSION['error'] = "Invalid trip.";

    header("Location: ../../dashboard/tenant/my_bookings.php");

    exit;
}

/*
|--------------------------------------------------------------------------
| VERIFY OWNERSHIP
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM truck_requests
    WHERE id = ?
    AND tenant_id = ?
    LIMIT 1
");

$stmt->execute([
    $trip_id,
    $tenant_id
]);

$trip = $stmt->fetch();

if (!$trip) {

    $_SESSION['error'] = "Trip not found.";

    header("Location: ../../dashboard/tenant/my_bookings.php");

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
        "🗑 Truck request deleted successfully.";

} else {

    $_SESSION['error'] =
        "Failed to delete truck request.";
}

header("Location: ../../dashboard/tenant/my_bookings.php");

exit;