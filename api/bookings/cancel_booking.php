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
    $booking['tenant_id'] != (int) Session::user()['id']
) {

$_SESSION['error'] =
"Unauthorized booking.";

header("Location: ../../dashboard/tenant/my_bookings.php");
exit;
}

/*
|--------------------------------------------------------------------------
| ONLY A PENDING BOOKING CAN BE CANCELLED
|--------------------------------------------------------------------------
*/

if ($booking['status'] !== 'pending') {

$_SESSION['error'] =
"Only pending bookings can be cancelled.";

header("Location: ../../dashboard/tenant/my_bookings.php");
exit;
}

/*
|--------------------------------------------------------------------------
| CANCEL BOOKING
|--------------------------------------------------------------------------
*/

$cancelled = $bookingModel->tenantUpdateStatus(
    $booking_id,
    'cancelled'
);

if ($cancelled) {

$_SESSION['success'] =
"Booking cancelled successfully.";

} else {

$_SESSION['error'] =
"Failed to cancel booking.";
}

header("Location: ../../dashboard/tenant/my_bookings.php");
exit;
