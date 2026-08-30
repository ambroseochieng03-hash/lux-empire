<?php
/**
 * LUX EMPIRE — COMPACT NAV MENU (partial)
 *
 * A single trigger button that opens a small (~160px) popover
 * anchored to its own bottom-right corner, instead of a full row of
 * nav buttons. Safe to include multiple times on one page (e.g. the
 * homepage has one in the hero and one in the CTA section) — the
 * behavior in assets/js/nav-menu.js finds each popover relative to
 * the button that was clicked, not by a shared/global id.
 *
 * Usage:
 *   $navMenuTriggerLabel = 'Access Empire'; // optional, has a default
 *   require __DIR__ . '/nav_menu.php';
 */

$triggerLabel = $navMenuTriggerLabel ?? 'Access Empire';
?>

<div class="lux-nav-menu">

    <button type="button" class="lux-btn lux-nav-menu-trigger">
        <?php echo htmlspecialchars($triggerLabel); ?>
        <i class="fa-solid fa-chevron-down lux-nav-menu-caret"></i>
    </button>

    <div class="lux-nav-menu-popover" aria-hidden="true">

        <a href="<?php echo BASE_URL; ?>/login" class="lux-nav-menu-item">
            <i class="fa-solid fa-right-to-bracket"></i> Login
        </a>

        <!--
            Guest browsing IS the tenant entry point now — it lists
            houses/trucks publicly and opens the tenant registration
            modal (includes/tenant_register_modal.php) the moment a
            guest tries to actually book something. No separate
            "Register as Tenant" link needed.
        -->
        <a href="<?php echo BASE_URL; ?>/browse" class="lux-nav-menu-item">
            <i class="fa-solid fa-magnifying-glass"></i> Browse Listings
        </a>

        <a href="<?php echo BASE_URL; ?>/register/landlord" class="lux-nav-menu-item">
            <i class="fa-solid fa-crown"></i> Register as Landlord
        </a>

        <a href="<?php echo BASE_URL; ?>/register/driver" class="lux-nav-menu-item">
            <i class="fa-solid fa-truck-fast"></i> Register as Driver
        </a>

    </div>

</div>