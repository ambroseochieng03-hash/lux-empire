<?php

require_once '../../includes/auth_check.php';
require_once '../../config/db.php';
require_once '../../classes/TruckRequest.php';

// Only tenants can request trucks
requireRoleAccess('tenant');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid request method.");
}

// Get tenant ID from session
$tenant_id = $_SESSION['user_id'];

// Collect form data
$pickup_location   = trim($_POST['pickup_location']);
$destination       = trim($_POST['destination']);
$price             = trim($_POST['price']);

// Optional GPS fields (later maps feature)
$pickup_lat = !empty($_POST['pickup_lat'])
    ? $_POST['pickup_lat']
    : null;

$pickup_lng = !empty($_POST['pickup_lng'])
    ? $_POST['pickup_lng']
    : null;

$destination_lat = !empty($_POST['destination_lat'])
    ? $_POST['destination_lat']
    : null;

$destination_lng = !empty($_POST['destination_lng'])
    ? $_POST['destination_lng']
    : null;

// Basic validation
if (empty($pickup_location) || empty($destination)) {
    $_SESSION['error'] = "Pickup and destination are required.";
    header("Location: ../../dashboard/tenant/request_truck.php");
    exit;
}

// Create object
$truck = new TruckRequest();

$data = [
    'tenant_id' => $tenant_id,
    'pickup_location' => $pickup_location,
    'destination' => $destination,
    'pickup_lat' => $pickup_lat,
    'pickup_lng' => $pickup_lng,
    'destination_lat' => $destination_lat,
    'destination_lng' => $destination_lng,
    'price' => $price
];

// Insert request
$result = $truck->createRequest($data);

if ($result) {
    $_SESSION['success'] = "🚚 Truck request submitted successfully!";
} else {
    $_SESSION['error'] = "Failed to submit request. Try again.";
}

// Redirect back
header("Location: ../../dashboard/tenant/request_truck.php");
exit;