<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/admin_api_bootstrap.php';
require_once __DIR__ . '/../../classes/PaymentWaiver.php';
require_once __DIR__ . '/../../config/security/Audit.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    adminJsonError('Method not allowed.', 405);
}

$waiverId = filter_input(INPUT_POST, 'waiver_id', FILTER_VALIDATE_INT);
$reason = trim((string) ($_POST['reason'] ?? ''));

if (!$waiverId) {
    adminJsonError('Invalid voucher.');
}

if ($reason === '') {
    adminJsonError('A reason is required to revoke a voucher.');
}

$result = PaymentWaiver::revoke($waiverId, $currentAdminId, $reason);

if (!$result['success']) {
    adminJsonError($result['message'], $result['code'] ?? 400);
}

Audit::log("Admin #{$currentAdminId} revoked voucher #{$waiverId} ({$reason})", $currentAdminId);

adminJsonResponse(['status' => 'revoked']);
