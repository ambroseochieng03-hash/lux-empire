<?php

require_once '../../config/db.php';
require_once '../../includes/auth_check.php';

requireRoleAccess('landlord');

$db = new Database();
$pdo = $db->connect();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    die("Invalid request.");
}

$booking_id = $_GET['id'] ?? null;
$status     = $_GET['status'] ?? null;

$allowedStatuses = ['approved', 'rejected'];

if (
    !$booking_id ||
    !$status ||
    !in_array($status, $allowedStatuses)
) {

    $_SESSION['error'] = "Invalid booking request.";

    header("Location: ../../dashboard/landlord/booking_requests.php");
    exit;
}

$landlord_id = (int) Session::user()['id'];

$stmt = $pdo->prepare("
    SELECT *
    FROM bookings
    WHERE id = ?
    AND landlord_id = ?
    LIMIT 1
");

$stmt->execute([
    $booking_id,
    $landlord_id
]);

$booking = $stmt->fetch();

if (!$booking) {

    $_SESSION['error'] =
        "Booking not found or unauthorized.";

    header("Location: ../../dashboard/landlord/booking_requests.php");
    exit;
}

$update = $pdo->prepare("
    UPDATE bookings
    SET status = ?
    WHERE id = ?
");

$success = $update->execute([
    $status,
    $booking_id
]);

if ($success) {

    $_SESSION['success'] =
        "Booking status updated successfully.";

} else {

    $_SESSION['error'] =
        "Failed to update booking.";
}

header("Location: ../../dashboard/landlord/booking_requests.php");
exit;