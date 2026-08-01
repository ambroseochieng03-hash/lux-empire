<?php
require_once '../config/session.php';

/**
 * Clear all session data
 */
$_SESSION = [];

/**
 * Destroy session cookie
 */
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

/**
 * Destroy session
 */
session_destroy();

/**
 * Redirect with premium message
 */
header("Location: login.php?success=" . urlencode("You have safely exited the Empire."));
exit();
?>