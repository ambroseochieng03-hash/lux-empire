<?php

ini_set('display_errors', 0);
ini_set('log_errors', 1);

/**
 * LUX EMPIRE Access Protection Layer
 */

require_once __DIR__ . '/../config/session.php';

/**
 * General login protection
 */
requireLogin();

/**
 * Optional role check:
 * Usage:
 * requireRoleAccess('admin');
 */
function requireRoleAccess($requiredRole) {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== $requiredRole) {
        header("Location: " . BASE_URL . "/auth/login.php?access_denied=1");
        exit();
    }
}

/**
 * Session timeout monitor
 */
checkSessionTimeout();
?>