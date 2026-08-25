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

<style>
.lux-crown-3d-wrap{
    font-size:2.3rem;
    line-height:1;
    display:inline-block;
}

.lux-crown-3d{
    background: linear-gradient(145deg, #fff6d0 0%, #f5d76e 22%, #d4af37 50%, #a8791f 78%, #7a5a15 100%);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    color: transparent;
    filter:
        drop-shadow(0 1px 0 rgba(255,255,255,0.55))
        drop-shadow(0 2px 2px rgba(0,0,0,0.35))
        drop-shadow(0 6px 10px rgba(0,0,0,0.45));
}

/* ================================
   NOTIFICATION BELL
================================= */

.lux-notif-bell-wrap{
    position:relative;
    width:44px;
    height:44px;
    border-radius:50%;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    cursor:pointer;
    background:rgba(255,255,255,0.04);
    border:1px solid rgba(212,175,55,0.25);
    transition:0.2s;
    flex-shrink:0;
}

.lux-notif-bell-wrap:hover{
    background:rgba(212,175,55,0.12);
}

.lux-notif-bell-icon{
    color:var(--gold);
    font-size:1.3rem;
}

.lux-notif-bell-badge{
    position:absolute;
    top:-4px;
    right:-4px;
    background:#ff3b3b;
    color:white;
    font-size:0.65rem;
    font-weight:bold;
    min-width:18px;
    height:18px;
    border-radius:50%;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    padding:0 4px;
    box-shadow:0 0 6px rgba(255,0,0,0.6);
}

.lux-notif-bell-wrap.has-unread .lux-notif-bell-icon{
    animation: luxBellShake 1.8s ease-in-out infinite;
    filter: drop-shadow(0 0 6px rgba(212,175,55,0.9));
}

@keyframes luxBellShake{
    0%, 100% { transform: rotate(0deg); }
    5%  { transform: rotate(14deg); }
    10% { transform: rotate(-12deg); }
    15% { transform: rotate(10deg); }
    20% { transform: rotate(-8deg); }
    25% { transform: rotate(4deg); }
    30% { transform: rotate(0deg); }
}
</style>

<nav class="lux-header">
<div class="lux-navbar">

<!-- BRAND -->
<div class="logo">
<span class="lux-crown-3d-wrap"><i class="fa-solid fa-crown lux-crown-3d"></i></span>
<div>
<div>LUX EMPIRE</div>
<small style="
                    display:block;
                    font-size:0.7rem;
                    color: var(--gray);
                    letter-spacing:2px;
                    margin-top:2px;
">
                    Luxury • Power • Prestige
</small>
</div>
</div>

<?php if (!$isLoggedIn): ?>

<!-- MOBILE MENU BUTTON -->
<button class="lux-mobile-toggle" onclick="toggleLuxNavbar()">
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

<!-- ACTION BUTTONS -->
<div class="lux-nav-buttons" id="luxNavButtons">
<a href="<?php echo BASE_URL; ?>/login" class="lux-btn" style="
                background: transparent;
                color: var(--gold);
                border: 1px solid var(--gold);
">
                Login
</a>

<a href="<?php echo BASE_URL; ?>/register" class="lux-btn">
                Join The Empire
</a>
</div>

<?php elseif ($navNotifLink): ?>

<!-- NOTIFICATION BELL -->
<div class="lux-notif-bell-wrap" id="luxNotifBell" style="margin-left:auto;" onclick="window.location.href='<?php echo $navNotifLink; ?>';">
    <i class="fa-solid fa-bell lux-notif-bell-icon"></i>
    <span class="lux-notif-bell-badge" id="luxNotifBellBadge" style="display:none;">0</span>
</div>

<?php endif; ?>

</div>
</nav>

<?php if (!$isLoggedIn): ?>
<script>
function toggleLuxNavbar() {

document
.getElementById("luxNavLinks")
.classList
.toggle("active");

document
.getElementById("luxNavButtons")
.classList
.toggle("active");
}
</script>
<?php endif; ?>

<?php if ($navNotifLink): ?>
<script>
    window.LUX_NOTIF_BELL_CONFIG = {
        baseUrl: "<?php echo BASE_URL; ?>"
    };
</script>
<script src="<?php echo BASE_URL; ?>/assets/js/notification-bell.js"></script>
<?php endif; ?>