<?php

declare(strict_types=1);

require_once '../../config/app.php';
require_once '../../config/session.php';
require_once '../../classes/User.php';
require_once '../../classes/Consent.php';
require_once '../../config/security/DoSProtection.php';
require_once '../../config/security/RateLimiter.php';

Session::start();

/**
 * No CSRF token here — same precedent as auth/login_handler.php's
 * LoginSecurity: this form doesn't operate on an already-authenticated
 * session, so CSRF's usual threat model doesn't apply. Abuse
 * protection instead comes from DoSProtection + the rate limit below.
 */
DoSProtection::check();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . BASE_URL . "/register/landlord");
    exit();
}

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

$rateKey = 'register_landlord:' . $ip;

if (RateLimiter::isBlocked($rateKey)) {
    header("Location: " . BASE_URL . "/register/landlord?error=" . urlencode("Too many attempts. Please try again later."));
    exit();
}

$attempts = RateLimiter::hit($rateKey, 3600);

if ($attempts > 10) {
    RateLimiter::block($rateKey, 3600);
    header("Location: " . BASE_URL . "/register/landlord?error=" . urlencode("Too many attempts. Please try again later."));
    exit();
}

/**
 * Collect & sanitize
 */
$full_name = trim($_POST['full_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$national_id = trim($_POST['national_id'] ?? '');
$password = $_POST['password'] ?? '';
$consentAccepted = ($_POST['consent_accepted'] ?? '') === '1';

/**
 * Consent is mandatory — checked server-side regardless of what the
 * client-side modal did, since the checkbox is a real required field
 * of the form it was submitted from.
 */
if (!$consentAccepted) {
    header("Location: " . BASE_URL . "/register/landlord?error=" . urlencode("You must accept the data processing notice to register."));
    exit();
}

if (
    $full_name === '' ||
    $email === '' ||
    $phone === '' ||
    $national_id === '' ||
    $password === ''
) {
    header("Location: " . BASE_URL . "/register/landlord?error=" . urlencode("All fields are required."));
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: " . BASE_URL . "/register/landlord?error=" . urlencode("Invalid email address."));
    exit();
}

if (strlen($password) < 8) {
    header("Location: " . BASE_URL . "/register/landlord?error=" . urlencode("Password must be at least 8 characters."));
    exit();
}

$userModel = new User();

$result = $userModel->registerLandlord(
    $full_name,
    $email,
    $phone,
    $national_id,
    $password
);

if (is_int($result)) {

    $newUserId = $result;

    $consent = new Consent();

    try {
        $consent->record($newUserId, 'landlord', 'accepted', $ip);
    } catch (Throwable $e) {
        /*
         * Non-critical path: the account was created successfully,
         * so don't fail registration over a logging error — but do
         * make sure it's visible in the logs.
         */
        error_log('LUX EMPIRE consent recording failed for user ' . $newUserId . ': ' . $e->getMessage());
    }

    header("Location: " . BASE_URL . "/login?success=" . urlencode("Welcome to LUX EMPIRE. Your landlord account has been created."));
    exit();
}

header("Location: " . BASE_URL . "/register/landlord?error=" . urlencode((string) $result));
exit();
