<?php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/csrf.php';

Session::start();

$csrfToken = Csrf::token();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/consent-modal.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/auth-forms.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/form-validation.css">

<section class="auth-hero">

    <div class="lux-card auth-card">

        <div class="auth-header">
            <h1 class="auth-title">
                Register as a Driver
            </h1>

            <p class="auth-subtitle">
                Join the Empire's moving and logistics network.
            </p>
        </div>

        <?php if (isset($_GET['error'])): ?>
            <div class="auth-alert auth-alert-error">
                <?php echo htmlspecialchars($_GET['error']); ?>
            </div>
        <?php endif; ?>

        <form action="<?php echo BASE_URL; ?>/register-driver-handler" method="POST">

            <?php
                $consentRole = 'driver';
                require __DIR__ . '/../includes/consent_modal.php';
            ?>

            <div class="auth-form-fields">

                <div class="auth-field">
                    <label for="fullName">Full Name</label>
                    <input type="text" id="fullName" name="full_name" data-validate="fullname" required placeholder="Your full legal name">
                </div>

                <div class="auth-field">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" data-validate="email" required placeholder="you@example.com">
                </div>

                <div class="auth-field">
                    <label for="phone">Contact Number</label>
                    <input type="text" id="phone" name="phone" data-validate="phone_required" required placeholder="0712345678 or +254712345678">
                </div>

                <!--
                    NOT validated yet — pending your answer on whether
                    this is always a numeric National ID (7-9 digits,
                    same rule as landlord) or can also be an
                    alphanumeric driving license number. Add
                    data-validate="national_id" here once confirmed,
                    or I'll add a dedicated "license" rule if it needs
                    letters too.
                -->
                <div class="auth-field">
                    <label for="licenseNumber">ID / License Number</label>
                    <input type="text" id="licenseNumber" name="license_number" required placeholder="Driver's license or ID number">
                </div>

                <div class="auth-field">
                    <label for="vehiclePlate">Vehicle Plate Number</label>
                    <input type="text" id="vehiclePlate" name="vehicle_plate" data-validate="vehicle_plate" required placeholder="e.g. KDA 123A">
                </div>

                <div class="auth-field">
                    <label for="vehicleType">Vehicle Type / Description</label>
                    <input type="text" id="vehicleType" name="vehicle_type" required placeholder="e.g. Canter truck, 5-ton pickup">
                </div>

                <div class="auth-field">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" data-validate="password" required minlength="8">
                </div>

                <button type="submit" class="lux-btn auth-submit-btn">
                    Register as Driver
                </button>

            </div>

        </form>

        <div class="auth-footer-link">
            <p>
                Already part of the Empire?
                <a href="<?php echo BASE_URL; ?>/login">Enter Here</a>
            </p>
        </div>

    </div>

</section>

<script>
    window.LUX_CONSENT_CONFIG = {
        baseUrl: "<?php echo BASE_URL; ?>",
        csrfToken: "<?php echo htmlspecialchars($csrfToken); ?>",
        role: "driver"
    };
</script>
<script src="<?php echo BASE_URL; ?>/assets/js/form-validation.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/consent-modal.js"></script>
<script>
    document.querySelector('.auth-form-fields').closest('form').addEventListener('submit', (event) => {
        if (window.LuxFormValidation && !LuxFormValidation.validateForm(document.querySelector('.auth-form-fields'))) {
            event.preventDefault();
        }
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>