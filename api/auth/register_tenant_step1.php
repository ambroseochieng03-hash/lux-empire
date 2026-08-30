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

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateKey = 'register_tenant:' . $ip;

if (RateLimiter::isBlocked($rateKey)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many attempts. Please try again later.']);
    exit;
}

$attempts = RateLimiter::hit($rateKey, 3600);

if ($attempts > 10) {
    RateLimiter::block($rateKey, 3600);
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many attempts. Please try again later.']);
    exit;
}

$fullName = trim($_POST['full_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$password = $_POST['password'] ?? '';

if ($fullName === '' || $email === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Name, email, and password are required.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
    exit;
}

if (strlen($password) < 8) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters.']);
    exit;
}

$userModel = new User();

$result = $userModel->registerTenantPending($fullName, $email, $phone, $password);

if (!is_int($result)) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => $result]);
    exit;
}

$userId = $result;

$otp = new Otp();
$code = $otp->generate($userId, 'registration');

/*
 * ---------------------------------------------------------------
 * MAILER INTERFACE ASSUMED — classes/Mailer.php hasn't been shared
 * with me, so this call is a guess at its shape. If it doesn't
 * match the real class, this line is what needs fixing — nothing
 * else in this file depends on it.
 * ---------------------------------------------------------------
 */
try {
    $mailer = new Mailer();
    $mailer->send(
        $email,
        $fullName,                                  // toName — I already have this in scope
        'Your LUX EMPIRE verification code',        // subject
        'Your verification code is ' . $code . '. It expires in 5 minutes.'  // bodyHtml
    );
} catch (Throwable $e) {
    error_log('LUX EMPIRE OTP email send failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not send verification email. Please try again.']);
    exit;
}

/*
 * Store the pending registration id SERVER-SIDE in session — never
 * trust a client-supplied user_id for the verify/resend steps.
 */
$_SESSION['pending_tenant_registration_id'] = $userId;

echo json_encode([
    'success' => true,
    'message' => 'Check your email for a 6-digit verification code.',
    'expires_in' => 300
]);
