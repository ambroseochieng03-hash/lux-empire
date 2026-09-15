<?php

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json');

require_once '../../config/session.php';
require_once '../../config/csrf.php';
require_once '../../classes/Payment.php';
require_once '../../classes/House.php';
require_once '../../config/security/DoSProtection.php';

Session::start();

if (!Session::isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

$user = Session::user();
$userId = (int) $user['id'];
$role = $user['role'] ?? '';
DoSProtection::check($userId);

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

$purpose = trim($body['purpose'] ?? '');
$receiptInput = trim($body['receipt_input'] ?? '');

if ($receiptInput === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Paste the M-Pesa message or code.']);
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
        if (!$house || $house['status'] !== 'available') {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'This property is no longer available to book.']);
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
        $rawAmount = $body['amount'] ?? null;
        if ($rawAmount !== null && $rawAmount !== '') {
            $requested = (float) $rawAmount;
            if ($requested < 100 || $requested > 50000) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Enter an amount between KES 100 and KES 50,000, or leave it blank and paste the full M-Pesa message instead.']);
                exit;
            }
            $amount = round($requested, 2);
        } else {
            // No amount typed — Payment::submitPaybillPayment() will
            // read it straight from the pasted M-Pesa message.
            $amount = null;
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Unknown payment purpose.']);
        exit;
}

$payment = new Payment();
echo json_encode($payment->submitPaybillPayment($userId, $purpose, $amount, $receiptInput, $metadata));
