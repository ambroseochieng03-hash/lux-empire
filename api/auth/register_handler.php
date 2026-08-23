<?php

require_once '../../config/app.php';
require_once '../../classes/User.php';

/**
 * Only allow POST requests
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: '. BASE_URL . '/register");
    exit();
}

/**
 * Collect & sanitize
 */
$full_name = trim($_POST['full_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$national_id = trim($_POST['national_id'] ?? '');
$role = trim($_POST['role'] ?? '');
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

/**
 * Validation
 */
if (
    empty($full_name) ||
    empty($email) ||
    empty($phone) ||
    empty($national_id) ||
    empty($role) ||
    empty($password) ||
    empty($confirm_password)
) {
    header("Location: '. BASE_URL . '/register?error=" . urlencode("All Empire fields are required."));
    exit();
}

/**
 * Email validation
 */
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: '. BASE_URL . '/register?error=" . urlencode("Invalid email address."));
    exit();
}

/**
 * Password match
 */
if ($password !== $confirm_password) {
    header("Location: '. BASE_URL . '/register?error=" . urlencode("Passwords do not match."));
    exit();
}

/**
 * Password strength
 */
if (strlen($password) < 8) {
    header("Location: '. BASE_URL . '/register?error=" . urlencode("Password must be at least 8 characters."));
    exit();
}

/**
 * Allowed roles only
 */
$allowed_roles = ['tenant', 'landlord', 'driver'];

if (!in_array($role, $allowed_roles)) {
    header("Location: '. BASE_URL . '/register?error=" . urlencode("Invalid Empire role selected."));
    exit();
}

/**
 * Register user
 */
$user = new User();

$result = $user->register(
    $full_name,
    $email,
    $phone,
    $national_id,
    $password,
    $role
);

/**
 * Result handling
 */
if ($result === true) {
    header("Location: '. BASE_URL . '/login?success=" . urlencode("Welcome to LUX EMPIRE. Your account has been created."));
    exit();
} else {
    header("Location: '. BASE_URL . '/register?error=" . urlencode($result));
    exit();
}
?>```