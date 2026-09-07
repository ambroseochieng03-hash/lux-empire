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

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateKey = 'register_driver:' . $ip;

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
$identityType = $_POST['identity_type'] ?? '';
$identityValue = trim($_POST['identity_value'] ?? '');
$vehiclePlate = trim($_POST['vehicle_plate'] ?? '');
$vehicleType = trim($_POST['vehicle_type'] ?? '');
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

if (!in_array($identityType, ['national_id', 'license'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'field' => 'identity_type', 'message' => 'Select either National ID or Driving License.']);
    exit;
}

if (!Validator::isValidDriverIdentity($identityValue, $identityType)) {
    $message = $identityType === 'national_id'
        ? 'National ID must be 7-9 digits, numbers only.'
        : 'Enter a valid license number (letters/numbers, 5-15 characters).';
    http_response_code(400);
    echo json_encode(['success' => false, 'field' => 'identity_value', 'message' => $message]);
    exit;
}

if (!Validator::isValidVehiclePlate($vehiclePlate)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'field' => 'vehicle_plate', 'message' => 'Enter a valid plate number (e.g. KDA 123A).']);
    exit;
}

if ($vehicleType === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'field' => 'vehicle_type', 'message' => 'Enter a vehicle type/description.']);
    exit;
}

if (!Validator::isValidPassword($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'field' => 'password', 'message' => 'Password must be at least 8 characters.']);
    exit;
}

$userModel = new User();

$result = $userModel->registerDriverPending(
    $fullName,
    $email,
    $phone,
    $password,
    $identityValue,
    $identityType,
    $vehiclePlate,
    $vehicleType
);

if (!is_int($result)) {
    http_response_code(409);
    echo json_encode(['success' => false, 'field' => 'email', 'message' => $result]);
    exit;
}

$userId = $result;

$consent = new Consent();

try {
    $consent->record($userId, 'driver', 'accepted', $ip);
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
$_SESSION['pending_registration_role'] = 'driver';

echo json_encode([
    'success' => true,
    'message' => 'Check your email for a 6-digit verification code.',
    'expires_in' => 300
]);
