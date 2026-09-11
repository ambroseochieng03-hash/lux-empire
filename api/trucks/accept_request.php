<?php

declare(strict_types=1);

require_once '../../includes/auth_check.php';
requireRoleAccess('driver');

require_once '../../config/db.php';
require_once '../../config/csrf.php';
require_once '../../classes/Notification.php';
require_once '../../classes/EmailJobPublisher.php';

header('Content-Type: application/json');

$db = new Database();
$pdo = $db->connect();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

Csrf::requireValid($_POST['csrf_token'] ?? null);

$driver_id = (int) Session::user()['id'];
$driver_name = Session::user()['full_name'] ?? 'Your driver';
$request_id = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);

if (!$request_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request ID.']);
    exit;
}

$stmt = $pdo->prepare("
    SELECT * FROM truck_requests
    WHERE id = ? AND status = 'pending'
    LIMIT 1
");
$stmt->execute([$request_id]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'This request is no longer available.']);
    exit;
}

/*
 * SCHEDULED TRIP GATE — the real security boundary, not just what
 * the page's button shows. Enforced here regardless of what any
 * client sends.
 */
if ($request['trip_type'] === 'scheduled' && $request['scheduled_at'] !== null) {

    $scheduledAtTimestamp = strtotime($request['scheduled_at']);
    $windowOpensAt = $scheduledAtTimestamp - (TRUCK_ACCEPT_WINDOW_MINUTES * 60);

    if (time() < $windowOpensAt) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => "This scheduled move can't be accepted yet."]);
        exit;
    }
}

$update = $pdo->prepare("
    UPDATE truck_requests
    SET driver_id = ?, status = 'accepted'
    WHERE id = ? AND status = 'pending'
");
$update->execute([$driver_id, $request_id]);

$success = $update->rowCount() > 0;

if (!$success) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'This request was just accepted by another driver.']);
    exit;
}

$notification = new Notification();
$notification->create(
    (int) $request['tenant_id'],
    'driver_assigned',
    'Driver Assigned',
    $driver_name . ' has accepted your move request and is heading to your pickup location.',
    BASE_URL . '/tenant/track-driver'
);

$tenantLookup = $pdo->prepare("SELECT full_name, email FROM users WHERE id = ?");
$tenantLookup->execute([$request['tenant_id']]);
$tenantRow = $tenantLookup->fetch();

if ($tenantRow) {
    EmailJobPublisher::publish('email.truck_request_accepted', [
        'email' => $tenantRow['email'],
        'name' => $tenantRow['full_name'],
        'driver_name' => $driver_name,
    ]);
}

echo json_encode([
    'success' => true,
    'message' => 'Request accepted successfully.',
    'redirect' => BASE_URL . '/driver/active-trip'
]);