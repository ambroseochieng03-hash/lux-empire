<?php

declare(strict_types=1);

require_once '../../config/app.php';
require_once '../../config/session.php';
require_once '../../config/csrf.php';
require_once '../../classes/User.php';
require_once '../../classes/Otp.php';
require_once '../../classes/Mailer.php';
require_once '../../classes/Validator.php';
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

/*
 * Field-by-field validation with field-specific error messages, so
 * the frontend can highlight exactly which input is wrong — not just
 * a generic "invalid input" message.
 */

if (!Validator::isValidFullName($fullName)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'field' => 'full_name',
        'message' => 'Enter a valid full name (letters only, at least 2 characters).'
    ]);
    exit;
}

if (!Validator::isValidEmail($email)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'field' => 'email',
        'message' => 'Enter a valid email address.'
    ]);
    exit;
}

/*
 * Phone is optional on the tenant modal (per the original field
 * label), so only validate its FORMAT if something was entered —
 * an empty string is allowed through.
 */
if ($phone !== '' && !Validator::isValidKenyanPhone($phone)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'field' => 'phone',
        'message' => 'Enter a valid Kenyan phone number (e.g. 0712345678 or +254712345678).'
    ]);
    exit;
}

if (!Validator::isValidPassword($password)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'field' => 'password',
        'message' => 'Password must be at least 8 characters.'
    ]);
    exit;
}

$userModel = new User();

$result = $userModel->registerTenantPending($fullName, $email, $phone, $password);

if (!is_int($result)) {
    http_response_code(409);
    echo json_encode(['success' => false, 'field' => 'email', 'message' => $result]);
    exit;
}

$userId = $result;

$otp = new Otp();
$code = $otp->generate($userId, 'registration');

try {
    $mailer = new Mailer();
    $mailer->send(
        $email,
        'Your LUX EMPIRE verification code',
        'Your verification code is ' . $code . '. It expires in 5 minutes.'
    );
} catch (Throwable $e) {
    error_log('LUX EMPIRE OTP email send failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not send verification email. Please try again.']);
    exit;
}

$_SESSION['pending_tenant_registration_id'] = $userId;

echo json_encode([
    'success' => true,
    'message' => 'Check your email for a 6-digit verification code.',
    'expires_in' => 300
]);