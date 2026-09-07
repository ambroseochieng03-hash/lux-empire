<?php

declare(strict_types=1);

require_once '../../config/app.php';
require_once '../../config/session.php';
require_once '../../config/csrf.php';
require_once '../../classes/User.php';
require_once '../../classes/Otp.php';
require_once '../../classes/TrustedDevice.php';
require_once '../../classes/Notification.php';
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

/*
 * Generic session key — used by tenant, landlord, and driver
 * registration alike. Replaces the old tenant-only
 * pending_tenant_registration_id.
 */
$userId = $_SESSION['pending_registration_id'] ?? null;

if (!$userId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No pending registration found. Please start again.']);
    exit;
}

$rateKey = 'verify_registration_otp:' . $userId;

if (RateLimiter::isBlocked($rateKey)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many attempts. Please try again later.']);
    exit;
}

/*
 * Threshold actually enforced this time — 10 attempts / 5 min
 * window, then a 10-minute block. Otp::verify() separately caps at
 * 5 wrong guesses against a single code; this is the broader guard
 * regardless of how many times a code has been resent.
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

if (!$userModel->activatePendingAccount((int) $userId)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Activation failed. Please try again.']);
    exit;
}

$user = $userModel->getUserById((int) $userId);

if (!$user) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Something went wrong. Please try again.']);
    exit;
}

/*
 * Log them in IMMEDIATELY, regardless of role — they just proved
 * ownership of the email via OTP, no reason to send anyone back to
 * a login form right after registering.
 */
Session::regenerateAfterLogin();
Csrf::regenerate();

$_SESSION['user'] = [
    'id'        => $user['id'],
    'full_name' => $user['full_name'],
    'email'     => $user['email'],
    'role'      => $user['role'],
];

unset($_SESSION['pending_registration_id'], $_SESSION['pending_registration_role']);

$trustedDevice = new TrustedDevice();
$trustedDevice->trust((int) $userId);

/*
 * Role-aware redirect and welcome message — this used to be
 * hardcoded to '/tenant' regardless of who was registering.
 */
$roleRoutes = [
    'tenant' => '/tenant',
    'landlord' => '/landlord',
    'driver' => '/driver',
];

$redirectPath = $roleRoutes[$user['role']] ?? '/login';

$welcomeMessages = [
    'tenant' => 'Your account is verified. Browse luxury properties and request a move whenever you\'re ready.',
    'landlord' => 'Your landlord account is verified. You can now list properties and manage booking requests.',
    'driver' => 'Your driver account is verified. Your profile will be reviewed before you can accept trips.',
];

$notification = new Notification();
$notification->create(
    (int) $user['id'],
    'welcome',
    'Welcome to LUX EMPIRE',
    $welcomeMessages[$user['role']] ?? 'Your account has been verified.',
    BASE_URL . $redirectPath
);

echo json_encode([
    'success' => true,
    'message' => 'Welcome to LUX EMPIRE.',
    'redirect' => BASE_URL . $redirectPath,
    'csrf_token' => Csrf::token()
]);
