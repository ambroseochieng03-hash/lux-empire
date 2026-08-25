<?php

require_once '../../includes/auth_check.php';
requireRoleAccess('driver');

require_once '../../config/db.php';
require_once '../../classes/Notification.php';
require_once '../../classes/Mailer.php';

$db = new Database();
$pdo = $db->connect();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid request.");
}

$driver_id = (int) Session::user()['id'];

$trip_id = $_POST['trip_id'] ?? null;
$status  = $_POST['status'] ?? null;

$allowedStatuses = ['arrived_at_pickup', 'in_transit', 'completed'];

if (!$trip_id || !$status || !in_array($status, $allowedStatuses)) {
    $_SESSION['error'] = "Invalid trip update request.";
    header("Location: ../../dashboard/driver/active_trip.php");
    exit;
}

$stmt = $pdo->prepare("
    SELECT tr.*, u.full_name AS tenant_name, u.email AS tenant_email
    FROM truck_requests tr
    JOIN users u ON tr.tenant_id = u.id
    WHERE tr.id = ? AND tr.driver_id = ?
    LIMIT 1
");
$stmt->execute([$trip_id, $driver_id]);
$trip = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$trip) {
    $_SESSION['error'] = "Trip not found.";
    header("Location: ../../dashboard/driver/active_trip.php");
    exit;
}

if ($trip['status'] === 'cancelled') {
    $_SESSION['error'] = "Tenant cancelled this trip.";
    header("Location: ../../dashboard/driver/active_trip.php");
    exit;
}

if ($trip['status'] === 'completed') {
    $_SESSION['error'] = "Trip already completed.";
    header("Location: ../../dashboard/driver/active_trip.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| TRANSITION RULES
|
| accepted -> arrived_at_pickup -> in_transit -> completed
|--------------------------------------------------------------------------
*/

if ($status === 'arrived_at_pickup' && $trip['status'] !== 'accepted') {
    $_SESSION['error'] = "This trip has already moved past acceptance.";
    header("Location: ../../dashboard/driver/active_trip.php");
    exit;
}

if ($status === 'in_transit' && $trip['status'] !== 'arrived_at_pickup') {
    $_SESSION['error'] = "You must confirm arrival at pickup before starting the trip.";
    header("Location: ../../dashboard/driver/active_trip.php");
    exit;
}

if ($status === 'completed' && $trip['status'] !== 'in_transit') {
    $_SESSION['error'] = "Trip must first be started.";
    header("Location: ../../dashboard/driver/active_trip.php");
    exit;
}

if ($status === 'completed') {
    $emergencyCheck = $pdo->prepare("
        SELECT id FROM emergency_alerts
        WHERE trip_id = ? AND status IN ('active', 'responding')
        LIMIT 1
    ");
    $emergencyCheck->execute([$trip_id]);

    if ($emergencyCheck->fetch()) {
        $_SESSION['error'] = "Trip cannot be completed during an active emergency.";
        header("Location: ../../dashboard/driver/active_trip.php");
        exit;
    }
}

$update = $pdo->prepare("UPDATE truck_requests SET status = ? WHERE id = ?");
$success = $update->execute([$status, $trip_id]);

if ($success) {

    $notification = new Notification();

    if ($status === 'arrived_at_pickup') {

        $notification->create(
            (int) $trip['tenant_id'],
            'driver_arrived',
            'Driver Has Arrived',
            'Your driver has arrived at the pickup location.',
            BASE_URL . '/tenant/track-driver'
        );

        $_SESSION['success'] = "Marked as arrived at pickup.";

    } elseif ($status === 'in_transit') {

        $notification->create(
            (int) $trip['tenant_id'],
            'trip_started',
            'Trip Started',
            'Your move is now underway to your destination.',
            BASE_URL . '/tenant/track-driver'
        );

        if (!empty($trip['tenant_email'])) {
            $mailer = new Mailer();
            $mailer->send(
                $trip['tenant_email'],
                $trip['tenant_name'],
                'Your LUX EMPIRE move has started',
                '<p>Hello ' . htmlspecialchars($trip['tenant_name']) . ',</p>'
                . '<p>Your driver has started the trip toward your destination: '
                . htmlspecialchars($trip['destination']) . '.</p>'
                . '<p>You can follow live progress from your LUX EMPIRE dashboard.</p>'
            );
        }

        $_SESSION['success'] = "Trip started successfully.";

    } elseif ($status === 'completed') {

        $_SESSION['success'] = "Trip completed successfully.";
    }

} else {
    $_SESSION['error'] = "Failed to update trip.";
}

header("Location: ../../dashboard/driver/active_trip.php");
exit;