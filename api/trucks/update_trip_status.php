<?php

declare(strict_types=1);

require_once '../../includes/auth_check.php';
requireRoleAccess('driver');

require_once '../../config/db.php';
require_once '../../config/csrf.php';
require_once '../../classes/Notification.php';
require_once '../../classes/Mailer.php';
require_once '../../classes/Payment.php';
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

$trip_id = filter_input(INPUT_POST, 'trip_id', FILTER_VALIDATE_INT);
$status  = $_POST['status'] ?? null;

$allowedStatuses = ['arrived_at_pickup', 'in_transit', 'completed', 'report_stuck'];

if (!$trip_id || !$status || !in_array($status, $allowedStatuses, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid trip update request.']);
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
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Trip not found.']);
    exit;
}

if ($trip['status'] === 'cancelled') {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'The tenant cancelled this trip.']);
    exit;
}

if ($trip['status'] === 'completed') {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'This trip is already completed.']);
    exit;
}

/*
|--------------------------------------------------------------------------
| REPORT STUCK — raises an emergency alert, does NOT change the trip's
| status. Only from 'in_transit' — the same state the automatic silence
| watchdog (scripts/monitor_active_trips.php) targets, so admin always sees
| one consistent kind of alert whether the system or the driver raised it.
|--------------------------------------------------------------------------
*/
if ($status === 'report_stuck') {

    if ($trip['status'] !== 'in_transit') {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Only an in-transit trip can be reported.']);
        exit;
    }

    $pdo->prepare("
        INSERT INTO emergency_alerts (user_id, role, trip_id, message, status)
        VALUES (?, 'driver', ?, ?, 'active')
    ")->execute([
        $driver_id,
        $trip_id,
        'DRIVER REPORT: Unable to complete or update this trip normally (destination: ' . $trip['destination'] . '). Please review and resolve.',
    ]);

    error_log('LUX EMPIRE: driver #' . $driver_id . ' reported trip #' . $trip_id . ' as stuck.');

    echo json_encode([
        'success' => true,
        'message' => "Reported to our team — they'll follow up shortly. Your trip stays open until resolved.",
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| TRANSITION RULES — each status requires a specific prior status. The
| UPDATE below is itself conditioned on that exact prior status, so even
| two near-simultaneous requests for the same transition can't both
| succeed — the second one's rowCount() comes back 0 and is refused
| cleanly instead of double-processing anything.
|--------------------------------------------------------------------------
*/
$transitions = [
    'arrived_at_pickup' => 'accepted',
    'in_transit'         => 'arrived_at_pickup',
    'completed'          => 'in_transit',
];

$requiredPriorStatus = $transitions[$status];

if ($trip['status'] !== $requiredPriorStatus) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'This trip is not in the right state for that action.']);
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
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'This trip has an open emergency report and cannot be completed until our team clears it.']);
        exit;
    }
}

$update = $pdo->prepare("UPDATE truck_requests SET status = ? WHERE id = ? AND status = ?");
$update->execute([$status, $trip_id, $requiredPriorStatus]);

if ($update->rowCount() === 0) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'This trip was just updated elsewhere. Please refresh.']);
    exit;
}

// Permanent, append-only record — who changed it, when, from what IP.
$pdo->prepare("
    INSERT INTO trip_status_history (trip_id, status, changed_by, ip_address)
    VALUES (:trip_id, :status, :driver_id, :ip)
")->execute([
    ':trip_id' => $trip_id,
    ':status' => $status,
    ':driver_id' => $driver_id,
    ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
]);

if ($status === 'completed') {
    // Frees this driver to accept a new trip.
    $pdo->prepare("DELETE FROM driver_active_trip_lock WHERE trip_id = :trip_id")
        ->execute([':trip_id' => $trip_id]);
}

$notification = new Notification();
$message = 'Trip updated.';

if ($status === 'arrived_at_pickup') {

    $notification->create(
        (int) $trip['tenant_id'], 'driver_arrived', 'Driver Has Arrived',
        'Your driver has arrived at the pickup location.',
        BASE_URL . '/tenant/track-driver'
    );
    $message = 'Marked as arrived at pickup.';

} elseif ($status === 'in_transit') {

    $notification->create(
        (int) $trip['tenant_id'], 'trip_started', 'Trip Started',
        'Your move is now underway to your destination.',
        BASE_URL . '/tenant/track-driver'
    );

    if (!empty($trip['tenant_email'])) {
        $mailer = new Mailer();
        $mailer->send(
            $trip['tenant_email'], $trip['tenant_name'],
            'Your LUX EMPIRE move has started',
            '<p>Hello ' . htmlspecialchars($trip['tenant_name']) . ',</p>'
            . '<p>Your driver has started the trip toward your destination: '
            . htmlspecialchars($trip['destination']) . '.</p>'
            . '<p>You can follow live progress from your LUX EMPIRE dashboard.</p>'
        );
    }

    $message = 'Trip started successfully.';

} elseif ($status === 'completed') {

    /*
     * Commission is deducted EXACTLY ONCE here, gated by the conditional
     * UPDATE above (status = 'in_transit' -> 'completed'), which guarantees
     * this branch runs for a given trip at most once, ever.
     *
     * The PREVIOUS version of this file had a structural bug: the commission
     * deduction sat outside the if/elseif chain it was meant to belong to,
     * so it ran on EVERY status change — arrived_at_pickup, in_transit, AND
     * completed — overcharging every driver on every trip by up to 3x.
     * Fixed here: it now only runs inside this 'completed' branch.
     */
    $paymentModel = new Payment();
    $paymentModel->deductCommission($driver_id, (float) $trip['price'], $trip_id);
    $newWalletBalance = $paymentModel->getWalletBalance($driver_id);

    if ($newWalletBalance < 0) {

        $notification->create(
            $driver_id, 'wallet_negative', 'Wallet Balance Low',
            'Your commission wallet is now KES ' . number_format($newWalletBalance, 2) . ' after this trip. Top up when convenient to stay in good standing.',
            BASE_URL . '/driver/wallet'
        );

        $driverRow = $pdo->prepare("SELECT full_name, email FROM users WHERE id = ?");
        $driverRow->execute([$driver_id]);
        $driverInfo = $driverRow->fetch();

        if ($driverInfo) {
            EmailJobPublisher::publish('email.wallet_negative', [
                'email' => $driverInfo['email'],
                'name' => $driverInfo['full_name'],
                'balance' => number_format($newWalletBalance, 2),
            ]);
        }
    }

    $notification->create(
        (int) $trip['tenant_id'], 'trip_completed', 'Trip Completed',
        'Your driver has arrived and completed the move to ' . $trip['destination'] . '.',
        BASE_URL . '/tenant/my-bookings'
    );

    $message = 'Trip completed successfully.';
}

echo json_encode(['success' => true, 'message' => $message, 'status' => $status]);