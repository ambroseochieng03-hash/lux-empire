(function () {

    const cfg = window.LUX_ADMIN;
    if (!cfg) return;

    const confirmModal = document.getElementById('luxConfirmModal');
    const reasonWrap = confirmModal.querySelector('.lux-confirm-reason-wrap');
    const reasonInput = confirmModal.querySelector('.lux-confirm-reason-input');
    const refWrap = confirmModal.querySelector('.lux-confirm-ref-wrap');
    const refInput = confirmModal.querySelector('.lux-confirm-ref-input');
    const messageEl = confirmModal.querySelector('.lux-confirm-message');
    const acceptBtn = confirmModal.querySelector('.lux-confirm-accept');

    let pendingAction = null;
    let pendingId = null;
    let pendingCard = null;
    let pendingOverrideAmount = null;

    function openConfirm(action, id, card) {
        pendingAction = action;
        pendingId = id;
        pendingCard = card;

        const messages = {
            approve: 'Approve this payment and grant access?',
            reject: 'Reject this payment? The user will be notified.',
            mark_refunded: 'Mark this refund as resolved? The user will be notified.',
            complete_refund: 'Confirm the money has reached the tenant. Enter the M-Pesa reference. The tenant will be notified.',
        };

        messageEl.textContent = messages[action] || 'Confirm this action?';

        reasonWrap.hidden = !(action === 'reject' || action === 'complete_refund');
        reasonInput.placeholder = action === 'reject' ? 'Notes (required for reject)' : 'Notes (optional)';
        reasonInput.value = '';

        refWrap.hidden = action !== 'complete_refund';
        refInput.value = '';
        refInput.classList.remove('field-invalid');
        const refError = refInput.nextElementSibling;
        if (refError && refError.classList.contains('field-error-msg')) {
            refError.hidden = true;
        }

        confirmModal.classList.add('is-open');
        confirmModal.setAttribute('aria-hidden', 'false');
    }

    function closeConfirm() {
        confirmModal.classList.remove('is-open');
        confirmModal.setAttribute('aria-hidden', 'true');
        pendingAction = null;
        pendingId = null;
        pendingCard = null;
    }

    confirmModal.querySelectorAll('[data-confirm-close]').forEach((el) => {
        el.addEventListener('click', closeConfirm);
    });

    document.addEventListener('click', (event) => {
        const btn = event.target.closest('[data-action][data-payment-id], [data-action][data-refund-id]');
        if (!btn) return;

        const card = btn.closest('.lux-entity-card');
        const amountInput = card ? card.querySelector('.lux-payment-amount-override') : null;
        pendingOverrideAmount = amountInput ? amountInput.value : null;

        openConfirm(btn.dataset.action, btn.dataset.paymentId || btn.dataset.refundId, card);
    });

    acceptBtn.addEventListener('click', async () => {

        if (!pendingAction || !pendingId) return;

        const notes = reasonInput.value.trim();
        const mpesaRef = refInput.value.trim().toUpperCase();

        if (pendingAction === 'reject' && !notes) {
            alert('A reason is required to reject a payment.');
            return;
        }

        if (pendingAction === 'complete_refund') {
            if (!window.LuxFormValidation.validateField(refInput)) {
                refInput.focus();
                return;
            }
        }

        // Capture these BEFORE closeConfirm() runs — closeConfirm()
        // resets the pending* variables to null.
        const cardToRemove = pendingCard;
        const actionToSend = pendingAction;
        const idToSend = pendingId;

        acceptBtn.disabled = true;

        try {
            const body = new URLSearchParams({
                csrf_token: cfg.csrfToken,
                action: actionToSend,
                notes: notes,
            });

            if (actionToSend === 'complete_refund') {
                body.append('refund_id', idToSend);
                body.append('mpesa_ref', mpesaRef);
            } else {
                body.append('payment_id', idToSend);
            }

            if (actionToSend === 'approve' && pendingOverrideAmount) {
                body.append('override_amount', pendingOverrideAmount);
            }

            const res = await fetch(`${cfg.baseUrl}/api/admin/payment_action.php`, { method: 'POST', body });
            const data = await res.json();

            acceptBtn.disabled = false;
            closeConfirm();

            if (data.success) {
                if (cardToRemove) cardToRemove.remove();
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