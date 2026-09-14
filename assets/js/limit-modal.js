/*
=========================================
LUX EMPIRE — PLAN LIMIT MODAL
=========================================
Shown when a landlord hits a plan limit (listings/images/video) on
add or edit. Opens the payment modal on "Upgrade to Pro".

Usage:
  window.LuxLimitModal.show({
      message: "You've reached the 3-listing limit for your plan.",
      onUpgrade: function () { ... },   // called when the person clicks Upgrade
      onDismiss: function () { ... }    // optional — called on "Not now" / close
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
                    <button type="button" class="lux-btn lux-limit-modal-upgrade">Upgrade to Pro — KES 499/mo</button>
                    <button type="button" class="lux-limit-modal-dismiss">Not now</button>
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

        modalEl.querySelector('.lux-limit-modal-message').textContent =
            config.message || 'You have reached a limit on your current plan.';

        const upgradeBtn = modalEl.querySelector('.lux-limit-modal-upgrade');
        const dismissBtn = modalEl.querySelector('.lux-limit-modal-dismiss');

        // Fresh clones so a previous show()'s listeners never double-fire.
        const newUpgradeBtn = upgradeBtn.cloneNode(true);
        upgradeBtn.parentNode.replaceChild(newUpgradeBtn, upgradeBtn);

        const newDismissBtn = dismissBtn.cloneNode(true);
        dismissBtn.parentNode.replaceChild(newDismissBtn, dismissBtn);

        newUpgradeBtn.addEventListener('click', () => {
            close();
            if (typeof config.onUpgrade === 'function') config.onUpgrade();
        });

        newDismissBtn.addEventListener('click', () => {
            close();
            if (typeof config.onDismiss === 'function') config.onDismiss();
        });

        modalEl.classList.add('is-open');
    }

    window.LuxLimitModal = { show, close };

})();
