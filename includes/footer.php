<?php

require_once __DIR__ . '/../config/session.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    Session::start();
}

$isLoggedIn = Session::isAuthenticated();
?>

<?php if (!$isLoggedIn): ?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/footer-extra.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/role-select-modal.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/info-modals.css">

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
<a href="#" data-open-info-modal="about">About Empire</a>
<a href="#" data-open-info-modal="contact">Contact</a>
<a href="#" data-open-info-modal="privacy">Privacy Policy</a>
</div>

<p class="lux-footer-quote">
    "Where elegance meets empire."
</p>

<p class="lux-footer-copyright">
&copy; <?php echo date("Y"); ?> LUX EMPIRE. All Rights Reserved.
</p>

</footer>

<?php require __DIR__ . '/role_select_modal.php'; ?>
<?php require __DIR__ . '/info_modals.php'; ?>

<script src="<?php echo BASE_URL; ?>/assets/js/role-select-modal.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/info-modals.js"></script>

<?php endif; ?>

</div> <!-- End lux-site-container -->

</body>
</html>