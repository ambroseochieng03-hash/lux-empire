<?php

declare(strict_types=1);

require_once '../../config/app.php';
require_once '../../config/session.php';
require_once '../../config/csrf.php';
require_once '../../classes/User.php';
require_once '../../classes/Otp.php';
require_once '../../classes/OtpDelivery.php';
require_once '../../config/security/DoSProtection.php';
require_once '../../config/security/RateLimiter.php';
require_once '../../config/security/RedisThrottle.php';

Session::start();
header('Content-Type: application/json');

DoSProtection::check();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

Csrf::requireValid($_POST['csrf_token'] ?? null);

$userId = $_SESSION['pending_registration_id'] ?? null;

if (!$userId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No pending registration found. Please start again.']);
    exit;
}

/*
 * Hard 45-second cooldown between individual resends. Atomic Redis
 * lock — the check AND the lock happen in a single command, so two
 * simultaneous clicks can no longer both slip through before either
 * sets the cooldown.
 */
$cooldownKey = "otp:resend:{$userId}:registration";

if (!RedisThrottle::tryAcquire($cooldownKey, 45)) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => 'Please wait a moment before requesting another code.',
        'retry_after' => RedisThrottle::retryAfter($cooldownKey)
    ]);
    exit;
}

$hourlyKey = 'resend_registration_otp:' . $userId;

if (RateLimiter::isBlocked($hourlyKey)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many resend attempts. Please try again later.']);
    exit;
}

$attempts = RateLimiter::hit($hourlyKey, 3600);

if ($attempts > 5) {
    RateLimiter::block($hourlyKey, 3600);
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many resend attempts. Please try again later.']);
    exit;
}

$userModel = new User();
$user = $userModel->getUserById((int) $userId);

if (!$user) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No pending registration found. Please start again.']);
    exit;
}

$otp = new Otp();
$code = $otp->generate((int) $userId, 'registration');

try {
    OtpDelivery::sendOtpEmail($user['email'], $user['full_name'], $code);
} catch (Throwable $e) {
    error_log('LUX EMPIRE OTP resend (NATS publish) failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not send verification email. Please try again.']);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'A new code has been sent.',
    'expires_in' => 300,
    'resend_cooldown' => 45
]);
