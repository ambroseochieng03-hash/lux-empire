<?php

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json');

require_once '../../config/session.php';
require_once '../../config/csrf.php';
require_once '../../classes/Payment.php';
require_once '../../config/security/DoSProtection.php';
require_once '../../config/security/RateLimiter.php';

Session::start();

if (!Session::isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

$user = Session::user();
DoSProtection::check((int) $user['id']);

/*
 * Every submission triggers a live lookup at Safaricom, so it is limited:
 * 10 per hour per user, then blocked for an hour.
 */
$rateKey = 'receipt_submit:' . (int) $user['id'];

if (RateLimiter::isBlocked($rateKey)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many attempts. Please wait a while and try again.']);
    exit;
}

if (RateLimiter::hit($rateKey, 3600) > 10) {
    RateLimiter::block($rateKey, 3600);
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many attempts. Please wait a while and try again.']);
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

$paymentId = (int) ($body['payment_id'] ?? 0);
$receiptInput = trim($body['receipt_input'] ?? '');

require_once '../../classes/Validator.php';

if (!Validator::isValidMpesaReceiptInput($receiptInput)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => "That doesn't look like a valid M-Pesa code or confirmation message. Please paste it exactly as received."]);
    exit;
}

if ($paymentId <= 0 || $receiptInput === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$payment = new Payment();
echo json_encode($payment->submitUserReceipt((int) $user['id'], $paymentId, $receiptInput));
