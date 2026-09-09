/*
=========================================
LUX EMPIRE — "YOU MUST BE ONLINE" MODAL
=========================================
Shared across any page where an action genuinely cannot proceed
offline (forgot-password, registration) and simply informing the
person is more honest than letting a native form POST fail with the
browser's generic offline error.
Requires window.LUX_OFFLINE_MODAL_CONFIG = { baseUrl }.
=========================================
*/

(function () {

    function ensureOfflineModal() {

        if (document.getElementById('luxOfflineRequiredModal')) {
            return document.getElementById('luxOfflineRequiredModal');
        }

        const cfg = window.LUX_OFFLINE_MODAL_CONFIG || { baseUrl: '' };

        const modal = document.createElement('div');
        modal.id = 'luxOfflineRequiredModal';
        modal.className = 'lux-booking-modal';
        modal.setAttribute('aria-hidden', 'true');

        modal.innerHTML = `
            <div class="lux-booking-modal-overlay" data-offline-modal-close></div>
            <div class="lux-booking-modal-box" role="alertdialog" aria-live="assertive">
                <div class="lux-booking-modal-icon">
                    <img src="${cfg.baseUrl}/assets/images/logo.svg" alt="LUX EMPIRE" style="width:48px;height:48px;">
                </div>
                <div class="lux-booking-modal-message">
                    You need to be online to do this. Please check your connection and try again.
                </div>
                <button type="button" class="lux-booking-modal-ok" data-offline-modal-close>OK</button>
            </div>
        `;

        document.body.appendChild(modal);

        modal.addEventListener('click', (event) => {
            if (event.target.hasAttribute('data-offline-modal-close')) {
                closeOfflineModal();
            }
        });

        return modal;
    }

    function showOfflineRequiredModal(message) {

        const modal = ensureOfflineModal();
        const messageEl = modal.querySelector('.lux-booking-modal-message');

        if (message) {
            messageEl.textContent = message;
        }

        modal.classList.add('is-visible');
        modal.setAttribute('aria-hidden', 'false');
    }

    function closeOfflineModal() {

        const modal = document.getElementById('luxOfflineRequiredModal');

        if (!modal) {
            return;
        }

        modal.classList.remove('is-visible');
        modal.setAttribute('aria-hidden', 'true');
    }

    /**
     * Returns true if online. If offline, shows the modal and
     * returns false — so callers can write:
     *   if (!LuxOfflineRequiredModal.check()) return;
     * as the very first line of any submit handler that requires
     * a live connection.
     */
    function checkOnlineOrWarn(message) {

        if (navigator.onLine) {
            return true;
        }

        showOfflineRequiredModal(message);
        return false;
    }

    window.LuxOfflineRequiredModal = {
        show: showOfflineRequiredModal,
        close: closeOfflineModal,
        check: checkOnlineOrWarn
    };

})();
