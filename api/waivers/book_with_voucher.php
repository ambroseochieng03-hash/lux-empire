<?php

declare(strict_types=1);

require_once '../../includes/auth_check.php';
requireRoleAccess('tenant');

require_once '../../classes/Payment.php';
require_once '../../config/app.php';
require_once '../../config/csrf.php';
require_once '../../config/security/DoSProtection.php';
require_once '../../config/security/RateLimiter.php';

header('Content-Type: application/json');

DoSProtection::check();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

Csrf::requireValid($_POST['csrf_token'] ?? null);

$tenantId = (int) Session::user()['id'];

$rateKey = 'voucher_book:' . $tenantId;

if (RateLimiter::isBlocked($rateKey)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many attempts. Please wait a moment and try again.']);
    exit;
}

if (RateLimiter::hit($rateKey, 60) > 10) {
    RateLimiter::block($rateKey, 120);
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many attempts. Please wait a moment and try again.']);
    exit;
}

$houseId = filter_input(INPUT_POST, 'house_id', FILTER_VALIDATE_INT);

if (!$houseId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid property.']);
    exit;
}

$result = (new Payment())->bookWithVoucher($tenantId, $houseId);

if (!$result['success']) {
    http_response_code(409);
}

echo json_encode($result);
