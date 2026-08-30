<?php

declare(strict_types=1);

require_once '../../config/app.php';
require_once '../../config/session.php';
require_once '../../config/csrf.php';
require_once '../../classes/User.php';
require_once '../../classes/GoogleAuth.php';
require_once '../../classes/TrustedDevice.php';
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
$rateKey = 'google_signup:' . $ip;

if (RateLimiter::isBlocked($rateKey)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many attempts. Please try again later.']);
    exit;
}

$attempts = RateLimiter::hit($rateKey, 300);

if ($attempts > 20) {
    RateLimiter::block($rateKey, 300);
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many attempts. Please try again later.']);
    exit;
}

$idToken = $_POST['id_token'] ?? '';

if ($idToken === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing Google credential.']);
    exit;
}

$googleUser = GoogleAuth::verify($idToken);

if ($googleUser === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Could not verify your Google account. Please try again.']);
    exit;
}

$userModel = new User();

$result = $userModel->findOrCreateGoogleUser(
    $googleUser['sub'],
    $googleUser['email'],
    $googleUser['name']
);

switch ($result['status']) {

    case 'blocked_existing_email':
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'An account with this email already exists. Please log in with your password instead.'
        ]);
        break;

    case 'existing_active':
        /*
         * Returning user via Google — this is functionally a login.
         * Google already strongly authenticated them, so no OTP step.
         */
        $user = $result['user'];

        Session::regenerateAfterLogin();
        Csrf::regenerate();

        $_SESSION['user'] = [
            'id'        => $user['id'],
            'full_name' => $user['full_name'],
            'email'     => $user['email'],
            'role'      => $user['role'],
        ];

        $trustedDevice = new TrustedDevice();
        $trustedDevice->trust((int) $user['id']);

        echo json_encode([
            'success' => true,
            'needs_password' => false,
            'message' => 'Welcome back.',
            'redirect' => BASE_URL . '/tenant'
        ]);
        break;

    case 'new_pending_password':
        /*
         * New account, email already verified by Google — still
         * needs a password set (mandatory for everyone) before
         * being fully active. Not logged in yet.
         */
        $_SESSION['pending_tenant_registration_id'] = $result['user_id'];

        echo json_encode([
            'success' => true,
            'needs_password' => false,
            'message' => 'Welcome back.',
            'redirect' => BASE_URL . '/tenant',
            'csrf_token' => Csrf::token()
        ]);
        break;

    default:
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Something went wrong. Please try again.']);
        break;
}
