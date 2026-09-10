<?php

declare(strict_types=1);

require_once '../../config/app.php';
require_once '../../config/session.php';
require_once '../../config/csrf.php';
require_once '../../classes/Otp.php';
require_once '../../classes/OtpDelivery.php';
require_once '../../config/security/RedisThrottle.php';

Session::start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

Csrf::requireValid($_POST['csrf_token'] ?? null);

$pendingUserId = $_SESSION['pending_login_id'] ?? null;
$pendingEmail = $_SESSION['pending_login_email'] ?? null;
$pendingName = $_SESSION['pending_login_name'] ?? null;

if (!$pendingUserId || !$pendingEmail || !$pendingName) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No pending login found. Please log in again.']);
    exit;
}

$cooldownKey = "otp:resend:{$pendingUserId}:new_device_login";

if (!RedisThrottle::tryAcquire($cooldownKey, 45)) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => 'Please wait a moment before requesting another code.',
        'retry_after' => RedisThrottle::retryAfter($cooldownKey)
    ]);
    exit;
}

$otp = new Otp();
$code = $otp->generate((int) $pendingUserId, 'new_device_login');

try {
    OtpDelivery::sendOtpEmail($pendingEmail, $pendingName, $code);
} catch (Throwable $e) {
    error_log('LUX EMPIRE login OTP resend (NATS publish) failed: ' . $e->getMessage());
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
