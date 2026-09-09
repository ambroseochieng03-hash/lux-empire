/*
=========================================
LUX EMPIRE — SHARED REGISTRATION OTP STEP
=========================================
Used by auth/register_landlord.php and auth/register_driver.php.
The tenant modal (assets/js/tenant-register-modal.js) has its own
separate implementation of the same idea — kept separate rather than
merged into this, to avoid touching an already-working flow while
retrofitting OTP onto landlord/driver.

Usage: call initRegistrationOtpStep(config) once per page, where
config = {
    baseUrl, csrfToken,           // from window.LUX_CONSENT_CONFIG or similar
    verifyEndpoint, resendEndpoint,
    detailsSectionEl, otpSectionEl,
    otpInputEl, otpTimerEl, resendBtnEl, verifyBtnEl, errorEl
}
=========================================
*/

function initRegistrationOtpStep(config) {

    let otpExpiryTimer = null;
    let resendCooldownTimer = null;

    function showError(message) {
        config.errorEl.textContent = message;
        config.errorEl.hidden = false;
    }

    function hideError() {
        config.errorEl.hidden = true;
    }

    async function postForm(url, fields) {
        const formData = new URLSearchParams();
        Object.keys(fields).forEach((key) => formData.append(key, fields[key]));

        const response = await fetch(url, { method: 'POST', body: formData });

        try {
            return await response.json();
        } catch (e) {
            return { success: false, message: 'Unexpected server response.' };
        }
    }

    function startOtpExpiryCountdown(seconds) {

        if (otpExpiryTimer) clearInterval(otpExpiryTimer);

        let remaining = seconds;

        function tick() {
            const m = Math.floor(remaining / 60);
            const s = String(remaining % 60).padStart(2, '0');
            config.otpTimerEl.textContent = remaining > 0
                ? `Code expires in ${m}:${s}`
                : 'Code expired — request a new one';

            if (remaining <= 0) {
                clearInterval(otpExpiryTimer);
                return;
            }
            remaining -= 1;
        }

        tick();
        otpExpiryTimer = setInterval(tick, 1000);
    }

    function startResendCooldown(seconds) {

        config.resendBtnEl.disabled = true;

        if (resendCooldownTimer) clearInterval(resendCooldownTimer);

        let remaining = seconds;

        function tick() {
            if (remaining <= 0) {
                clearInterval(resendCooldownTimer);
                config.resendBtnEl.disabled = false;
                config.resendBtnEl.textContent = 'Resend Code';
                return;
            }
            config.resendBtnEl.textContent = `Resend Code (${remaining}s)`;
            remaining -= 1;
        }

        tick();
        resendCooldownTimer = setInterval(tick, 1000);
    }

    // Called by the page once step 1 (create-pending-account) succeeds.
    function enterOtpStep(expiresIn) {
        config.detailsSectionEl.hidden = true;
        config.otpSectionEl.hidden = false;
        startOtpExpiryCountdown(expiresIn || 300);
        startResendCooldown(45);
    }

    config.verifyBtnEl.addEventListener('click', async () => {

        if (window.LuxOfflineRequiredModal && !window.LuxOfflineRequiredModal.check('You need to be online to verify your code. Please check your connection and try again.')) {
            return;
        }

        hideError();

        const code = config.otpInputEl.value.trim();
        config.verifyBtnEl.disabled = true;

        const data = await postForm(config.verifyEndpoint, {
            code: code,
            csrf_token: config.csrfToken
        });

        config.verifyBtnEl.disabled = false;

        if (!data.success) {
            showError(data.message || 'Something went wrong.');
            return;
        }

        window.location.href = data.redirect || config.baseUrl + '/login';
    });

    config.resendBtnEl.addEventListener('click', async () => {

        if (window.LuxOfflineRequiredModal && !window.LuxOfflineRequiredModal.check('You need to be online to resend a code. Please check your connection and try again.')) {
            return;
        }

        hideError();

        const data = await postForm(config.resendEndpoint, {
            csrf_token: config.csrfToken
        });

        if (!data.success) {
            showError(data.message || 'Something went wrong.');
            if (typeof data.retry_after === 'number' && data.retry_after > 0) {
                startResendCooldown(data.retry_after);
            }
            return;
        }

        startOtpExpiryCountdown(data.expires_in || 300);
        startResendCooldown(data.resend_cooldown || 45);
    });

    return { enterOtpStep, showError, hideError };
}
