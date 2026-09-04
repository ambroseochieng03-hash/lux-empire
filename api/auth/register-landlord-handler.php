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
                Register as a Landlord
            </h1>

            <p class="auth-subtitle">
                List your properties and manage tenant requests inside the Empire.
            </p>
        </div>

        <?php if (isset($_GET['error'])): ?>
            <div class="auth-alert auth-alert-error">
                <?php echo htmlspecialchars($_GET['error']); ?>
            </div>
        <?php endif; ?>

        <form action="<?php echo BASE_URL; ?>/register-landlord-handler" method="POST">

            <?php
                /*
                 * The consent checkbox rendered here is a REAL field of
                 * this form (name="consent_accepted"). It is deliberately
                 * OUTSIDE .auth-form-fields below — see the comment on
                 * that div for why.
                 */
                $consentRole = 'landlord';
                require __DIR__ . '/../includes/consent_modal.php';
            ?>

            <!--
                Everything the consent modal locks/blurs lives in here.
                This div is a SIBLING of the modal above, not an
                ancestor — so blurring this never blurs the modal.
            -->
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

                <div class="auth-field">
                    <label for="nationalId">National ID</label>
                    <input type="text" id="nationalId" name="national_id" data-validate="national_id" required placeholder="7-9 digit ID number">
                </div>

                <div class="auth-field">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" data-validate="password" required minlength="8">
                </div>

                <button type="submit" class="lux-btn auth-submit-btn">
                    Register as Landlord
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
        role: "landlord"
    };
</script>
<script src="<?php echo BASE_URL; ?>/assets/js/form-validation.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/consent-modal.js"></script>
<script>
    // Block submission client-side if any field fails validation —
    // server-side (api/auth/register_landlord_handler.php) is the
    // real, authoritative gate; this is UX only.
    document.querySelector('.auth-form-fields').closest('form').addEventListener('submit', (event) => {
        if (window.LuxFormValidation && !LuxFormValidation.validateForm(document.querySelector('.auth-form-fields'))) {
            event.preventDefault();
        }
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>