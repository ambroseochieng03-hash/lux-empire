<?php

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json');

require_once '../../includes/auth_check.php';
requireRoleAccess('admin');

require_once '../../classes/Payment.php';
require_once '../../classes/Validator.php';
require_once '../../config/csrf.php';
require_once '../../config/security/DoSProtection.php';

DoSProtection::check();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

Csrf::requireValid($_POST['csrf_token'] ?? null);

$adminId = (int) Session::user()['id'];
$action = $_POST['action'] ?? '';
$notes = trim($_POST['notes'] ?? '');

if (!in_array($action, ['approve', 'reject', 'mark_refunded', 'complete_refund'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$payment = new Payment();

if ($action === 'complete_refund') {

    $refundId = (int) ($_POST['refund_id'] ?? 0);
    $mpesaRef = strtoupper(trim($_POST['mpesa_ref'] ?? ''));

    if ($refundId <= 0 || !Validator::isValidMpesaReference($mpesaRef)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Enter a valid 10-character M-Pesa reference (letters and numbers, starts with a letter).']);
        exit;
    }

    echo json_encode($payment->adminMarkRefundCompleted($refundId, $adminId, $mpesaRef, $notes));
    exit;
}

$paymentId = (int) ($_POST['payment_id'] ?? 0);

if ($paymentId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

if ($action === 'reject' && $notes === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'A reason is required to reject a payment.']);
    exit;
}

$overrideAmount = isset($_POST['override_amount']) && $_POST['override_amount'] !== ''
    ? (float) $_POST['override_amount']
    : null;

$result = match ($action) {
    'approve' => $payment->manuallyApprove($paymentId, $adminId, $notes, $overrideAmount),
    'reject' => $payment->manuallyReject($paymentId, $adminId, $notes),
    'mark_refunded' => $payment->markRefundResolved($paymentId, $adminId, $notes),
};

echo json_encode($result);