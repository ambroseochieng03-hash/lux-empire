/*
=========================================
LUX EMPIRE — TENANT REGISTRATION MODAL
=========================================
Requires window.LUX_TENANT_REGISTER_CONFIG = { baseUrl, csrfToken,
googleClientId } to be set by the page before this script runs.
=========================================
*/

(function () {

    const cfg = window.LUX_TENANT_REGISTER_CONFIG;

    if (!cfg) {
        return;
    }

    let otpCountdownTimer = null;

    document.addEventListener('DOMContentLoaded', () => {

        const modal = document.getElementById('tenantRegisterModal');

        if (!modal) {
            return;
        }

        const steps = {
            details: document.getElementById('trStepDetails'),
            otp: document.getElementById('trStepOtp'),
            googlePassword: document.getElementById('trStepGooglePassword')
        };

        function showStep(name) {
            Object.keys(steps).forEach((key) => {
                steps[key].hidden = key !== name;
            });
        }

        function showError(elId, message) {
            const el = document.getElementById(elId);
            el.textContent = message;
            el.hidden = false;
        }

        function hideError(elId) {
            document.getElementById(elId).hidden = true;
        }

        function openModal() {
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            showStep('details');
        }

        function closeModal() {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            if (otpCountdownTimer) {
                clearInterval(otpCountdownTimer);
            }
        }

        // Any element with data-open-tenant-register opens this modal.
        document.addEventListener('click', (event) => {
            if (event.target.closest('[data-open-tenant-register]')) {
                event.preventDefault();
                openModal();
            }
        });

        // Exposed so other scripts (e.g. guest-browse.js) can open this
        // modal programmatically, not just via a data-attribute click.
        window.openTenantRegisterModal = openModal;

        /*
         * If a guest was mid-action (Book Now / truck request) before
         * registering, that intent is sitting in sessionStorage under
         * 'luxPendingGuestAction' (set by guest-browse.js). Once
         * registration completes, fire it automatically instead of
         * just dropping the user on their dashboard.
         */
        async function completePendingGuestActionThenRedirect(fallbackRedirect) {

            const pendingRaw = sessionStorage.getItem('luxPendingGuestAction');

            if (!pendingRaw) {
                window.location.href = fallbackRedirect;
                return;
            }

            let pending;

            try {
                pending = JSON.parse(pendingRaw);
            } catch (e) {
                sessionStorage.removeItem('luxPendingGuestAction');
                window.location.href = fallbackRedirect;
                return;
            }

            sessionStorage.removeItem('luxPendingGuestAction');

            if (pending.type === 'book_house') {

                try {
                    await postForm(`${cfg.baseUrl}/api/houses/book_house.php`, {
                        house_id: pending.houseId,
                        csrf_token: cfg.csrfToken
                    });
                } catch (e) {
                    // Not fatal — they can just click Book Now again
                    // from their now-authenticated session.
                }

                window.location.href = cfg.baseUrl + '/tenant/my-bookings';
                return;
            }

            if (pending.type === 'request_truck') {

                /*
                 * api/trucks/request_truck.php is a classic redirect-
                 * based endpoint (not JSON) — replicate a normal form
                 * POST rather than rewriting that endpoint just for
                 * this flow. The session is already authenticated at
                 * this point, so auth_check.php there will pass.
                 */
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = `${cfg.baseUrl}/api/trucks/request_truck.php`;
                form.style.display = 'none';

                Object.keys(pending.fields).forEach((key) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = key;
                    input.value = pending.fields[key];
                    form.appendChild(input);
                });

                document.body.appendChild(form);
                form.submit();
                return;
            }

            window.location.href = fallbackRedirect;
        }

        modal.querySelectorAll('[data-tr-close]').forEach((el) => {
            el.addEventListener('click', closeModal);
        });

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

        function startOtpCountdown(seconds) {

            const timerEl = document.getElementById('trOtpTimer');
            const resendBtn = document.getElementById('trResendOtp');

            resendBtn.disabled = true;

            if (otpCountdownTimer) {
                clearInterval(otpCountdownTimer);
            }

            let remaining = seconds;

            function tick() {
                const m = Math.floor(remaining / 60);
                const s = String(remaining % 60).padStart(2, '0');
                timerEl.textContent = remaining > 0
                    ? `Code expires in ${m}:${s}`
                    : 'Code expired';

                if (remaining <= 0) {
                    clearInterval(otpCountdownTimer);
                    resendBtn.disabled = false;
                    return;
                }

                remaining -= 1;
            }

            tick();
            otpCountdownTimer = setInterval(tick, 1000);
        }

        // ---- STEP 1: submit details ----
        document.getElementById('trSubmitDetails').addEventListener('click', async () => {

            hideError('trDetailsError');

            const fullName = document.getElementById('trFullName').value.trim();
            const email = document.getElementById('trEmail').value.trim();
            const phone = document.getElementById('trPhone').value.trim();
            const password = document.getElementById('trPassword').value;

            const btn = document.getElementById('trSubmitDetails');
            btn.disabled = true;

            const data = await postForm(`${cfg.baseUrl}/api/auth/register_tenant_step1.php`, {
                full_name: fullName,
                email: email,
                phone: phone,
                password: password,
                csrf_token: cfg.csrfToken
            });

            btn.disabled = false;

            if (!data.success) {
                showError('trDetailsError', data.message || 'Something went wrong.');
                return;
            }

            showStep('otp');
            startOtpCountdown(data.expires_in || 300);
        });

        // ---- STEP 2a: verify OTP ----
        document.getElementById('trSubmitOtp').addEventListener('click', async () => {

            hideError('trOtpError');

            const code = document.getElementById('trOtpCode').value.trim();
            const btn = document.getElementById('trSubmitOtp');
            btn.disabled = true;

            const data = await postForm(`${cfg.baseUrl}/api/auth/verify_tenant_otp.php`, {
                code: code,
                csrf_token: cfg.csrfToken
            });

            btn.disabled = false;

            if (!data.success) {
                showError('trOtpError', data.message || 'Something went wrong.');
                return;
            }

            completePendingGuestActionThenRedirect(data.redirect || cfg.baseUrl + '/tenant');
        });

        // ---- Resend OTP ----
        document.getElementById('trResendOtp').addEventListener('click', async () => {

            hideError('trOtpError');

            const data = await postForm(`${cfg.baseUrl}/api/auth/resend_tenant_otp.php`, {
                csrf_token: cfg.csrfToken
            });

            if (!data.success) {
                showError('trOtpError', data.message || 'Something went wrong.');
                return;
            }

            startOtpCountdown(data.expires_in || 300);
        });

        // ---- STEP 2b: Google — set password ----
        document.getElementById('trSubmitGooglePassword').addEventListener('click', async () => {

            hideError('trGooglePasswordError');

            const password = document.getElementById('trGooglePassword').value;
            const btn = document.getElementById('trSubmitGooglePassword');
            btn.disabled = true;

            const data = await postForm(`${cfg.baseUrl}/api/auth/google_set_password.php`, {
                password: password,
                csrf_token: cfg.csrfToken
            });

            btn.disabled = false;

            if (!data.success) {
                showError('trGooglePasswordError', data.message || 'Something went wrong.');
                return;
            }

            completePendingGuestActionThenRedirect(data.redirect || cfg.baseUrl + '/tenant');
        });

        // ---- Google Identity Services ----
        function handleGoogleCredentialResponse(response) {

            hideError('trDetailsError');

            postForm(`${cfg.baseUrl}/api/auth/google_signup.php`, {
                id_token: response.credential,
                csrf_token: cfg.csrfToken
            }).then((data) => {

                if (!data.success) {
                    showError('trDetailsError', data.message || 'Something went wrong.');
                    return;
                }

                if (data.needs_password) {
                    showStep('googlePassword');
                    return;
                }

                completePendingGuestActionThenRedirect(data.redirect || cfg.baseUrl + '/tenant');
            });
        }

        if (cfg.googleClientId && window.google && window.google.accounts) {

            google.accounts.id.initialize({
                client_id: cfg.googleClientId,
                callback: handleGoogleCredentialResponse
            });

            google.accounts.id.renderButton(
                document.getElementById('googleSignInButton'),
                { theme: 'outline', size: 'large', width: 320 }
            );
        }
    });

})();