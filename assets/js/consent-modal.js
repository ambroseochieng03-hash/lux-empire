/*
=========================================
LUX EMPIRE — CONSENT MODAL BEHAVIOR
=========================================
Requires window.LUX_CONSENT_CONFIG = { baseUrl, csrfToken, role }
to be set by the page before this script runs.
=========================================
*/

(function () {

    const cfg = window.LUX_CONSENT_CONFIG;

    if (!cfg) {
        return;
    }

    document.addEventListener('DOMContentLoaded', () => {

        const modal = document.getElementById('consentModal');
        const checkbox = document.getElementById('consentCheckbox');
        const declineBtn = document.getElementById('consentDeclineBtn');
        const form = checkbox ? checkbox.closest('form') : null;

        /*
         * IMPORTANT: we lock/blur .auth-form-fields (a sibling of the
         * modal inside the form), NOT the form itself. The modal is
         * also a child of the form (so its checkbox submits with the
         * rest of the fields) — blurring the whole form would blur
         * the modal too, since CSS filter affects an element's full
         * subtree. This was an actual bug in the first version.
         */
        const fieldsWrapper = form ? form.querySelector('.auth-form-fields') : null;

        if (!modal || !checkbox || !form || !fieldsWrapper) {
            return;
        }

        fieldsWrapper.classList.add('consent-locked');
        modal.classList.remove('is-dismissed');

        checkbox.addEventListener('change', () => {
            if (checkbox.checked) {
                modal.classList.add('is-dismissed');
                fieldsWrapper.classList.remove('consent-locked');
            }
        });

        declineBtn.addEventListener('click', () => {

            const formData = new FormData();
            formData.append('role', cfg.role);
            formData.append('csrf_token', cfg.csrfToken);

            const declineUrl = `${cfg.baseUrl}/api/consent/log_decline.php`;

            if (navigator.sendBeacon) {
                navigator.sendBeacon(declineUrl, formData);
            } else {
                // Fallback for browsers without sendBeacon — fire and
                // forget, don't block the redirect on it completing.
                fetch(declineUrl, { method: 'POST', body: formData, keepalive: true });
            }

            window.location.href = cfg.baseUrl + '/';
        });
    });

})();