<?php

require_once '../../includes/auth_check.php';
requireRoleAccess('tenant');

require_once '../../config/db.php';
require_once '../../classes/TruckRequest.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid request method.");
}

$tenant_id = (int) Session::user()['id'];

$pickup_location = trim($_POST['pickup_location'] ?? '');
$destination     = trim($_POST['destination'] ?? '');
$price           = trim($_POST['price'] ?? '');

$pickup_lat = !empty($_POST['pickup_lat']) ? $_POST['pickup_lat'] : null;
$pickup_lng = !empty($_POST['pickup_lng']) ? $_POST['pickup_lng'] : null;
$destination_lat = !empty($_POST['destination_lat']) ? $_POST['destination_lat'] : null;
$destination_lng = !empty($_POST['destination_lng']) ? $_POST['destination_lng'] : null;

if (empty($pickup_location) || empty($destination)) {
    $_SESSION['error'] = "Pickup and destination are required.";
    header("Location: ../../dashboard/tenant/request_truck.php");
    exit;
}

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

$result = $truck->createRequest($data);

if ($result) {
    $_SESSION['success'] = "Truck request submitted successfully!";
} else {
    $_SESSION['error'] = "Failed to submit request. Try again.";
}

header("Location: ../../dashboard/tenant/request_truck.php");
exit;