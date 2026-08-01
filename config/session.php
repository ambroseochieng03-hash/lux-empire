<?php
/**
 * LUX EMPIRE Session & Security Manager
 */

require_once __DIR__ . '/app.php';

/**
 * SESSION SECURITY SETTINGS
 * MUST COME BEFORE session_start()
 */
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);

/**
 * HTTPS-only cookies in production
 * Keep OFF on localhost
 */
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1);
}

/**
 * Custom session name
 */
session_name('LUXEMPIRESESSION');

/**
 * START SESSION
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Regenerate session ID once
 */
if (!isset($_SESSION['initiated'])) {

    session_regenerate_id(true);

    $_SESSION['initiated'] = true;
}

/**
 * SESSION TIMEOUT HANDLER
 */
function checkSessionTimeout() {

    if (isset($_SESSION['last_activity'])) {

        if (
            time() - $_SESSION['last_activity']
            > SESSION_TIMEOUT
        ) {

            session_unset();
            session_destroy();

            header(
                "Location: "
                . BASE_URL .
                "/auth/login.php?timeout=1"
            );

            exit();
        }
    }

    $_SESSION['last_activity'] = time();
}

/**
 * REQUIRE LOGIN
 */
function requireLogin() {

    if (!isset($_SESSION['user_id'])) {

        header(
            "Location: "
            . BASE_URL .
            "/auth/login.php"
        );

        exit();
    }
}

/**
 * ROLE PROTECTION
 */
function requireRole($role) {

    requireLogin();

    if (
        !isset($_SESSION['role']) ||
        $_SESSION['role'] !== $role
    ) {

        header(
            "Location: "
            . BASE_URL .
            "/index.php?unauthorized=1"
        );

        exit();
    }
}
?>