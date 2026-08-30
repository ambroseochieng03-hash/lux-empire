<?php

declare(strict_types=1);

require_once '../../config/app.php';
require_once '../../config/session.php';
require_once '../../classes/User.php';
require_once '../../classes/Consent.php';
require_once '../../config/security/DoSProtection.php';
require_once '../../config/security/RateLimiter.php';
require_once '../../classes/Notification.php';

Session::start();

DoSProtection::check();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . BASE_URL . "/register/driver");
    exit();
}

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

$rateKey = 'register_driver:' . $ip;

if (RateLimiter::isBlocked($rateKey)) {
    header("Location: " . BASE_URL . "/register/driver?error=" . urlencode("Too many attempts. Please try again later."));
    exit();
}

$attempts = RateLimiter::hit($rateKey, 3600);

if ($attempts > 10) {
    RateLimiter::block($rateKey, 3600);
    header("Location: " . BASE_URL . "/register/driver?error=" . urlencode("Too many attempts. Please try again later."));
    exit();
}

$full_name = trim($_POST['full_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$license_number = trim($_POST['license_number'] ?? '');
$vehicle_plate = trim($_POST['vehicle_plate'] ?? '');
$vehicle_type = trim($_POST['vehicle_type'] ?? '');
$password = $_POST['password'] ?? '';
$consentAccepted = ($_POST['consent_accepted'] ?? '') === '1';

if (!$consentAccepted) {
    header("Location: " . BASE_URL . "/register/driver?error=" . urlencode("You must accept the data processing notice to register."));
    exit();
}

if (
    $full_name === '' ||
    $email === '' ||
    $phone === '' ||
    $license_number === '' ||
    $vehicle_plate === '' ||
    $vehicle_type === '' ||
    $password === ''
) {
    header("Location: " . BASE_URL . "/register/driver?error=" . urlencode("All fields are required."));
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: " . BASE_URL . "/register/driver?error=" . urlencode("Invalid email address."));
    exit();
}

if (strlen($password) < 8) {
    header("Location: " . BASE_URL . "/register/driver?error=" . urlencode("Password must be at least 8 characters."));
    exit();
}

$userModel = new User();

$result = $userModel->registerDriver(
    $full_name,
    $email,
    $phone,
    $password,
    $license_number,
    $vehicle_plate,
    $vehicle_type
);

if (is_int($result)) {

    $newUserId = $result;

    $consent = new Consent();

    try {
        $consent->record($newUserId, 'driver', 'accepted', $ip);
    } catch (Throwable $e) {
        error_log('LUX EMPIRE consent recording failed for user ' . $newUserId . ': ' . $e->getMessage());
    }

    $notification = new Notification();
    $notification->create(
        $newUserId,
        'welcome',
        'Welcome to LUX EMPIRE',
        'Your driver account has been created. You can now browse and accept available truck requests.',
        BASE_URL . '/driver'
    );

    header("Location: " . BASE_URL . "/login?success=" . urlencode("Welcome to LUX EMPIRE. Your driver account has been created."));
    exit();
}

header("Location: " . BASE_URL . "/register/driver?error=" . urlencode((string) $result));
exit();
