
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/session.php';

?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/navbar.css">

<nav class="lux-header">
    <div class="lux-navbar">

        <!-- BRAND -->
        <div class="logo">
            <span style="font-size: 2.3rem;">👑</span>
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

        <!-- MOBILE MENU BUTTON -->
        <button class="lux-mobile-toggle" onclick="toggleLuxNavbar()">
            ☰
        </button>

        <!-- NAVIGATION -->
        <div class="nav-links" id="luxNavLinks">
            <a href="<?php echo BASE_URL; ?>/index.php">Home</a>
            <a href="#">LUX Homes</a>
            <a href="#">LUX Move</a>
            <a href="#">Estates</a>
            <a href="#">Contact</a>
        </div>

        <!-- ACTION BUTTONS -->
        <div class="lux-nav-buttons" id="luxNavButtons">
            <a href="<?php echo BASE_URL; ?>/auth/login.php" class="lux-btn" style="
                background: transparent;
                color: var(--gold);
                border: 1px solid var(--gold);
            ">
                Login
            </a>

            <a href="<?php echo BASE_URL; ?>/auth/register.php" class="lux-btn">
                Join The Empire
            </a>
        </div>

    </div>
    
</nav>

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