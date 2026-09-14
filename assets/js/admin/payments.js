(function () {

    const cfg = window.LUX_ADMIN;
    if (!cfg) return;

    const confirmModal = document.getElementById('luxConfirmModal');
    const reasonWrap = confirmModal.querySelector('.lux-confirm-reason-wrap');
    const reasonInput = confirmModal.querySelector('.lux-confirm-reason-input');
    const messageEl = confirmModal.querySelector('.lux-confirm-message');
    const acceptBtn = confirmModal.querySelector('.lux-confirm-accept');

    let pendingAction = null;
    let pendingPaymentId = null;
    let pendingCard = null;

    function openConfirm(action, paymentId, card) {
        pendingAction = action;
        pendingPaymentId = paymentId;
        pendingCard = card;

        const messages = {
            approve: 'Approve this payment and grant access?',
            reject: 'Reject this payment? The user will be notified.',
            mark_refunded: 'Mark this refund as resolved? The user will be notified.',
        };

        messageEl.textContent = messages[action] || 'Confirm this action?';
        reasonWrap.hidden = action !== 'reject';
        reasonInput.value = '';

        confirmModal.classList.add('is-open');
        confirmModal.setAttribute('aria-hidden', 'false');
    }

    function closeConfirm() {
        confirmModal.classList.remove('is-open');
        confirmModal.setAttribute('aria-hidden', 'true');
        pendingAction = null;
        pendingPaymentId = null;
        pendingCard = null;
    }

    confirmModal.querySelectorAll('[data-confirm-close]').forEach((el) => {
        el.addEventListener('click', closeConfirm);
    });

    document.addEventListener('click', (event) => {
        const btn = event.target.closest('[data-action][data-payment-id]');
        if (!btn) return;

        const card = btn.closest('.lux-entity-card');
        openConfirm(btn.dataset.action, btn.dataset.paymentId, card);
    });

    acceptBtn.addEventListener('click', async () => {

        if (!pendingAction || !pendingPaymentId) return;

        const notes = reasonInput.value.trim();

        if (pendingAction === 'reject' && !notes) {
            alert('A reason is required to reject a payment.');
            return;
        }

        acceptBtn.disabled = true;

        try {
            const body = new URLSearchParams({
                csrf_token: cfg.csrfToken,
                payment_id: pendingPaymentId,
                action: pendingAction,
                notes: notes,
            });

            const res = await fetch(`${cfg.baseUrl}/api/admin/payment_action.php`, { method: 'POST', body });
            const data = await res.json();

            acceptBtn.disabled = false;
            closeConfirm();

            if (data.success) {
                if (pendingCard) pendingCard.remove();
            } else {
                alert(data.message || 'Something went wrong.');
            }

        } catch (e) {
            acceptBtn.disabled = false;
            closeConfirm();
            alert('Network error. Please try again.');
        }
    });

})();
