<?php

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json');

require_once '../../includes/auth_check.php';
requireRoleAccess('admin');

require_once '../../classes/Payment.php';
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
$paymentId = (int) ($_POST['payment_id'] ?? 0);
$action = $_POST['action'] ?? '';
$notes = trim($_POST['notes'] ?? '');

if ($paymentId <= 0 || !in_array($action, ['approve', 'reject', 'mark_refunded'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

if ($action === 'reject' && $notes === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'A reason is required to reject a payment.']);
    exit;
}

$payment = new Payment();

$result = match ($action) {
    'approve' => $payment->manuallyApprove($paymentId, $adminId, $notes),
    'reject' => $payment->manuallyReject($paymentId, $adminId, $notes),
    'mark_refunded' => $payment->markRefundResolved($paymentId, $adminId, $notes),
};

echo json_encode($result);
