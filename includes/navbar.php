<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/session.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    Session::start();
}

$isLoggedIn = Session::isAuthenticated();

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