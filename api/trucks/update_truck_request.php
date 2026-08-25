<?php

require_once '../../includes/auth_check.php';
requireRoleAccess('tenant');

require_once '../../config/db.php';

$db = new Database();
$pdo = $db->connect();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    die("Invalid request.");
}

$request_id = $_POST['request_id'] ?? null;

$pickup_location = trim(
    $_POST['pickup_location'] ?? ''
);

$destination = trim(
    $_POST['destination'] ?? ''
);

$moving_date = trim(
    $_POST['moving_date'] ?? ''
);

$notes = trim(
    $_POST['notes'] ?? ''
);

if (
    !$request_id ||
    empty($pickup_location) ||
    empty($destination) ||
    empty($moving_date)
) {

    die("All required fields must be filled.");
}

/*
|--------------------------------------------------------------------------
| VERIFY OWNERSHIP
|--------------------------------------------------------------------------
*/

$check = $pdo->prepare("
    SELECT *
    FROM truck_requests
    WHERE id = ?
    AND tenant_id = ?
    LIMIT 1
");

$check->execute([
    $request_id,
    (int) Session::user()['id']
]);

$request = $check->fetch();

if (!$request) {

    die("Truck request not found.");
}

/*
|--------------------------------------------------------------------------
| BLOCK ACTIVE TRIPS
|--------------------------------------------------------------------------
*/

if (
    in_array(
        $request['status'],
        ['accepted', 'in_transit', 'completed']
    )
) {

    die("This request can no longer be edited.");
}

/*
|--------------------------------------------------------------------------
| UPDATE REQUEST
|--------------------------------------------------------------------------
*/

$update = $pdo->prepare("
    UPDATE truck_requests
    SET
        pickup_location = ?,
        destination = ?,
        moving_date = ?,
        notes = ?
    WHERE id = ?
");

$success = $update->execute([
    $pickup_location,
    $destination,
    $moving_date,
    $notes,
    $request_id
]);

if ($success) {

    header(
        "Location: ../../dashboard/tenant/my_bookings.php?success=Request updated successfully"
    );

    exit();
}

die("Failed to update request.");
?>