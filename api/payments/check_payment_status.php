<?php

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json');

require_once '../../config/session.php';
require_once '../../classes/Payment.php';
require_once '../../config/security/DoSProtection.php';

Session::start();

if (!Session::isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

$user = Session::user();
DoSProtection::check((int) $user['id'], 'polling');

$paymentId = (int) ($_GET['payment_id'] ?? 0);

if ($paymentId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid payment id.']);
    exit;
}

$payment = new Payment();
$status = $payment->getPaymentStatus($paymentId, (int) $user['id']);

if ($status === null) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Payment not found.']);
    exit;
}

/*
 * If the callback hasn't resolved this within 15 seconds, actively
 * ask Safaricom instead of waiting indefinitely — see
 * Payment::reconcilePendingPayment() for why the callback alone
 * isn't enough. Frontend polls every 3s, so this fires on roughly
 * the 5th poll onward, and every poll after that until resolved.
 */
if ($status['status'] === 'pending' && strtotime($status['created_at']) <= (time() - 15)) {
    $payment->reconcilePendingPayment($paymentId);
    $status = $payment->getPaymentStatus($paymentId, (int) $user['id']);
}

echo json_encode(['success' => true] + $status);
