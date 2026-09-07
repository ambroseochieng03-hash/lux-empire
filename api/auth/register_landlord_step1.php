<?php

declare(strict_types=1);

require_once '../../config/app.php';
require_once '../../config/session.php';
require_once '../../classes/User.php';
require_once '../../classes/Otp.php';
require_once '../../classes/OtpDelivery.php';
require_once '../../classes/Consent.php';
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

/*
 * No CSRF here — same precedent as login/registration elsewhere in
 * this app (no pre-existing authenticated session to protect).
 * DoSProtection + this rate limit are the abuse guards instead.
 */
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateKey = 'register_landlord:' . $ip;

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
$nationalId = trim($_POST['national_id'] ?? '');
$password = $_POST['password'] ?? '';
$consentAccepted = ($_POST['consent_accepted'] ?? '') === '1';

if (!$consentAccepted) {
    http_response_code(400);
    echo json_encode(['success' => false, 'field' => 'consent', 'message' => 'You must accept the data processing notice to register.']);
    exit;
}

if (!Validator::isValidFullName($fullName)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'field' => 'full_name', 'message' => 'Enter a valid full name (letters only, at least 2 characters).']);
    exit;
}

if (!Validator::isValidEmail($email)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'field' => 'email', 'message' => 'Enter a valid email address.']);
    exit;
}

if (!Validator::isValidKenyanPhone($phone)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'field' => 'phone', 'message' => 'Enter a valid Kenyan phone number (e.g. 0712345678 or +254712345678).']);
    exit;
}

if (!Validator::isValidNationalId($nationalId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'field' => 'national_id', 'message' => 'National ID must be 7-9 digits, numbers only.']);
    exit;
}

if (!Validator::isValidPassword($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'field' => 'password', 'message' => 'Password must be at least 8 characters.']);
    exit;
}

$userModel = new User();

$result = $userModel->registerLandlordPending($fullName, $email, $phone, $nationalId, $password);

if (!is_int($result)) {
    http_response_code(409);
    echo json_encode(['success' => false, 'field' => 'email', 'message' => $result]);
    exit;
}

$userId = $result;

$consent = new Consent();

try {
    $consent->record($userId, 'landlord', 'accepted', $ip);
} catch (Throwable $e) {
    error_log('LUX EMPIRE consent recording failed for user ' . $userId . ': ' . $e->getMessage());
}

$otp = new Otp();
$code = $otp->generate($userId, 'registration');

try {
    OtpDelivery::sendOtpEmail($email, $fullName, $code);
} catch (Throwable $e) {
    error_log('LUX EMPIRE OTP publish (NATS) failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not send verification email. Please try again.']);
    exit;
}

$_SESSION['pending_registration_id'] = $userId;
$_SESSION['pending_registration_role'] = 'landlord';

echo json_encode([
    'success' => true,
    'message' => 'Check your email for a 6-digit verification code.',
    'expires_in' => 300
]);
