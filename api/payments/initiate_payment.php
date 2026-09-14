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

    $database = new Database();
    $pdoCheck = $database->connect();
    $payment = new Payment();

    /*
    * Only a genuinely RECENT pending attempt blocks a new one — a
    * double-click or an already-open modal, not something the person
    * gave up on minutes ago. Deliberately does NOT synchronously call
    * Safaricom here: that reconciliation belongs to the polling loop
    * (check_payment_status.php) and the future cron sweep, not the hot
    * path of starting a new payment — trying to resolve an old stuck
    * transaction inline can itself hang, blocking this request.
    */
    $recentWindowSeconds = 60;

    $dupParams = [
        ':user_id' => $userId,
        ':purpose' => $purpose,
        ':window' => $recentWindowSeconds,
    ];

    $houseScopeSql = '';

    if ($purpose === 'booking_fee') {
        $houseScopeSql = " AND JSON_EXTRACT(metadata, '$.house_id') = :house_id ";
        $dupParams[':house_id'] = (int) ($body['house_id'] ?? 0);
    }

    $existingPending = $pdoCheck->prepare("
        SELECT id FROM payments
        WHERE user_id = :user_id
        AND purpose = :purpose
        AND status = 'pending'
        AND created_at >= (NOW() - INTERVAL :window SECOND)
        $houseScopeSql
        ORDER BY id DESC LIMIT 1
    ");
    $existingPending->execute($dupParams);

    if ($existingPending->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'A payment attempt is already in progress. Check your phone, or wait a moment before trying again.']);
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

            require_once '../../classes/PaymentWaiver.php';

            if (PaymentWaiver::isWaived($userId, 'tenant')) {
                $waivedResult = $payment->grantWaivedPayment($userId, 'booking_fee', 0.0, ['house_id' => $houseId, 'waived' => true]);
                echo json_encode($waivedResult);
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

    $result = $payment->initiateStkPush($userId, $purpose, $amount, $phoneInput, $metadata);

    echo json_encode($result);

} catch (Throwable $e) {

    error_log('LUX EMPIRE initiate_payment error: ' . $e->getMessage());

    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred.']);
}