<?php

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json');

require_once '../../config/session.php';
require_once '../../config/csrf.php';
require_once '../../classes/House.php';
require_once '../../config/security/DoSProtection.php';

Session::start();

if (!Session::isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

$user = Session::user();

if (($user['role'] ?? '') !== 'landlord') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$landlordId = (int) $user['id'];
DoSProtection::check($landlordId);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid or expired CSRF token.']);
    exit;
}

$houseId = (int) ($_POST['house_id'] ?? 0);
$action = $_POST['action'] ?? '';

if ($houseId <= 0 || !in_array($action, ['mark_unavailable', 'mark_available'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$houseModel = new House();
$house = $houseModel->getHouseById($houseId);

if (!$house || (int) $house['landlord_id'] !== $landlordId) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You do not have permission to update this property.']);
    exit;
}

$database = new Database();
$pdo = $database->connect();

if ($action === 'mark_unavailable') {

    if ($house['status'] !== 'available') {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'Only an available listing can be marked as booked elsewhere. A listing with a pending paid booking must be accepted or rejected first.',
        ]);
        exit;
    }

    $pdo->prepare("UPDATE houses SET status = 'unavailable' WHERE id = :id")->execute([':id' => $houseId]);

    echo json_encode(['success' => true, 'message' => 'Marked as booked elsewhere — hidden from tenants.', 'status' => 'unavailable']);
    exit;
}

if ($house['status'] !== 'unavailable') {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'This listing is not currently marked unavailable.']);
    exit;
}

$pdo->prepare("UPDATE houses SET status = 'available' WHERE id = :id")->execute([':id' => $houseId]);

echo json_encode(['success' => true, 'message' => 'Listing is visible to tenants again.', 'status' => 'available']);
