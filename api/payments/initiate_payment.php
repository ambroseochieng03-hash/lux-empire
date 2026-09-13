<?php

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json');

require_once '../../config/session.php';
require_once '../../config/csrf.php';
require_once '../../classes/Payment.php';
require_once '../../classes/House.php';
require_once '../../config/db.php';
require_once '../../config/security/DoSProtection.php';

try {

    Session::start();

    if (!Session::isAuthenticated()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authentication required.']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
        exit;
    }

    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    if (!Csrf::validate($body['csrf_token'] ?? null)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid or expired CSRF token.']);
        exit;
    }

    $user = Session::user();
    $userId = (int) $user['id'];
    $role = $user['role'] ?? '';

    DoSProtection::check($userId);

    $purpose = trim($body['purpose'] ?? '');
    $phoneInput = trim($body['phone'] ?? ($user['phone'] ?? ''));

    if ($phoneInput === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'A phone number is required.']);
        exit;
    }

    $amount = null;
    $metadata = [];

    switch ($purpose) {

        case 'landlord_pro':

            if ($role !== 'landlord') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Only landlord accounts can upgrade to Pro.']);
                exit;
            }

            $amount = (float) PRICE_LANDLORD_PRO_MONTHLY;
            break;

        case 'booking_fee':

            $houseId = (int) ($body['house_id'] ?? 0);

            if ($houseId <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid property.']);
                exit;
            }

            $houseModel = new House();
            $house = $houseModel->getHouseById($houseId);

            if (!$house) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Property not found.']);
                exit;
            }

            if ((int) $house['landlord_id'] === $userId) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'You cannot book your own property.']);
                exit;
            }

            if (!empty($house['is_hidden']) || $house['status'] !== 'available') {
                http_response_code(409);
                echo json_encode(['success' => false, 'message' => 'This property is no longer available to book.']);
                exit;
            }

            // Prevents firing a second STK push for the same house
            // while an earlier one is still pending — not the
            // race-condition guarantee itself (that's the row lock
            // in Payment::applyEntitlement()).
            $database = new Database();
            $pdoCheck = $database->connect();

            $dup = $pdoCheck->prepare("
                SELECT id FROM payments
                WHERE user_id = :user_id
                AND purpose = 'booking_fee'
                AND status = 'pending'
                AND JSON_EXTRACT(metadata, '$.house_id') = :house_id
                LIMIT 1
            ");
            $dup->execute([':user_id' => $userId, ':house_id' => $houseId]);

            if ($dup->fetch()) {
                http_response_code(409);
                echo json_encode(['success' => false, 'message' => 'A payment for this property is already in progress. Check your phone for the M-Pesa prompt.']);
                exit;
            }

            $amount = (float) BOOKING_FEE_AMOUNT;
            $metadata = ['house_id' => $houseId];
            break;

        case 'driver_wallet_topup':

            if ($role !== 'driver') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Only driver accounts have a commission wallet.']);
                exit;
            }

            $requested = (float) ($body['amount'] ?? 0);
            $minTopup = 100.0;
            $maxTopup = 50000.0;

            if ($requested < $minTopup || $requested > $maxTopup) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Enter an amount between KES ' . number_format($minTopup) . ' and KES ' . number_format($maxTopup) . '.',
                ]);
                exit;
            }

            $amount = round($requested, 2);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Unknown payment purpose.']);
            exit;
    }

    $payment = new Payment();
    $result = $payment->initiateStkPush($userId, $purpose, $amount, $phoneInput, $metadata);

    echo json_encode($result);

} catch (Throwable $e) {

    error_log('LUX EMPIRE initiate_payment error: ' . $e->getMessage());

    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred.']);
}