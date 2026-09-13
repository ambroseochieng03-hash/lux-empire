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
DoSProtection::check((int) $user['id']);

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

echo json_encode(['success' => true] + $status);
