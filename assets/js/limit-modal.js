/*
=========================================
LUX EMPIRE — PLAN LIMIT MODAL
=========================================
Shown when a landlord hits a plan limit (listings/images/video) on
add or edit.

Two modes:
  - 'upgrade' (default) — Free-tier landlord. Shows an "Upgrade to
    Pro" button that opens the payment modal.
  - 'manage' — Pro-tier landlord who has hit their (higher) cap.
    Upgrading would do nothing for them, so instead this offers to
    take them to Manage Listings so they can delete or edit an
    existing card.

Usage:
  window.LuxLimitModal.show({
      message: "You've reached the 3-listing limit for your plan.",
      mode: 'upgrade',                  // or 'manage'
      onUpgrade: function () { ... },   // 'upgrade' mode only
      onPrimary: function () { ... },   // 'manage' mode only, optional
                                         // (defaults to navigating to
                                         // manage_houses.php)
      onDismiss: function () { ... }    // optional — called on close
  });
=========================================
*/

(function () {

    let modalEl = null;

    function ensureModal() {
        if (modalEl) return modalEl;

        modalEl = document.createElement('div');
        modalEl.className = 'lux-limit-modal-overlay';
        modalEl.innerHTML = `
            <div class="lux-limit-modal">
                <div class="lux-limit-modal-message"></div>
                <div class="lux-limit-modal-actions">
                    <button type="button" class="lux-btn lux-limit-modal-upgrade"></button>
                    <button type="button" class="lux-limit-modal-dismiss"></button>
                </div>
            </div>
        `;
        document.body.appendChild(modalEl);

        modalEl.addEventListener('click', (e) => {
            if (e.target === modalEl) close();
        });

        return modalEl;
    }

    function close() {
        if (modalEl) modalEl.classList.remove('is-open');
    }

    function show(config) {
        ensureModal();

        const mode = config.mode || 'upgrade';

        modalEl.querySelector('.lux-limit-modal-message').textContent =
            config.message || 'You have reached a limit on your current plan.';

        const upgradeBtn = modalEl.querySelector('.lux-limit-modal-upgrade');
        const dismissBtn = modalEl.querySelector('.lux-limit-modal-dismiss');

        // Fresh clones so a previous show()'s listeners never double-fire.
        const newUpgradeBtn = upgradeBtn.cloneNode(true);
        upgradeBtn.parentNode.replaceChild(newUpgradeBtn, upgradeBtn);

        const newDismissBtn = dismissBtn.cloneNode(true);
        dismissBtn.parentNode.replaceChild(newDismissBtn, dismissBtn);

        if (mode === 'manage') {

            // Already Pro and hit a cap upgrading can't fix — never
            // show an "Upgrade" button here, it would just take their
            // money for nothing.
            newUpgradeBtn.textContent = 'Manage My Listings';
            newUpgradeBtn.addEventListener('click', () => {
                close();
                if (typeof config.onPrimary === 'function') {
                    config.onPrimary();
                } else {
                    const base = (window.LUX_PAYMENT_CONFIG && window.LUX_PAYMENT_CONFIG.baseUrl) || '';
                    window.location.href = base + '/dashboard/landlord/manage_houses.php';
                }
            });
            newDismissBtn.textContent = 'Close';

        } else {

            newUpgradeBtn.textContent = 'Upgrade to Pro — KES 499/mo';
            newUpgradeBtn.addEventListener('click', () => {
                close();
                if (typeof config.onUpgrade === 'function') config.onUpgrade();
            });
            newDismissBtn.textContent = 'Not now';
        }

        newDismissBtn.addEventListener('click', () => {
            close();
            if (typeof config.onDismiss === 'function') config.onDismiss();
        });

        modalEl.classList.add('is-open');
    }

    window.LuxLimitModal = { show, close };

})();