<?php

declare(strict_types=1);

require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../config/session.php';
require_once '../../config/csrf.php';
require_once '../../classes/Otp.php';
require_once '../../classes/TrustedDevice.php';
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
$pendingRole = $_SESSION['pending_login_role'] ?? null;

if (!$pendingUserId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No pending login found. Please log in again.']);
    exit;
}

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

/*
 * Endpoint-level throttle, separate from Otp::verify()'s own 5-wrong
 * -attempts-per-code cap — that one protects a single OTP row; this
 * one caps how fast this IP+pending-account can hammer the endpoint
 * at all (e.g. across several resends), same shape as DoSProtection.
 */
$attemptKey = 'login_otp:attempts:' . hash('sha256', $ip . '|' . $pendingUserId);
$blockKey = 'login_otp:block:' . hash('sha256', $ip . '|' . $pendingUserId);

if (RedisThrottle::isBlocked($blockKey)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many attempts. Please try again later.']);
    exit;
}

$attempts = RedisThrottle::incrWithExpiry($attemptKey, 300);

if ($attempts > 8) {
    RedisThrottle::block($blockKey, 900);
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many attempts. Please try again later.']);
    exit;
}

$code = trim($_POST['code'] ?? '');

if ($code === '') {
    echo json_encode(['success' => false, 'message' => 'Enter the verification code.']);
    exit;
}

$otp = new Otp();

if (!$otp->verify((int) $pendingUserId, 'new_device_login', $code)) {
    echo json_encode(['success' => false, 'message' => 'Invalid or expired code.']);
    exit;
}

// Code correct — fetch the full user row (session so far only has
// id/role/email, not full_name, which completeLogin()-equivalent
// logic here needs for $_SESSION['user']).
$database = new Database();
$pdo = $database->connect();

$stmt = $pdo->prepare("SELECT id, full_name, email, role FROM users WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $pendingUserId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'Account no longer exists.']);
    exit;
}

$trustedDevice = new TrustedDevice();
$trustedDevice->trust((int) $user['id']);

Session::regenerateAfterLogin();

$_SESSION['user'] = [
    'id'        => $user['id'],
    'full_name' => $user['full_name'],
    'email'     => $user['email'],
    'role'      => $user['role'],
];

unset($_SESSION['pending_login_id'], $_SESSION['pending_login_role'], $_SESSION['pending_login_email'], $_SESSION['pending_login_name']);

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
    exit;
}

// Fresh CSRF token since we just crossed a privilege boundary
// (unauthenticated -> authenticated) — matches the same pattern
// tenant-register-modal.js already expects (data.csrf_token).
$newCsrfToken = Csrf::regenerate();

echo json_encode([
    'success' => true,
    'redirect' => $redirect,
    'csrf_token' => $newCsrfToken
]);
