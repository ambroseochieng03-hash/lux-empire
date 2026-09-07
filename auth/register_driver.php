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

        <div class="auth-alert auth-alert-error" id="registerTopError" <?php echo isset($_GET['error']) ? '' : 'hidden'; ?>>
            <?php echo isset($_GET['error']) ? htmlspecialchars($_GET['error']) : ''; ?>
        </div>

        <form id="driverRegisterForm" action="<?php echo BASE_URL; ?>/api/auth/register_driver_step1.php" method="POST">

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

                <!-- IDENTITY TYPE — choose one, mutually exclusive -->
                <div class="auth-field">
                    <label>Identification Type</label>
                    <div class="auth-radio-row">
                        <label class="auth-radio-option">
                            <input type="radio" name="identity_type" value="national_id" id="identityTypeNationalId" checked>
                            National ID
                        </label>
                        <label class="auth-radio-option">
                            <input type="radio" name="identity_type" value="license" id="identityTypeLicense">
                            Driving License
                        </label>
                    </div>
                </div>

                <!--
                    ONE field, meaning changes based on the radio above.
                    data-validate starts as "national_id" (matches the
                    default-checked radio) and is swapped by JS below
                    whenever the selection changes.
                -->
                <div class="auth-field">
                    <label for="identityValue" id="identityValueLabel">National ID Number</label>
                    <input type="text" id="identityValue" name="identity_value" data-validate="national_id" required placeholder="7-9 digit ID number">
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

                <button type="submit" class="lux-btn auth-submit-btn" id="driverSubmitBtn">
                    Register as Driver
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
        role: "driver"
    };
</script>
<script src="<?php echo BASE_URL; ?>/assets/js/form-validation.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/consent-modal.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/registration-otp-step.js"></script>
<script>
(function () {

    const cfg = window.LUX_CONSENT_CONFIG;
    const form = document.getElementById('driverRegisterForm');
    const fieldsWrapper = document.querySelector('.auth-form-fields');
    const submitBtn = document.getElementById('driverSubmitBtn');
    const topError = document.getElementById('registerTopError');

    function showTopError(message) {
        topError.textContent = message;
        topError.hidden = false;
    }

    function hideTopError() {
        topError.hidden = true;
    }

    /*
     * IDENTITY TYPE TOGGLE — the ONE shared input's label,
     * placeholder, and live-validation rule all swap based on which
     * radio is selected. Value + any error state is cleared on
     * switch so a half-typed National ID doesn't get validated as a
     * license number or vice versa.
     */
    const identityInput = document.getElementById('identityValue');
    const identityLabel = document.getElementById('identityValueLabel');

    function clearIdentityFieldState() {
        identityInput.value = '';
        identityInput.classList.remove('field-invalid');
        const errorEl = identityInput.nextElementSibling;
        if (errorEl && errorEl.classList.contains('field-error-msg')) {
            errorEl.hidden = true;
        }
    }

    document.querySelectorAll('input[name="identity_type"]').forEach((radio) => {
        radio.addEventListener('change', () => {

            clearIdentityFieldState();

            if (radio.value === 'national_id') {
                identityInput.dataset.validate = 'national_id';
                identityLabel.textContent = 'National ID Number';
                identityInput.placeholder = '7-9 digit ID number';
            } else {
                identityInput.dataset.validate = 'driver_license';
                identityLabel.textContent = 'Driving License Number';
                identityInput.placeholder = 'Letters/numbers, 5-15 characters';
            }
        });
    });

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