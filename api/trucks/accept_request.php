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

$request = null;

try {
    $pdo->beginTransaction();

    // Lock the trip row itself first, so two concurrent accept attempts
    // on the SAME trip can't both pass this check.
    $stmt = $pdo->prepare("SELECT * FROM truck_requests WHERE id = ? AND status = 'pending' FOR UPDATE");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'This request is no longer available.']);
        exit;
    }

    if ($request['trip_type'] === 'scheduled' && $request['scheduled_at'] !== null) {
        $scheduledAtTimestamp = strtotime($request['scheduled_at']);
        $windowOpensAt = $scheduledAtTimestamp - (TRUCK_ACCEPT_WINDOW_MINUTES * 60);

        if (time() < $windowOpensAt) {
            $pdo->rollBack();
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => "This scheduled move can't be accepted yet."]);
            exit;
        }
    }

    /*
     * ONE ACTIVE TRIP PER DRIVER — enforced by driver_active_trip_lock,
     * a table where driver_id is the PRIMARY KEY. A driver can hold at
     * most one row here, ever: the database itself rejects a second
     * INSERT for the same driver_id, which is what makes it impossible
     * for the same driver to end up with two active trips even from
     * two devices clicking two different pending requests in the same
     * millisecond. The row is removed only when the trip reaches
     * 'completed' or 'cancelled' — see update_trip_status.php and
     * classes/AdminTruckService.php.
     */
    try {
        $lock = $pdo->prepare("INSERT INTO driver_active_trip_lock (driver_id, trip_id) VALUES (?, ?)");
        $lock->execute([$driver_id, $request_id]);
    } catch (PDOException $e) {
        if ((string) $e->getCode() === '23000') {
            $pdo->rollBack();
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'You already have an active trip. Finish it before accepting another.']);
            exit;
        }
        throw $e;
    }

    $update = $pdo->prepare("
        UPDATE truck_requests
        SET driver_id = ?, status = 'accepted'
        WHERE id = ? AND status = 'pending'
    ");
    $update->execute([$driver_id, $request_id]);

    if ($update->rowCount() === 0) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'This request was just accepted by another driver.']);
        exit;
    }

    $pdo->prepare("
        INSERT INTO trip_status_history (trip_id, status, changed_by, ip_address)
        VALUES (:trip_id, 'accepted', :driver_id, :ip)
    ")->execute([
        ':trip_id' => $request_id,
        ':driver_id' => $driver_id,
        ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
    ]);

    $pdo->commit();

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('LUX EMPIRE accept_request DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Something went wrong. Please try again.']);
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