<?php

/**
 * LUX EMPIRE
 * Authentication Protection Layer
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/session.php';


/**
 * Start and validate session.
 */
Session::start();


/**
 * Require authenticated user.
 */
if (!Session::isAuthenticated()) {

    header(
        'Location: ' . BASE_URL . '/login?access_denied=1'
    );

    exit();
}


/**
 * Require a specific role.
 */
function requireRoleAccess(string $requiredRole): void
{
    $user = Session::user();

    if ($user === null) {

        header(
            'Location: ' . BASE_URL . '/login?access_denied=1'
        );

        exit();
    }

    if (($user['role'] ?? null) !== $requiredRole) {

        http_response_code(403);

        exit('Access denied.');
    }
}