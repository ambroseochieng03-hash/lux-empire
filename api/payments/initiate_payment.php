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
    $holdKey = null;
    $redisHold = null;

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

            // Free bookings are redeemed with a voucher through
            // api/waivers/book_with_voucher.php — never through this payment endpoint.

            $amount = (float) BOOKING_FEE_AMOUNT;
            $metadata = ['house_id' => $houseId];

            /*
             * SOFT HOLD — stops two tenants paying for the same house at the same
             * moment. The first to start a payment holds the house for 2 minutes
             * (long enough for the M-Pesa PIN prompt); anyone else is told BEFORE
             * any money is requested. If Redis is down we carry on: the database
             * check in Payment::applyEntitlement() still guarantees only one
             * booking is created and the other payment is refunded automatically.
             */
            try {
                require_once '../../config/RedisConnection.php';

                $redisHold = RedisConnection::get();
                $holdKey = 'hold:house:' . $houseId;

                $acquired = $redisHold->set($holdKey, (string) $userId, ['nx', 'ex' => 120]);

                if (!$acquired && (string) $redisHold->get($holdKey) !== (string) $userId) {
                    http_response_code(409);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Another tenant is completing payment for this property right now. Please try again in about two minutes.'
                    ]);
                    exit;
                }
            } catch (Throwable $holdError) {
                $holdKey = null;
                $redisHold = null;
            }

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

    // The M-Pesa prompt was refused, so nobody is paying — free the house
    // straight away instead of making others wait out the 2-minute hold.
    if (empty($result['success']) && $holdKey !== null && $redisHold !== null) {
        try {
            if ((string) $redisHold->get($holdKey) === (string) $userId) {
                $redisHold->del($holdKey);
            }
        } catch (Throwable $holdError) {
            // Not fatal — the hold expires by itself.
        }
    }

    echo json_encode($result);

} catch (Throwable $e) {

    error_log('LUX EMPIRE initiate_payment error: ' . $e->getMessage());

    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred.']);
}