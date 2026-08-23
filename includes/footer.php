<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/session.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    Session::start();
}

$isLoggedIn = Session::isAuthenticated();
?>

<?php if (!$isLoggedIn): ?>

<footer class="lux-footer">

<!-- Gold Divider -->
<div class="gold-line"></div>

<!-- Crown / Brand -->
<h3>LUX EMPIRE</h3>

<p style="
        max-width: 700px;
        margin: 18px auto;
        font-size: 1rem;
        line-height: 1.8;
">
        Luxury Living. Elite Movement. One Empire.  
        Discover premium homes, elite transport, and a lifestyle built for royalty.
</p>

<!-- Footer Navigation -->
<div style="
        display:flex;
        justify-content:center;
        flex-wrap:wrap;
        gap:25px;
        margin:30px 0;
">
<a href="<?php echo BASE_URL; ?>/" style="color: var(--gray); text-decoration:none;">Home</a>
<a href="#" style="color: var(--gray); text-decoration:none;">Luxury Homes</a>
<a href="#" style="color: var(--gray); text-decoration:none;">Elite Transport</a>
<a href="#" style="color: var(--gray); text-decoration:none;">About Empire</a>
<a href="#" style="color: var(--gray); text-decoration:none;">Contact</a>
</div>

<!-- Social / Prestige -->
<div style="
        display:flex;
        justify-content:center;
        gap:20px;
        font-size:1.4rem;
        margin-bottom:25px;
">
<span></span>
<span></span>
<span></span>
<span></span>
</div>

<!-- Royal Quote -->
<p style="
        font-style: italic;
        color: var(--gold);
        margin-bottom: 20px;
        font-family: 'Cinzel', serif;
">
        “Where elegance meets empire.”
</p>

<!-- Copyright -->
<p style="font-size: 0.85rem; color: #777;">
&copy; <?php echo date("Y"); ?> LUX EMPIRE. All Rights Reserved.
</p>

</footer>

<?php endif; ?>

</div> <!-- End lux-site-container -->

</body>
</html>