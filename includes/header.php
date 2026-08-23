<?php


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

    <!-- Crown Icon -->
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>/assets/images/logo/crown.png">

    <!-- Premium Meta -->
    <meta name="theme-color" content="#0A0A0A">
    <meta name="description" content="LUX EMPIRE - Luxury Living. Elite Movement. One Empire.">

    <!-- Optional Future Google Maps -->
    <script src='https://maps.googleapis.com/maps/api/js?key=<?php echo GOOGLE_MAPS_API_KEY; ?>'></script>
</head>

<body>

<!-- Luxury Ambient Background -->
<div class="lux-background-overlay"></div>

<!-- Page Wrapper -->
<div class="lux-site-container">