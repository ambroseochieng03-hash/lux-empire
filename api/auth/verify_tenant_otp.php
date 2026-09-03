<?php

declare(strict_types=1);

require_once '../../config/app.php';
require_once '../../config/session.php';
require_once '../../config/csrf.php';
require_once '../../classes/User.php';
require_once '../../classes/Otp.php';
require_once '../../classes/TrustedDevice.php';
require_once '../../config/security/DoSProtection.php';
require_once '../../config/security/RateLimiter.php';
require_once '../../classes/Notification.php';

Session::start();
header('Content-Type: application/json');

DoSProtection::check();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

Csrf::requireValid($_POST['csrf_token'] ?? null);

$userId = $_SESSION['pending_tenant_registration_id'] ?? null;

if (!$userId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No pending registration found. Please start again.']);
    exit;
}

$rateKey = 'verify_tenant_otp:' . $userId;

if (RateLimiter::isBlocked($rateKey)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many attempts. Please try again later.']);
    exit;
}

/*
 * FIX: this used to call hit() and discard the result — the
 * threshold was never actually checked, so nothing was enforced.
 * Otp::verify() itself caps at 5 wrong guesses against a single
 * code, but this is a broader per-IP-independent guard: 10 verify
 * attempts (right or wrong) per 5-minute window, then a 10-minute
 * block, regardless of how many times they've resent the code.
 */
$attempts = RateLimiter::hit($rateKey, 300);

if ($attempts > 10) {
    RateLimiter::block($rateKey, 600);
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many attempts. Please try again later.']);
    exit;
}

$code = trim($_POST['code'] ?? '');

if ($code === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Enter the code from your email.']);
    exit;
}

$otp = new Otp();

if (!$otp->verify((int) $userId, 'registration', $code)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Incorrect or expired code.']);
    exit;
}

$userModel = new User();

if (!$userModel->activateTenant((int) $userId)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Activation failed. Please try again.']);
    exit;
}

$user = $userModel->getUserById((int) $userId);

/*
 * Log them in immediately and trust this device — they just proved
 * ownership of the email via OTP, no reason to make them log in
 * again right after registering.
 */
Session::regenerateAfterLogin();
Csrf::regenerate();

$_SESSION['user'] = [
    'id'        => $user['id'],
    'full_name' => $user['full_name'],
    'email'     => $user['email'],
    'role'      => $user['role'],
];

unset($_SESSION['pending_tenant_registration_id']);

$trustedDevice = new TrustedDevice();
$trustedDevice->trust((int) $userId);

$notification = new Notification();
$notification->create(
    (int) $user['id'],
    'welcome',
    'Welcome to LUX EMPIRE',
    'Your account is verified. Browse luxury properties and request a move whenever you\'re ready.',
    BASE_URL . '/tenant'
);

echo json_encode([
    'success' => true,
    'message' => 'Welcome to LUX EMPIRE.',
    'redirect' => BASE_URL . '/tenant',
    'csrf_token' => Csrf::token()
]);