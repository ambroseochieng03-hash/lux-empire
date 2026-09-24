<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/csrf.php';

Session::start();

if (Session::isAuthenticated()) {

    $currentUser = Session::user();

    $roleDashboardRoutes = [
        'tenant'   => '/tenant',
        'landlord' => '/landlord',
        'driver'   => '/driver',
        'admin'    => '/admin',
    ];

    $dashboardPath = $roleDashboardRoutes[$currentUser['role'] ?? ''] ?? null;

    if ($dashboardPath !== null) {
        header('Location: ' . BASE_URL . $dashboardPath);
        exit;
    }
}

$csrfToken = Csrf::token();

require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/auth-forms.css">

<section class="auth-hero">

    <div class="lux-card auth-card">

        <div class="auth-header">
            <h1 class="auth-title">Enter The Empire</h1>
            <p class="auth-subtitle">Access your luxury world of property, movement, and prestige.</p>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="auth-alert auth-alert-success">
                <?php echo htmlspecialchars($_GET['success']); ?>
            </div>
        <?php endif; ?>

        <div class="auth-alert auth-alert-error" id="loginTopError" hidden></div>

        <form id="loginForm" action="<?php echo BASE_URL; ?>/login-handler" method="POST">

            <div class="auth-form-fields" id="loginFields">

                <div class="auth-field">
                    <label for="loginEmail">Email Address</label>
                    <input type="email" id="loginEmail" name="email" required
                           placeholder="Enter your empire email">
                </div>

                <div class="auth-field">
                    <label for="loginPassword">Password</label>
                    <input type="password" id="loginPassword" name="password" required
                           placeholder="Your secure empire key">
                </div>

                <div class="auth-field" id="loginCaptchaField" hidden>
                    <div id="loginCaptchaWidget"></div>
                </div>

                <button type="submit" class="lux-btn auth-submit-btn" id="loginSubmitBtn">
                    <i class="fa-solid fa-right-to-bracket"></i> Enter Now
                </button>

            </div>

            <!-- NEW-DEVICE OTP STEP — hidden unless login_handler.php
                 signals this device isn't trusted yet. -->
            <div class="auth-otp-step" id="authOtpStep" hidden>

                <h2 class="auth-otp-title">Verify This Device</h2>
                <p class="auth-otp-subtitle">
                    We don't recognize this device. Enter the 6-digit code we sent you.
                </p>

                <div class="auth-alert auth-alert-error" id="authOtpError" hidden></div>

                <div class="auth-field">
                    <label for="authOtpCode">Verification Code</label>
                    <input type="text" id="authOtpCode" inputmode="numeric" maxlength="6" placeholder="000000">
                </div>

                <button type="button" class="lux-btn auth-submit-btn" id="authOtpVerifyBtn">
                    Verify &amp; Enter
                </button>

                <div class="auth-resend-row">
                    <span id="authOtpTimer">Code expires in 5:00</span>
                    <button type="button" id="authOtpResendBtn" disabled>Resend Code</button>
                </div>

            </div>

        </form>

        <div class="auth-footer-link">
            <p>
                <a href="<?php echo BASE_URL; ?>/forgot-password">
                    Forgotten your Empire key?
                </a>
            </p>

            <p>
                New to the Empire?
                <a href="#" data-open-role-select>Join Here</a>
            </p>
        </div>

    </div>

</section>

<script src="https://challenges.cloudflare.com/turnstile/api.js" async defer></script>
<script src="<?php echo BASE_URL; ?>/assets/js/registration-otp-step.js"></script>
<script>
(function () {

    const baseUrl = "<?php echo BASE_URL; ?>";
    let csrfToken = "<?php echo htmlspecialchars($csrfToken); ?>";

    let loginCaptchaWidgetId = null;
    let loginCaptchaToken = null;

    function ensureCaptchaRendered() {
        if (loginCaptchaWidgetId !== null) return;
        if (!window.turnstile) {
            setTimeout(ensureCaptchaRendered, 200);
            return;
        }
        loginCaptchaWidgetId = turnstile.render('#loginCaptchaWidget', {
            sitekey: "<?php echo htmlspecialchars(TURNSTILE_SITE_KEY, ENT_QUOTES); ?>",
            callback: function (token) { loginCaptchaToken = token; }
        });
    }

    const form = document.getElementById('loginForm');
    const fieldsWrapper = document.getElementById('loginFields');
    const submitBtn = document.getElementById('loginSubmitBtn');
    const topError = document.getElementById('loginTopError');

    function showTopError(message) {
        topError.textContent = message;
        topError.hidden = false;
    }

    function hideTopError() {
        topError.hidden = true;
    }

    const otpStep = initRegistrationOtpStep({
        baseUrl: baseUrl,
        csrfToken: csrfToken,
        verifyEndpoint: baseUrl + '/api/auth/verify_login_otp.php',
        resendEndpoint: baseUrl + '/api/auth/resend_login_otp.php',
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
        submitBtn.disabled = true;

        const formData = new URLSearchParams(new FormData(form));
        formData.set('csrf_token', csrfToken);

        if (loginCaptchaToken) {
            formData.set('captcha_token', loginCaptchaToken);
        }

        try {

            const response = await fetch(form.action, { method: 'POST', body: formData });
            const data = await response.json();

            if (!data.success) {

                if (data.requires_captcha) {
                    document.getElementById('loginCaptchaField').hidden = false;
                    ensureCaptchaRendered();
                }

                showTopError(data.message || 'Login failed.');
                submitBtn.disabled = false;
                return;
            }

            if (data.needs_otp) {
                otpStep.enterOtpStep(data.expires_in);
                submitBtn.disabled = false;
                return;
            }

            window.location.href = data.redirect || baseUrl + '/';

        } catch (error) {

            /*
             * The request never reached the server at all — genuinely
             * different from a wrong password, so it gets the branded
             * offline modal instead of the inline error line. We no
             * longer pre-check navigator.onLine before attempting: that
             * flag reflects "is there any network interface," not "can
             * this server be reached," and pre-checking it was blocking
             * logins — including trusted-device ones that would have
             * succeeded fine over a local connection — before they were
             * even tried. Always attempting first is both more correct
             * and lets a trusted device log in instantly whenever the
             * server is actually reachable, with no OTP delay.
             */
            window.LuxOfflineRequiredModal.show('You need to be online to log in. Please check your connection and try again.');
            submitBtn.disabled = false;
        }
    });

})();
</script>

<?php require_once '../includes/footer.php'; ?>