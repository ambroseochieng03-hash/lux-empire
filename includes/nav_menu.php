<?php
/**
 * LUX EMPIRE — COMPACT NAV MENU (partial)
 * SAVE AT: includes/nav_menu.php
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

        <a href="<?php echo BASE_URL; ?>/register" class="lux-nav-menu-item">
            <i class="fa-solid fa-user"></i> Register as Tenant
        </a>

        <a href="<?php echo BASE_URL; ?>/register/landlord" class="lux-nav-menu-item">
            <i class="fa-solid fa-crown"></i> Register as Landlord
        </a>

        <a href="<?php echo BASE_URL; ?>/register/driver" class="lux-nav-menu-item">
            <i class="fa-solid fa-truck-fast"></i> Register as Driver
        </a>

    </div>

</div>
