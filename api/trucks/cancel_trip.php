<?php

require_once '../../includes/auth_check.php';
requireRoleAccess('tenant');

require_once '../../config/db.php';

$db = new Database();
$pdo = $db->connect();

if ($_SERVER['REQUEST_METHOD'] !== 'POST')
{
    die("Invalid request.");
}

$tenant_id = $_SESSION['user_id'];

$trip_id = $_POST['trip_id'] ?? null;

if (!$trip_id)
{
    $_SESSION['error'] = "Invalid trip.";

    header("Location: ../../dashboard/tenant/track_driver.php");
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

if (!$trip)
{
    $_SESSION['error'] =
        "Trip not found.";

    header("Location: ../../dashboard/tenant/track_driver.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| BLOCK CANCELLATION AFTER START
|--------------------------------------------------------------------------
*/
if (
    $trip['status'] === 'in_transit'
    ||
    $trip['status'] === 'completed'
)
{
    $_SESSION['error'] =
        "Trip already started and cannot be cancelled.";

    header("Location: ../../dashboard/tenant/track_driver.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| CANCEL TRIP
|--------------------------------------------------------------------------
*/
$update = $pdo->prepare("
    UPDATE truck_requests
    SET status = 'cancelled'
    WHERE id = ?
");

$success = $update->execute([$trip_id]);

if ($success)
{
    $_SESSION['success'] =
        "Trip cancelled successfully.";
}
else
{
    $_SESSION['error'] =
        "Failed to cancel trip.";
}

header("Location: ../../dashboard/tenant/track_driver.php");
exit;