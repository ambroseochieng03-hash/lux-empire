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

        <!-- Server-rendered error (non-JS fallback) AND JS-driven step-1 errors both use this one element. -->
        <div class="auth-alert auth-alert-error" id="registerTopError" <?php echo isset($_GET['error']) ? '' : 'hidden'; ?>>
            <?php echo isset($_GET['error']) ? htmlspecialchars($_GET['error']) : ''; ?>
        </div>

        <form id="landlordRegisterForm" action="<?php echo BASE_URL; ?>/api/auth/register_landlord_step1.php" method="POST">

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

                <button type="submit" class="lux-btn auth-submit-btn" id="landlordSubmitBtn">
                    Register as Landlord
                </button>

            </div>

            <!-- OTP STEP — hidden until step 1 (above) succeeds -->
            <div class="auth-otp-step" id="authOtpStep" hidden>

                <h2 class="auth-otp-title">Verify Your Email</h2>
                <p class="auth-otp-subtitle">Enter the 6-digit code we sent you.</p>

                <div class="auth-alert auth-alert-error" id="authOtpError" hidden></div>

                <div class="auth-field">
                    <label for="authOtpCode">Verification Code</label>
                    <input type="text" id="authOtpCode" inputmode="numeric" maxlength="6" placeholder="000000">
                </div>

                <button type="button" class="lux-btn auth-submit-btn" id="authOtpVerifyBtn">
                    Verify &amp; Continue
                </button>

                <div class="auth-resend-row">
                    <span id="authOtpTimer">Code expires in 5:00</span>
                    <button type="button" id="authOtpResendBtn" disabled>Resend Code</button>
                </div>

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
<script src="<?php echo BASE_URL; ?>/assets/js/registration-otp-step.js"></script>
<script>
(function () {

    const cfg = window.LUX_CONSENT_CONFIG;
    const form = document.getElementById('landlordRegisterForm');
    const fieldsWrapper = document.querySelector('.auth-form-fields');
    const submitBtn = document.getElementById('landlordSubmitBtn');
    const topError = document.getElementById('registerTopError');

    function showTopError(message) {
        topError.textContent = message;
        topError.hidden = false;
    }

    function hideTopError() {
        topError.hidden = true;
    }

    const otpStep = initRegistrationOtpStep({
        baseUrl: cfg.baseUrl,
        csrfToken: cfg.csrfToken,
        verifyEndpoint: cfg.baseUrl + '/api/auth/verify_registration_otp.php',
        resendEndpoint: cfg.baseUrl + '/api/auth/resend_registration_otp.php',
        detailsSectionEl: fieldsWrapper,
        otpSectionEl: document.getElementById('authOtpStep'),
        otpInputEl: document.getElementById('authOtpCode'),
        otpTimerEl: document.getElementById('authOtpTimer'),
        resendBtnEl: document.getElementById('authOtpResendBtn'),
        verifyBtnEl: document.getElementById('authOtpVerifyBtn'),
        errorEl: document.getElementById('authOtpError')
    });

    form.addEventListener('submit', async (event) => {

        event.preventDefault();
        hideTopError();

        if (window.LuxFormValidation && !LuxFormValidation.validateForm(fieldsWrapper)) {
            return;
        }

        submitBtn.disabled = true;

        const formData = new URLSearchParams(new FormData(form));

        try {

            const response = await fetch(form.action, { method: 'POST', body: formData });
            const data = await response.json();

            if (!data.success) {

                if (data.field) {
                    const fieldEl = form.querySelector(`[name="${data.field}"]`);
                    if (fieldEl) {
                        fieldEl.classList.add('field-invalid');
                    }
                }

                showTopError(data.message || 'Something went wrong.');
                submitBtn.disabled = false;
                return;
            }

            otpStep.enterOtpStep(data.expires_in);

        } catch (error) {

            showTopError('Network error. Please try again.');
            submitBtn.disabled = false;
        }
    });

})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>