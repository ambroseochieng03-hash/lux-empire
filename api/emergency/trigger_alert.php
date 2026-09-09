<?php

declare(strict_types=1);

require_once '../../includes/auth_check.php';
require_once '../../config/db.php';
require_once '../../config/csrf.php';
require_once '../../config/security/RateLimiter.php';
require_once '../../config/security/Audit.php';
require_once '../../classes/Notification.php';
require_once '../../classes/EmailJobPublisher.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

Csrf::requireValid($_POST['csrf_token'] ?? null);

$user = Session::user();
$user_id = (int) $user['id'];
$role = $user['role'] ?? 'tenant';
$name = $user['full_name'] ?? 'Empire Member';

/**
 * Throttle: 5 alerts per 5 minutes per user, then a 10 minute
 * cool-down. Prevents accidental/malicious spam without getting in
 * the way of a genuine repeat report.
 */
$rateKey = 'emergency_alert:' . $user_id;

if (RateLimiter::isBlocked($rateKey)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many alerts sent. Please wait a few minutes.']);
    exit;
}

$attempts = RateLimiter::hit($rateKey, 300);

if ($attempts > 5) {
    RateLimiter::block($rateKey, 600);
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many alerts sent. Please wait a few minutes.']);
    exit;
}

$message = trim((string) ($_POST['message'] ?? ''));

if ($message === '') {
    $message = 'Emergency alert';
}

if (mb_strlen($message) > 1000) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Message is too long.']);
    exit;
}

$db = new Database();
$pdo = $db->connect();

/*
|--------------------------------------------------------------------------
| TRY TO FIND ACTIVE CONTEXT
|--------------------------------------------------------------------------
*/

$tripStmt = $pdo->prepare("
    SELECT id
    FROM truck_requests
    WHERE (tenant_id = ? OR driver_id = ?)
    AND status IN ('accepted', 'in_transit')
    LIMIT 1
");

$tripStmt->execute([$user_id, $user_id]);
$trip = $tripStmt->fetch();

$trip_id = $trip['id'] ?? null;

/*
|--------------------------------------------------------------------------
| INSERT EMERGENCY ALERT
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    INSERT INTO emergency_alerts
    (user_id, role, trip_id, message)
    VALUES (?, ?, ?, ?)
");

$stmt->execute([
    $user_id,
    $role,
    $trip_id,
    $message
]);

$alertId = (int) $pdo->lastInsertId();

Audit::log("User #{$user_id} ({$role}) triggered emergency alert #{$alertId}", $user_id);

/*
|--------------------------------------------------------------------------
| EMAIL + NOTIFICATION (via NATS — never blocks this response)
|--------------------------------------------------------------------------
*/

EmailJobPublisher::publish('email.emergency_acknowledged', [
    'email' => $user['email'] ?? '',
    'name' => $name,
    'role' => $role,
    'message' => $message,
]);

$notification = new Notification();
$notification->create(
    $user_id,
    'emergency_alert_received',
    'Emergency Alert Received',
    'Your emergency alert has been received and is being reviewed by our safety team. We will follow up shortly.',
    BASE_URL . '/' . $role . '/notifications'
);

echo json_encode([
    'success' => true,
    'message' => 'Your alert has been received. Our safety team has been notified and you will get an email confirmation shortly.'
]);