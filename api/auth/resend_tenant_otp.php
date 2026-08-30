<?php

declare(strict_types=1);

require_once '../../config/app.php';
require_once '../../config/session.php';
require_once '../../config/csrf.php';
require_once '../../classes/User.php';
require_once '../../classes/Otp.php';
require_once '../../classes/Mailer.php';
require_once '../../config/security/DoSProtection.php';
require_once '../../config/security/RateLimiter.php';

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

/*
 * One resend per 60 seconds, max 5 per hour — generous enough for a
 * genuinely lost email, tight enough to stop abuse of the mail
 * sender.
 */
$rateKey = 'resend_tenant_otp:' . $userId;

if (RateLimiter::isBlocked($rateKey)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Please wait before requesting another code.']);
    exit;
}

$attempts = RateLimiter::hit($rateKey, 3600);

if ($attempts > 5) {
    RateLimiter::block($rateKey, 3600);
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
    $mailer = new Mailer();
    $mailer->send(
        $user['email'],
        'Your LUX EMPIRE verification code',
        'Your verification code is ' . $code . '. It expires in 5 minutes.'
    );
} catch (Throwable $e) {
    error_log('LUX EMPIRE OTP resend failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not send verification email. Please try again.']);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'A new code has been sent.',
    'expires_in' => 300
]);
