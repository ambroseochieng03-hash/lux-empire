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
    <!-- Open Graph / Social Media Link Previews (WhatsApp, Facebook, etc.) -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo BASE_URL; ?>">
    <meta property="og:title" content="<?php echo APP_NAME; ?> | <?php echo APP_TAGLINE; ?>">
    <meta property="og:description" content="Luxury Living. Elite Movement. One Empire.">
    <!--
        Most social crawlers (WhatsApp included) do not reliably render
        SVG for link previews — this MUST be a raster image, not
        logo.svg. See assets/images/generate-icons.sh.
    -->
    <meta property="og:image" content="<?php echo BASE_URL . '/' . APP_OG_IMAGE; ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo APP_NAME; ?> | <?php echo APP_TAGLINE; ?>">
    <meta name="twitter:description" content="Luxury Living. Elite Movement. One Empire.">
    <meta name="twitter:image" content="<?php echo BASE_URL . '/' . APP_OG_IMAGE; ?>">

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
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/payment-modal.css">

    <!-- LUX EMPIRE Favicon -->
    <!-- SVG first: modern browsers (Chrome, Firefox, Edge) use this and ignore the rest. -->
    <link rel="icon" type="image/svg+xml" href="<?php echo BASE_URL . '/' . APP_FAVICON; ?>">
    <!-- Safari (and anything else without SVG favicon support) falls back to these. -->
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo BASE_URL . '/' . APP_FAVICON_PNG_32; ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo BASE_URL . '/' . APP_FAVICON_PNG_16; ?>">
    <!-- iOS "Add to Home Screen" icon. -->
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo BASE_URL . '/' . APP_APPLE_TOUCH_ICON; ?>">

    <!-- PWA manifest — enables offline support / "Add to Home Screen". -->
    <link rel="manifest" href="<?php echo BASE_URL; ?>/manifest.json">

    <!--
        iOS ignores manifest.json's colors entirely for "Add to Home
        Screen" — without these tags, standalone mode shows a plain
        WHITE status bar no matter what the manifest says. This is the
        actual cause of the white background on iOS.
    -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="LUX EMPIRE">

    <!-- Premium Meta -->
    <meta name="theme-color" content="#0A0A0A">
    <meta name="description" content="LUX EMPIRE - Luxury Living. Elite Movement. One Empire.">
</head>

<body>

<!-- Luxury Ambient Background -->
<div class="lux-background-overlay"></div>

<!-- Page Wrapper -->
<div class="lux-site-container">