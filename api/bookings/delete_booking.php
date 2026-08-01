<?php

require_once '../../includes/auth_check.php';
requireRoleAccess('tenant');

require_once '../../classes/Booking.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid request.");
}

$booking_id = $_POST['booking_id'] ?? null;

if (!$booking_id) {

    $_SESSION['error'] =
        "Invalid booking.";

    header("Location: ../../dashboard/tenant/my_bookings.php");
    exit;
}

$bookingModel = new Booking();

/*
|--------------------------------------------------------------------------
| VERIFY BOOKING
|--------------------------------------------------------------------------
*/

$booking = $bookingModel->getBookingById($booking_id);

if (
    !$booking ||
    $booking['tenant_id'] != $_SESSION['user_id']
) {

    $_SESSION['error'] =
        "Unauthorized booking.";

    header("Location: ../../dashboard/tenant/my_bookings.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| DELETE BOOKING
|--------------------------------------------------------------------------
*/

$deleted = $bookingModel->deleteBooking(
    $booking_id,
    $_SESSION['user_id']
);

if ($deleted) {

    $_SESSION['success'] =
        "🗑 Booking deleted successfully.";

} else {

    $_SESSION['error'] =
        "Failed to delete booking.";
}

header("Location: ../../dashboard/tenant/my_bookings.php");
exit;