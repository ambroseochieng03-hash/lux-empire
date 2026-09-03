<?php

require_once __DIR__ . '/../config/session.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    Session::start();
}

$isLoggedIn = Session::isAuthenticated();

$navNotifLink = null;

if ($isLoggedIn) {
    $navUser = Session::user();
    $navRole = $navUser['role'] ?? null;

    $notifRoleRoutes = [
        'tenant'   => '/tenant/notifications',
        'landlord' => '/landlord/notifications',
        'driver'   => '/driver/notifications',
    ];

    if (isset($notifRoleRoutes[$navRole])) {
        $navNotifLink = BASE_URL . $notifRoleRoutes[$navRole];
    }
}

?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/nav-menu.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/navbar-extra.css">

<?php if (!$isLoggedIn): ?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/mobile-nav.css">
<?php endif; ?>

<nav class="lux-header">
<div class="lux-navbar">

<!-- BRAND -->
<div class="logo">
<span class="lux-mark-wrap">
    <svg viewBox="0 0 100 96" xmlns="http://www.w3.org/2000/svg" class="lux-house-mark" aria-hidden="true">
        <defs>
            <linearGradient id="luxHouseGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" stop-color="#fff6d0"/>
                <stop offset="22%" stop-color="#f5d76e"/>
                <stop offset="50%" stop-color="#d4af37"/>
                <stop offset="78%" stop-color="#a8791f"/>
                <stop offset="100%" stop-color="#7a5a15"/>
            </linearGradient>
        </defs>
        <path d="M50 6 L94 42 L82 42 L82 90 L60 90 L60 62 L40 62 L40 90 L18 90 L18 42 L6 42 Z"
              fill="none" stroke="url(#luxHouseGradient)" stroke-width="7" stroke-linejoin="round"/>
        <path d="M34 46 L66 78 M66 46 L34 78"
              fill="none" stroke="url(#luxHouseGradient)" stroke-width="7" stroke-linecap="round"/>
    </svg>
</span>
<div>
<div>LUX EMPIRE</div>
<small class="lux-navbar-tagline">
                    Elite Homes • Effortless Moves
</small>
</div>
</div>

<?php if (!$isLoggedIn): ?>

<!-- MOBILE MENU BUTTON -->
<button class="lux-mobile-toggle" id="luxMobileToggleBtn" type="button" aria-haspopup="true" aria-expanded="false">
            ☰
</button>

<!-- DESKTOP NAVIGATION -->
<div class="nav-links" id="luxNavLinks">
<a href="<?php echo BASE_URL; ?>/">Home</a>
<a href="<?php echo BASE_URL; ?>/browse">LUX Homes</a>
<a href="<?php echo BASE_URL; ?>/browse">LUX Move</a>
<a href="#" data-open-info-modal="about">About</a>
<a href="#" data-open-info-modal="contact">Contact</a>
</div>

<div class="lux-nav-buttons" id="luxNavButtons">
<?php
        $navMenuTriggerLabel = 'Access Empire';
require __DIR__ . '/nav_menu.php';
?>
</div>

<!-- MOBILE NAV POPOVER — compact, anchored under the hamburger,
     not a full-width expansion. Consolidates nav + auth entry
     points + info modals into one small menu for small screens. -->
<div class="lux-mobile-nav-popover" id="luxMobileNavPopover" aria-hidden="true">
<a href="<?php echo BASE_URL; ?>/">Home</a>
<a href="<?php echo BASE_URL; ?>/browse">Browse Listings</a>
<a href="<?php echo BASE_URL; ?>/login">Sign In</a>
<a href="#" data-open-role-select>Create Account</a>
<a href="#" data-open-info-modal="about">About</a>
<a href="#" data-open-info-modal="contact">Contact</a>
<a href="<?php echo BASE_URL; ?>/forgot-password">Recover Your Account</a>
</div>

<?php elseif ($navNotifLink): ?>

<!-- NOTIFICATION BELL -->
<div class="lux-notif-bell-wrap" id="luxNotifBell" data-notif-link="<?php echo htmlspecialchars($navNotifLink); ?>">
<i class="fa-solid fa-bell lux-notif-bell-icon"></i>
<span class="lux-notif-bell-badge is-hidden" id="luxNotifBellBadge">0</span>
</div>

<?php endif; ?>

</div>
</nav>

<?php if (!$isLoggedIn): ?>
<script src="<?php echo BASE_URL; ?>/assets/js/nav-menu.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/mobile-nav.js"></script>
<?php endif; ?>

<?php if ($navNotifLink): ?>
<script>
    window.LUX_NOTIF_BELL_CONFIG = {
        baseUrl: "<?php echo BASE_URL; ?>"
    };
</script>
<script src="<?php echo BASE_URL; ?>/assets/js/navbar-notif-init.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/notification-bell.js"></script>
<?php endif; ?>