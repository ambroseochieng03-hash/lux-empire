<?php

declare(strict_types=1);

require_once '../config/app.php';
require_once '../config/session.php';

/**
 * Start the current session so it can be completely destroyed.
 */
Session::start();

/**
 * Completely destroy the authenticated session.
 */
Session::destroy();

/**
 * Redirect to login.
 */
header(
    'Location: ' . BASE_URL . '/login?success=' .
    urlencode('You have safely exited the Empire.')
);

exit;