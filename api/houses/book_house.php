<?php
require_once '../../config/session.php';

require_once '../../classes/House.php';
require_once '../../classes/Booking.php';

/**
 * Must be logged in
 */
requireLogin();

/**
 * Tenants only
 */
if ($_SESSION['role'] !== 'tenant') {

    header("Location: ../../index.php?error=Unauthorized access");
    exit();
}

/**
 * Validate house ID
 */
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {

    header("Location: ../../dashboard/tenant/search_houses.php?error=Invalid property");
    exit();
}

$house_id = (int) $_GET['id'];

$tenant_id = $_SESSION['user_id'];

/**
 * Get house
 */
$houseModel = new House();

$house = $houseModel->getHouseById($house_id);

if (!$house) {

    header("Location: ../../dashboard/tenant/search_houses.php?error=Property not found");
    exit();
}

/**
 * Prevent landlord booking own house
 */
if ($house['landlord_id'] == $tenant_id) {

    header("Location: ../../dashboard/tenant/search_houses.php?error=You cannot book your own property");
    exit();
}

/**
 * Create booking
 */
$bookingModel = new Booking();

$result = $bookingModel->createBooking(
    $tenant_id,
    $house_id,
    $house['landlord_id']
);

/**
 * Handle response
 */
if ($result === true) {

    header("Location: ../../dashboard/tenant/my_bookings.php?success=Luxury property booking submitted successfully");
    exit();

} else {

    header("Location: ../../dashboard/tenant/search_houses.php?error=" . urlencode($result));
    exit();
}
?>