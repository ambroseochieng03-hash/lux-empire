<?php
/**
 * LUX EMPIRE — ROLE SELECTION MODAL
 *
 * Replaces the old dead /register catch-all. Presents the three
 * real account types up front so the person self-selects the
 * correct registration flow with no ambiguity.
 *
 * Opened via any element with [data-open-role-select].
 * Requires assets/js/role-select-modal.js + role-select-modal.css.
 */
?>

<div class="role-select-modal" id="roleSelectModal" aria-hidden="true">
    <div class="role-select-modal-overlay" data-role-select-close></div>

    <div class="role-select-modal-box">

        <button type="button" class="role-select-modal-close" data-role-select-close aria-label="Close">×</button>

        <div class="role-select-header">
            <h2 class="role-select-title">Join The Empire</h2>
            <p class="role-select-subtitle">Tell us why you're here, and we'll take you to the right place.</p>
        </div>

        <div class="role-select-options">

            <a href="<?php echo BASE_URL; ?>/register/landlord" class="role-select-card">
                <div class="role-select-icon"><i class="fa-solid fa-building-columns"></i></div>
                <h3 class="role-select-card-title">List Your Property</h3>
                <p class="role-select-card-desc">
                    Own a home or apartment? List it with the Empire and connect with serious, verified tenants.
                </p>
                <span class="role-select-card-cta">Register as a Landlord <i class="fa-solid fa-arrow-right"></i></span>
            </a>

            <a href="<?php echo BASE_URL; ?>/browse" class="role-select-card">
                <div class="role-select-icon"><i class="fa-solid fa-house-chimney"></i></div>
                <h3 class="role-select-card-title">Find Your Next Home</h3>
                <p class="role-select-card-desc">
                    Hunting for a luxury house, or need a truck to move your belongings? Browse listings and request a move.
                </p>
                <span class="role-select-card-cta">Explore as a Tenant <i class="fa-solid fa-arrow-right"></i></span>
            </a>

            <a href="<?php echo BASE_URL; ?>/register/driver" class="role-select-card">
                <div class="role-select-icon"><i class="fa-solid fa-truck-fast"></i></div>
                <h3 class="role-select-card-title">Drive For The Empire</h3>
                <p class="role-select-card-desc">
                    Offer logistics and moving services? Register as a driver and start accepting truck requests.
                </p>
                <span class="role-select-card-cta">Register as a Driver <i class="fa-solid fa-arrow-right"></i></span>
            </a>

        </div>

    </div>

</div>
