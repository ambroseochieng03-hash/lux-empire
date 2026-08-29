<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

<nav class="lux-header">
<div class="lux-navbar">

<!-- BRAND -->
<div class="logo">
<span class="lux-crown-3d-wrap"><i class="fa-solid fa-crown lux-crown-3d"></i></span>
<div>
<div>LUX EMPIRE</div>
<small class="lux-navbar-tagline">
                    Luxury • Power • Prestige
</small>
</div>
</div>

<?php if (!$isLoggedIn): ?>

<!-- MOBILE MENU BUTTON -->
<button class="lux-mobile-toggle" id="luxMobileToggleBtn" type="button">
            ☰
</button>

<!-- NAVIGATION -->
<div class="nav-links" id="luxNavLinks">
<a href="<?php echo BASE_URL; ?>/">Home</a>
<a href="#">LUX Homes</a>
<a href="#">LUX Move</a>
<a href="#">Estates</a>
<a href="#">Contact</a>
</div>

<!--
    Was two buttons (Login / Join The Empire) — now the single
    compact nav menu (includes/nav_menu.php), which also holds the
    landlord/driver registration links that didn't fit here before
    without the navbar visually expanding.
-->
<div class="lux-nav-buttons" id="luxNavButtons">
    <?php
        $navMenuTriggerLabel = 'Access Empire';
        require __DIR__ . '/nav_menu.php';
    ?>
</div>

<?php elseif ($navNotifLink): ?>

<!-- NOTIFICATION BELL -->
<div class="lux-notif-bell-wrap" id="luxNotifBell" data-notif-link="<?php echo htmlspecialchars($navNotifLink); ?>">
    <i class="fa-solid fa-bell lux-notif-bell-icon"></i>
    <span class="lux-notif-bell-badge" id="luxNotifBellBadge" style="display:none;">0</span>
</div>

<?php endif; ?>

</div>
</nav>

<?php if (!$isLoggedIn): ?>
<script>
    /*
     * Small bootstrapping listeners only (same pattern used across
     * this app for page config, e.g. window.LUX_BOOKING_CONFIG) —
     * not inline onclick="" attributes.
     */
    document.getElementById('luxMobileToggleBtn').addEventListener('click', function () {
        document.getElementById('luxNavLinks').classList.toggle('active');
        document.getElementById('luxNavButtons').classList.toggle('active');
    });
</script>
<script src="<?php echo BASE_URL; ?>/assets/js/nav-menu.js"></script>
<?php endif; ?>

<?php if ($navNotifLink): ?>
<script>
    window.LUX_NOTIF_BELL_CONFIG = {
        baseUrl: "<?php echo BASE_URL; ?>"
    };

    document.getElementById('luxNotifBell').addEventListener('click', function () {
        window.location.href = this.dataset.notifLink;
    });
</script>
<script src="<?php echo BASE_URL; ?>/assets/js/notification-bell.js"></script>
<?php endif; ?>