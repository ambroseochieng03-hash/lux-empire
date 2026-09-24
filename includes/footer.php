<?php

require_once __DIR__ . '/../config/session.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    Session::start();
}

$isLoggedIn = Session::isAuthenticated();

/*
 * Same detection as navbar.php — the footer must never appear on a
 * dashboard page (sidebar-driven layout, no room/need for it), but
 * SHOULD appear on every public page regardless of login state,
 * which is the actual bug being fixed here: it used to disappear
 * for anyone logged in, anywhere, including the homepage.
 */
$isDashboardContext = $isLoggedIn
    && strpos($_SERVER['SCRIPT_FILENAME'] ?? '', '/dashboard/') !== false;
?>

<?php if (!$isDashboardContext): ?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/footer-extra.css">

<footer class="lux-footer">

<div class="gold-line"></div>

<h3>LUX EMPIRE</h3>

<p class="lux-footer-desc">
    Luxury Living. Elite Movement. One Empire.
    Discover premium homes, elite transport, and a lifestyle built for royalty.
</p>

<div class="lux-footer-links">
<a href="<?php echo BASE_URL; ?>/">Home</a>
<a href="<?php echo BASE_URL; ?>/browse">Luxury Homes</a>
<a href="<?php echo BASE_URL; ?>/browse">Elite Transport</a>

<?php if (!$isLoggedIn): ?>
<a href="#" data-open-info-modal="about">About Empire</a>
<a href="#" data-open-info-modal="contact">Contact</a>
<a href="#" data-open-info-modal="privacy">Privacy Policy</a>
<?php endif; ?>

</div>

<p class="lux-footer-quote">
    "Where elegance meets empire."
</p>

<p class="lux-footer-copyright">
&copy; <?php echo date("Y"); ?> LUX EMPIRE. All Rights Reserved.
</p>

</footer>

<?php if (!$isLoggedIn): ?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/role-select-modal.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/info-modals.css">

<?php require __DIR__ . '/role_select_modal.php'; ?>
<?php require __DIR__ . '/info_modals.php'; ?>

<script src="<?php echo BASE_URL; ?>/assets/js/role-select-modal.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/info-modals.js"></script>

<?php endif; ?>

<?php endif; ?>

<script>
    window.LUX_PAYMENT_PAYBILL = "<?php echo htmlspecialchars(DARAJA_SHORTCODE, ENT_QUOTES); ?>";
</script>

<script>
    window.LUX_OFFLINE_MODAL_CONFIG = { baseUrl: "<?php echo BASE_URL; ?>" };
</script>
<script src="<?php echo BASE_URL; ?>/assets/js/offline-required-modal.js"></script>

<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('<?php echo BASE_URL; ?>/sw.js')
            .catch((err) => console.error('LUX EMPIRE: service worker registration failed', err));
    });
}
</script>

</div> <!-- End lux-site-container -->

</body>
</html>