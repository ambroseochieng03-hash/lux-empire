<?php
declare(strict_types=1);

require_once '../../includes/init.php';
require_once '../../config/session.php';
require_once '../../config/csrf.php';
require_once '../../classes/Chat.php';
require_once '../../classes/House.php';
require_once '../../classes/Booking.php';
require_once '../../config/db.php';
require_once '../../config/security/DoSProtection.php';

Session::start();
header('Content-Type: application/json');

if (!Session::isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated.']);
    exit;
}

Csrf::requireValid($_POST['csrf_token'] ?? null);

$user = Session::user();
DoSProtection::check((int) $user['id']);

$role = $user['role'] ?? '';
$chat = new Chat();
$bookingModel = new Booking();

$houseId = isset($_POST['house_id']) && $_POST['house_id'] !== '' ? (int) $_POST['house_id'] : null;
$truckRequestId = isset($_POST['truck_request_id']) && $_POST['truck_request_id'] !== '' ? (int) $_POST['truck_request_id'] : null;

if ($houseId !== null && $houseId <= 0) {
    $houseId = null;
}

if ($role === 'tenant') {

    $tenantId = (int) $user['id'];
    $otherUserId = (int) ($_POST['other_user_id'] ?? 0);
    $otherRole = $_POST['other_role'] ?? '';

    if ($otherUserId <= 0 || !in_array($otherRole, ['landlord', 'driver'], true)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid recipient.']);
        exit;
    }

    // Tenant -> Landlord: the house must actually belong to that landlord.
    if ($houseId !== null) {
        $houseModel = new House();
        if (!$houseModel->belongsToLandlord($houseId, $otherUserId)) {
            http_response_code(403);
            echo json_encode(['error' => 'This property does not belong to that landlord.']);
            exit;
        }
    }

    // Tenant -> Landlord: in-app chat is unlocked by a PAID, still-live
    // booking with THAT landlord. Hiding the button is not enough — this is
    // the real gate, so nobody can start a conversation by posting here directly.
    if ($otherRole === 'landlord' && !$bookingModel->hasLiveBookingWithLandlord($tenantId, $otherUserId)) {
        http_response_code(403);
        echo json_encode([
            'error' => 'Messaging opens once you have paid the booking fee for one of this landlord\'s properties. If the landlord declines, your fee is refunded.'
        ]);
        exit;
    }

    // Tenant -> Driver: only allowed once that driver is the one assigned
    // to an accepted trip (not while it's still pending).
    if ($otherRole === 'driver' && $truckRequestId !== null) {
        $database = new Database();
        $pdo = $database->connect();

        $stmt = $pdo->prepare("SELECT tenant_id, driver_id, status FROM truck_requests WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $truckRequestId]);
        $trip = $stmt->fetch(PDO::FETCH_ASSOC);

        if (
            !$trip
            || (int) $trip['tenant_id'] !== $tenantId
            || (int) $trip['driver_id'] !== $otherUserId
            || !in_array($trip['status'], ['accepted', 'in_transit', 'completed'], true)
        ) {
            http_response_code(403);
            echo json_encode(['error' => 'You can only message the driver assigned to an accepted trip.']);
            exit;
        }
    }

    $conversation = $chat->getOrCreateConversation($tenantId, $otherUserId, $otherRole, $houseId, $truckRequestId);

} elseif ($role === 'landlord') {

    // Landlord -> Tenant: only a tenant who holds a paid, live booking with
    // THIS landlord (the Message Tenant button on Booking Requests).
    $landlordId = (int) $user['id'];
    $tenantId = (int) ($_POST['tenant_id'] ?? 0);

    if ($tenantId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid tenant.']);
        exit;
    }

    if ($houseId !== null) {
        $houseModel = new House();
        if (!$houseModel->belongsToLandlord($houseId, $landlordId)) {
            http_response_code(403);
            echo json_encode(['error' => 'This property is not yours.']);
            exit;
        }
    }

    if (!$bookingModel->hasLiveBookingWithLandlord($tenantId, $landlordId)) {
        http_response_code(403);
        echo json_encode(['error' => 'You can message tenants who have an active booking request with you.']);
        exit;
    }

    $conversation = $chat->getOrCreateConversation($tenantId, $landlordId, 'landlord', $houseId, null);

} elseif ($role === 'driver') {

    // Driver -> Tenant: driver picks a pending request and messages the
    // tenant who posted it. Multiple drivers can each open their own
    // conversation with the same tenant before anyone accepts.
    $tenantId = (int) ($_POST['tenant_id'] ?? 0);
    $otherUserId = (int) $user['id'];

    if ($tenantId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid tenant.']);
        exit;
    }

    if ($truckRequestId !== null) {
        $database = new Database();
        $pdo = $database->connect();

        $stmt = $pdo->prepare("SELECT tenant_id FROM truck_requests WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $truckRequestId]);
        $trip = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$trip || (int) $trip['tenant_id'] !== $tenantId) {
            http_response_code(403);
            echo json_encode(['error' => 'This request does not belong to that tenant.']);
            exit;
        }
    }

    $conversation = $chat->getOrCreateConversation($tenantId, $otherUserId, 'driver', $houseId, $truckRequestId);

} else {
    http_response_code(403);
    echo json_encode(['error' => 'Your role cannot start a new conversation.']);
    exit;
}

echo json_encode(['conversation' => $conversation]);