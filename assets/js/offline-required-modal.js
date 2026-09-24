/*
=========================================
LUX EMPIRE — OFFLINE STATUS + "YOU MUST BE
ONLINE" MODAL
=========================================
One shared modal, two uses:

  1. EXPLICIT, per-action check — call before something that
     genuinely cannot work offline:
        if (!LuxOfflineRequiredModal.check('...message...')) return;

  2. AMBIENT, site-wide notice — this file, loaded on every page via
     includes/footer.php, automatically shows the SAME modal the
     moment the browser goes offline, and closes it again the moment
     it comes back. No per-page wiring needed. Session-scoped so it
     doesn't re-show on every cached page you browse to while
     continuously offline.

Requires window.LUX_OFFLINE_MODAL_CONFIG = { baseUrl }.
=========================================
*/

(function () {

    const AMBIENT_DEFAULT_MESSAGE =
        "You're offline. Pages you've already visited will still open, " +
        "but anything that needs the server — bookings, payments, chat, " +
        "tracking and more — won't work until you're back online.";

    const SESSION_FLAG = 'lux_offline_notice_shown';

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
                <div class="lux-booking-modal-icon" style="display:flex; flex-direction:column; align-items:center; gap:10px;">
                    <img src="${cfg.baseUrl}/assets/images/logo.svg" alt="LUX EMPIRE" style="width:48px;height:48px;">
                    <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="#D4AF37" stroke-width="1.6" aria-hidden="true">
                        <path d="M2 8.5C6 5 18 5 22 8.5" stroke-opacity="0.35"/>
                        <path d="M5 12c4-2.6 10-2.6 14 0" stroke-opacity="0.55"/>
                        <path d="M8.5 15.5c2-1.1 5-1.1 7 0" stroke-opacity="0.8"/>
                        <circle cx="12" cy="19" r="1.3" fill="#D4AF37" stroke="none"/>
                        <line x1="3" y1="3" x2="21" y2="21" stroke="#ff6b6b" stroke-width="1.8"/>
                    </svg>
                </div>
                <div class="lux-booking-modal-message"></div>
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

        messageEl.textContent = message || AMBIENT_DEFAULT_MESSAGE;

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

    /* ===================== AMBIENT SITE-WIDE NOTICE =====================
     * Guarded so this only wires up once even if the script happens to
     * be included more than once on the same page (harmless either
     * way, but no need for duplicate listeners).
     */
    if (!window.__luxOfflineAmbientWired) {
        window.__luxOfflineAmbientWired = true;

        window.addEventListener('offline', () => {
            showOfflineRequiredModal(AMBIENT_DEFAULT_MESSAGE);
            try { sessionStorage.setItem(SESSION_FLAG, '1'); } catch (e) {}
        });

        window.addEventListener('online', () => {
            closeOfflineModal();
            try { sessionStorage.removeItem(SESSION_FLAG); } catch (e) {}
        });

        document.addEventListener('DOMContentLoaded', () => {
            let alreadyShownThisSession = false;
            try { alreadyShownThisSession = sessionStorage.getItem(SESSION_FLAG) === '1'; } catch (e) {}

            if (!navigator.onLine && !alreadyShownThisSession) {
                showOfflineRequiredModal(AMBIENT_DEFAULT_MESSAGE);
                try { sessionStorage.setItem(SESSION_FLAG, '1'); } catch (e) {}
            }
        });
    }

})();