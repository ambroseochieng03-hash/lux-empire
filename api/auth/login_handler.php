<?php

declare(strict_types=1);

require_once '../../config/db.php';
require_once '../../config/session.php';
require_once '../../config/csrf.php';
require_once '../../classes/Auth.php';
require_once '../../classes/Otp.php';
require_once '../../classes/OtpDelivery.php';
require_once '../../classes/TrustedDevice.php';
require_once '../../config/security/LoginSecurity.php';

Session::start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

Csrf::requireValid($_POST['csrf_token'] ?? null);

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if ($email === '' || $password === '') {
    echo json_encode(['success' => false, 'message' => 'Email and password are required.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
    exit;
}

$ip = LoginSecurity::clientIp();

LoginSecurity::beforeAuthentication($email, $ip);

$database = new Database();
$pdo = $database->connect();

$auth = new Auth($pdo);
$user = $auth->login($email, $password);

if ($user === null) {
    LoginSecurity::authenticationFailed($email, $ip);
    echo json_encode(['success' => false, 'message' => 'Incorrect empire credentials.']);
    exit;
}

if (isset($user['status'])) {

    if ($user['status'] === 'suspended') {
        echo json_encode(['success' => false, 'message' => 'Kindly contact Empire support for assistance.']);
        exit;
    }

    if ($user['status'] === 'blocked') {
        echo json_encode(['success' => false, 'message' => 'Account blocked from platform access.']);
        exit;
    }

    if ($user['status'] !== 'active') {
        echo json_encode(['success' => false, 'message' => 'Account inactive.']);
        exit;
    }
}

LoginSecurity::authenticationSucceeded($email, $ip);

$userId = (int) $user['id'];

$trustedDevice = new TrustedDevice();

/*
 * Admin accounts NEVER skip OTP via device trust, no matter what a
 * stored trust cookie says — admin access always requires the full
 * password + OTP challenge, every single time. isTrusted() is still
 * called (not skipped) so a stray/expired trust row belonging to an
 * admin account still gets cleaned up normally; its result is simply
 * never allowed to short-circuit the OTP step below for that role.
 */
$deviceIsTrusted = $trustedDevice->isTrusted($userId);

if ($deviceIsTrusted && $user['role'] !== 'admin') {
    // Recognized device — skip OTP, log straight in.
    completeLogin($user);
    exit;
}

// Unrecognized device — password was correct, but login is NOT
// complete yet. Store a pending marker (NOT $_SESSION['user'] —
// that's the actual authenticated state) and require OTP.
$_SESSION['pending_login_id'] = $userId;
$_SESSION['pending_login_role'] = $user['role'];
$_SESSION['pending_login_email'] = $user['email'];
$_SESSION['pending_login_name'] = $user['full_name'];

$otp = new Otp();
$code = $otp->generate($userId, 'new_device_login');

try {
    OtpDelivery::sendOtpEmail($user['email'], $user['full_name'], $code);
} catch (Throwable $e) {
    error_log('LUX EMPIRE login OTP publish (NATS) failed: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Could not send verification email. Please try again.']);
    exit;
}

echo json_encode([
    'success' => true,
    'needs_otp' => true,
    'expires_in' => 300
]);
exit;


/**
 * Shared by both the trusted-device path here and
 * verify_login_otp.php's post-OTP path — kept as one function so the
 * two can never drift apart on what "fully logged in" means.
 */
function completeLogin(array $user): void
{
    Session::regenerateAfterLogin();

    $_SESSION['user'] = [
        'id'        => $user['id'],
        'full_name' => $user['full_name'],
        'email'     => $user['email'],
        'role'      => $user['role'],
    ];

    unset($_SESSION['pending_login_id'], $_SESSION['pending_login_role'], $_SESSION['pending_login_email']);

    $redirect = match ($user['role']) {
        'tenant'   => BASE_URL . '/tenant',
        'landlord' => BASE_URL . '/landlord',
        'driver'   => BASE_URL . '/driver',
        'admin'    => BASE_URL . '/admin',
        default    => null,
    };

    if ($redirect === null) {
        Session::destroy();
        echo json_encode(['success' => false, 'message' => 'Unknown empire role.']);
        return;
    }

    echo json_encode(['success' => true, 'redirect' => $redirect]);
}