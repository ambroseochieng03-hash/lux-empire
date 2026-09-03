<?php

require_once __DIR__ . '/../config/session.php';

/*
 * Ensure the session is active before ANY output is sent.
 *
 * Dashboard pages already start the session early via
 * auth_check.php, so this is a no-op there. Public pages that
 * render this header (and, downstream, navbar.php) without going
 * through auth_check.php previously left the session unstarted
 * until navbar.php's own guarded Session::start() call — by which
 * point this file had already sent the <!DOCTYPE html>/<head>
 * output below, producing "headers already sent" warnings.
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
    Session::start();
}

require_once __DIR__ . '/../config/app.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <title><?php echo APP_NAME; ?> | <?php echo APP_TAGLINE; ?></title>

    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/fontawesome/css/fontawesome.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/fontawesome/css/solid.min.css">

    <!-- Main Styles -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/auth.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/dashboard.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/maps.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/tenant.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/admin.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/emergency.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/navbar.css">

    <!-- LUX EMPIRE Favicon -->
    <link
        rel="icon"
        type="image/svg+xml"
        href="<?php echo BASE_URL . '/' . APP_FAVICON; ?>"
    >

    <!-- Premium Meta -->
    <meta name="theme-color" content="#0A0A0A">
    <meta name="description" content="LUX EMPIRE - Luxury Living. Elite Movement. One Empire.">
</head>

<body>

<!-- Luxury Ambient Background -->
<div class="lux-background-overlay"></div>

<!-- Page Wrapper -->
<div class="lux-site-container">