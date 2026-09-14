<?php

require_once __DIR__ . '/../config/session.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    Session::start();
}

$isLoggedIn = Session::isAuthenticated();

/*
 * True only when the physically-requested script lives under
 * /dashboard/ — SCRIPT_FILENAME always reflects the top-level entry
 * script regardless of require() depth, so this works no matter how
 * many files this include is nested inside.
 */
$isDashboardContext = $isLoggedIn
    && strpos($_SERVER['SCRIPT_FILENAME'] ?? '', '/dashboard/') !== false;

$navNotifLink = null;
$navDashboardUrl = null;

if ($isLoggedIn) {

    $navUser = Session::user();
    $navRole = $navUser['role'] ?? null;

    $roleDashboardRoutes = [
        'tenant'   => '/tenant',
        'landlord' => '/landlord',
        'driver'   => '/driver',
        'admin'    => '/admin',
    ];

    if (isset($roleDashboardRoutes[$navRole])) {
        $navDashboardUrl = BASE_URL . $roleDashboardRoutes[$navRole];
    }

    if ($isDashboardContext) {

        $notifRoleRoutes = [
            'tenant'   => '/tenant/notifications',
            'landlord' => '/landlord/notifications',
            'driver'   => '/driver/notifications',
        ];

        if (isset($notifRoleRoutes[$navRole])) {
            $navNotifLink = BASE_URL . $notifRoleRoutes[$navRole];
        }
    }
}

?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/nav-menu.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/navbar-extra.css">

<?php if (!$isDashboardContext): ?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/mobile-nav.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/product-tour.css">
<?php endif; ?>

<?php if ($isDashboardContext): ?>
<style>
    /*
     * Keeps the dashboard navbar's logo in sync with dashboard.css's
     * own .lux-sidebar-toggle breakpoint (confirmed: 768px) — the
     * logo appears exactly when that button disappears, and vice
     * versa, so there's never a width where both or neither show.
     */
    @media (max-width: 768px) {
        .lux-dashboard-logo-desktop-only {
            display: none;
        }

        /*
         * .lux-sidebar-toggle (dashboard.css) is position:fixed at
         * left:18px, 50px wide -> right edge sits at 68px from the
         * viewport's left. .lux-navbar has padding:14px 18px on
         * mobile (style.css), so .logo's content already starts
         * 18px in on its own — space-between (style.css) then pins
         * it flush against that inner edge, which is exactly where
         * the fixed button floats too. MARGIN (not padding) here,
         * since .logo sits directly against .lux-navbar's own
         * padding — adding padding to .logo would sit inside ITS
         * box, not move the box itself away from the toggle button.
         * 68 - 18 (navbar's own edge padding) = 50px needed.
         */
        #luxDashboardNavbar {
            margin-left: 54px;
        }
    }
</style>
<?php endif; ?>

<nav class="lux-header">
<div class="lux-navbar">

<?php if (!$isDashboardContext): ?>

<!-- PUBLIC-PAGE BRAND — logo always shown here, both screen sizes.
     Unchanged from the original public-page behavior. -->
<div class="logo">
<span class="lux-mark-wrap">
    <img src="<?php echo BASE_URL . '/' . APP_FAVICON; ?>"
        onerror="this.onerror=null; this.src='<?php echo BASE_URL . '/' . APP_FAVICON_PNG_32; ?>';"
        class="lux-house-mark" alt="Lux Empire" width="100" height="87">
</span>
<div>
<div>LUX EMPIRE</div>
<small class="lux-navbar-tagline">
                    Elite Homes • Effortless Moves
</small>
</div>
</div>

<!-- MOBILE MENU BUTTON -->
<button class="lux-mobile-toggle" id="luxMobileToggleBtn" type="button" aria-haspopup="true" aria-expanded="false">
            ☰
</button>

<!-- DESKTOP NAVIGATION -->
<div class="nav-links" id="luxNavLinks">
<a href="<?php echo BASE_URL; ?>/" data-tour="home">Home</a>
<a href="<?php echo BASE_URL; ?>/browse" data-tour="homes">LUX Homes</a>
<a href="<?php echo BASE_URL; ?>/browse" data-tour="move">LUX Move</a>
<a href="#" data-tour="about" data-open-info-modal="about">About</a>
<a href="#" data-tour="contact" data-open-info-modal="contact">Contact</a>
</div>

<div class="lux-nav-buttons" id="luxNavButtons">
<?php
        $navMenuTriggerLabel = 'Access Empire';
require __DIR__ . '/nav_menu.php';
?>
</div>

<!-- MOBILE NAV POPOVER -->
<div class="lux-mobile-nav-popover" id="luxMobileNavPopover" aria-hidden="true">
<a href="<?php echo BASE_URL; ?>/" data-tour="m-home">Home</a>
<a href="<?php echo BASE_URL; ?>/browse" data-tour="m-browse">Browse Listings</a>
<a href="<?php echo BASE_URL; ?>/login" data-tour="m-login">Sign In</a>
<a href="#" data-tour="m-register" data-open-role-select>Create Account</a>
<a href="#" data-tour="m-about" data-open-info-modal="about">About</a>
<a href="#" data-tour="m-contact" data-open-info-modal="contact">Contact</a>
<a href="<?php echo BASE_URL; ?>/forgot-password" data-tour="m-recover">Recover Your Account</a>
</div>

<?php else: ?>

<!-- DASHBOARD BRAND — logo appears ONLY at desktop widths (where
     the sidebar stays open and sidebar.php's own toggle button
     hides itself via CSS); at smaller widths, that toggle button
     occupies this slot instead, so the logo is hidden here. App
     name + tagline show at both sizes, always. -->
<div class="logo" id="luxDashboardNavbar">
<span class="lux-mark-wrap lux-dashboard-logo-desktop-only">
    <img src="<?php echo BASE_URL . '/' . APP_FAVICON; ?>"
        onerror="this.onerror=null; this.src='<?php echo BASE_URL . '/' . APP_FAVICON_PNG_32; ?>';"
        class="lux-house-mark" alt="Lux Empire" width="100" height="87">
</span>
<div>
<div>LUX EMPIRE</div>
<small class="lux-navbar-tagline">
                    Elite Homes • Effortless Moves
</small>
</div>
</div>

<?php if ($navNotifLink): ?>
<!-- NOTIFICATION BELL -->
<div class="lux-notif-bell-wrap" id="luxNotifBell" data-notif-link="<?php echo htmlspecialchars($navNotifLink); ?>">
<i class="fa-solid fa-bell lux-notif-bell-icon"></i>
<span class="lux-notif-bell-badge is-hidden" id="luxNotifBellBadge">0</span>
</div>
<?php endif; ?>

<?php endif; ?>

</div>
</nav>

<?php if (!$isDashboardContext): ?>
<script src="<?php echo BASE_URL; ?>/assets/js/nav-menu.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/mobile-nav.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/product-tour.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/home-tour.js"></script>

<script>
/*
 * Sign In / Create Account triggers, when a session is already
 * active: redirect straight to the dashboard rather than showing a
 * login/registration form.
 */
(function () {
    <?php if ($navDashboardUrl): ?>
    const dashboardUrl = <?php echo json_encode($navDashboardUrl); ?>;

    document.addEventListener('click', function (event) {

        const trigger = event.target.closest('[data-open-role-select], [data-open-tenant-register]');

        if (!trigger) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        window.location.href = dashboardUrl;

    }, true);
    <?php endif; ?>
})();
</script>
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