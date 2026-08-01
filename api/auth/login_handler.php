<?php
require_once '../../classes/User.php';
require_once '../../config/session.php';

/**
 * Only POST requests
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../../auth/login.php");
    exit();
}

/**
 * Collect inputs
 */
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

/**
 * Validation
 */
if (empty($email) || empty($password)) {
    header("Location: ../../auth/login.php?error=" . urlencode("Email and password are required."));
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: ../../auth/login.php?error=" . urlencode("Invalid email format."));
    exit();
}

/**
 * User lookup
 */
$userModel = new User();
$user = $userModel->getUserByEmail($email);

/**
 * User exists?
 */
if (!$user) {
    header("Location: ../../auth/login.php?error=" . urlencode("Empire member not found."));
    exit();
}

/**
 * Verify password (Argon2id compatible)
 */
if (!password_verify($password, $user['password'])) {
    header("Location: ../../auth/login.php?error=" . urlencode("Incorrect empire credentials."));
    exit();
}


/**
 * Account status protection
 */
if (isset($user['status'])) {

    if ($user['status'] === 'suspended') {

        header(
            "Location: ../../auth/login.php?error="
            . urlencode("Kindly contact Empire support for assistance.")
        );

        exit();
    }

    if ($user['status'] === 'blocked') {

        header(
            "Location: ../../auth/login.php?error="
            . urlencode("Account blocked from platform access.")
        );

        exit();
    }

    if ($user['status'] !== 'active') {

        header(
            "Location: ../../auth/login.php?error="
            . urlencode("Account inactive.")
        );

        exit();
    }
}

/**
 * Secure session
 */
session_regenerate_id(true);

$_SESSION['user_id'] = $user['id'];
$_SESSION['full_name'] = $user['full_name'];
$_SESSION['email'] = $user['email'];
$_SESSION['role'] = $user['role'];
$_SESSION['last_activity'] = time();

/**
 * Role-based redirect
 */
switch ($user['role']) {

    case 'tenant':
        header("Location: ../../dashboard/tenant/dashboard.php");
        break;

    case 'landlord':
        header("Location: ../../dashboard/landlord/dashboard.php");
        break;

    case 'driver':
        header("Location: ../../dashboard/driver/dashboard.php");
        break;

    case 'admin':
        header("Location: ../../dashboard/admin/dashboard.php");
        break;

    default:
        header("Location: ../../auth/login.php?error=" . urlencode("Unknown empire role."));
        break;
}

exit();
?>