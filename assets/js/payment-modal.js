/*
=========================================
LUX EMPIRE — PAYMENT MODAL
=========================================
Reusable STK Push flow: collect phone -> initiate_payment.php ->
poll check_payment_status.php -> success/fail.

Usage:
  window.LuxPayment.open({
      purpose: 'landlord_pro',       // 'landlord_pro' | 'booking_fee' | 'driver_wallet_topup'
      bookingId: null,               // required for booking_fee
      amount: null,                  // required (user-entered) for driver_wallet_topup
      amountLabel: 'KES 499',        // display only, shown before phone entry
      onSuccess: function () {}      // called once payment confirms as completed
  });
=========================================
*/

(function () {

    const POLL_INTERVAL_MS = 3000;
    const POLL_TIMEOUT_MS = 180000; // 3 minutes — realistic time to notice and enter the M-Pesa PIN

    let modalEl = null;
    let pollTimer = null;
    let pollDeadline = null;

    function ensureModal() {
        if (modalEl) return modalEl;

        modalEl = document.createElement('div');
        modalEl.className = 'lux-payment-modal-overlay';
        modalEl.innerHTML = `
            <div class="lux-payment-modal">
                <button type="button" class="lux-payment-modal-close" aria-label="Close">×</button>
                <div class="lux-payment-modal-body"></div>
            </div>
        `;
        document.body.appendChild(modalEl);

        modalEl.querySelector('.lux-payment-modal-close').addEventListener('click', close);
        modalEl.addEventListener('click', (e) => {
            if (e.target === modalEl) close();
        });

        return modalEl;
    }

    function close() {
        if (pollTimer) clearTimeout(pollTimer);
        if (modalEl) modalEl.classList.remove('is-open');
    }

    function renderPhoneStep(config) {
        const body = modalEl.querySelector('.lux-payment-modal-body');

        const amountRow = config.purpose === 'driver_wallet_topup'
            ? `<label class="lux-payment-label">Amount (KES)
                 <input type="number" id="luxPaymentAmount" class="lux-payment-input" min="100" max="50000" placeholder="e.g. 1000">
               </label>`
            : `<div class="lux-payment-amount-display">${config.amountLabel || ''}</div>`;

        body.innerHTML = `
            <h3 class="lux-payment-title">${config.title || 'Pay with M-Pesa'}</h3>
            ${config.description ? `<p class="lux-payment-description">${config.description}</p>` : ''}
            ${amountRow}
            <label class="lux-payment-label">M-Pesa Phone Number
                <input type="tel" id="luxPaymentPhone" class="lux-payment-input" placeholder="07XX XXX XXX" value="${config.defaultPhone || ''}">
            </label>
            <div class="lux-payment-error" id="luxPaymentError" hidden></div>
            <button type="button" class="lux-btn lux-payment-pay-btn" id="luxPaymentPayBtn" style="width:100%;">
                Pay Now
            </button>
            <div style="text-align:center; color:var(--gray); margin:14px 0; font-size:0.85rem;">— or —</div>
            <button type="button" class="lux-btn lux-payment-retry-btn" id="luxPaymentPaybillTrigger" style="width:100%;">
                Already Paid via Paybill? Enter Code
            </button>
        `;

        body.querySelector('#luxPaymentPayBtn').addEventListener('click', () => initiate(config));

        body.querySelector('#luxPaymentPaybillTrigger').addEventListener('click', () => {

            const paybillAmountInput = modalEl.querySelector('#luxPaymentAmount');

            // Amount is OPTIONAL going into the Paybill screen now —
            // if the driver already paid an arbitrary sum outside
            // the app, they may not know/want to re-type it; the
            // server reads it straight out of the pasted M-Pesa
            // message instead. If they DID fill the field (e.g. they
            // were about to use the STK "Pay Now" flow and switched
            // their mind), carry that value over as a convenience.
            if (paybillAmountInput && paybillAmountInput.value) {
                config.amount = parseFloat(paybillAmountInput.value);
            }

            renderPaybillStep(config);
        });
    }

    function renderPaybillStep(config) {
        const body = modalEl.querySelector('.lux-payment-modal-body');
        const paybill = window.LUX_PAYMENT_PAYBILL || '—';

        // amountLabel covers landlord_pro/booking_fee (fixed, server-set
        // prices); config.amount covers driver_wallet_topup (the figure
        // the person just typed in, captured by the trigger above).
        const displayAmount = config.amountLabel
            || (config.amount ? `KES ${config.amount.toLocaleString()}` : 'Will be read from your M-Pesa message');

        body.innerHTML = `
            <h3 class="lux-payment-title">Pay via Paybill</h3>
            <p class="lux-payment-description">
                Paybill: <strong>${paybill}</strong><br>
                Account Number: <strong>your phone number</strong><br>
                Amount: <strong>${displayAmount}</strong><br><br>
                After paying, paste the M-Pesa confirmation message (or just the code) below.
            </p>
            <textarea id="luxPaybillReceiptInput" class="lux-payment-input" rows="3" placeholder="e.g. UIEQ46JHI7 Confirmed..."></textarea>
            <div class="lux-payment-error" id="luxPaybillError" hidden></div>
            <button type="button" class="lux-btn" id="luxPaybillSubmitBtn" style="width:100%; margin-top:10px;">Submit</button>
            <button type="button" class="lux-btn lux-payment-retry-btn" id="luxPaybillBackBtn" style="width:100%; margin-top:8px;">Back</button>
        `;

        body.querySelector('#luxPaybillBackBtn').addEventListener('click', () => renderPhoneStep(config));

        body.querySelector('#luxPaybillSubmitBtn').addEventListener('click', async () => {
            const input = document.getElementById('luxPaybillReceiptInput').value.trim();
            const errorEl = document.getElementById('luxPaybillError');

            if (!input) {
                errorEl.textContent = 'Paste the M-Pesa message or code.';
                errorEl.hidden = false;
                return;
            }

            renderWaitingStep('Verifying...');

            try {
                const payload = {
                    csrf_token: window.LUX_PAYMENT_CONFIG.csrfToken,
                    purpose: config.purpose,
                    receipt_input: input,
                };
                if (config.houseId) payload.house_id = config.houseId;
                if (config.amount) payload.amount = config.amount;

                const res = await fetch(window.LUX_PAYMENT_CONFIG.baseUrl + '/api/payments/submit_paybill_payment.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                const data = await res.json();

                if (data.success) {
                    renderResultStep(!data.pending_review, data.message, config);
                } else {
                    renderPaybillStep(config);
                    document.getElementById('luxPaybillError').textContent = data.message || 'Could not verify.';
                    document.getElementById('luxPaybillError').hidden = false;
                }
            } catch (e) {
                renderPaybillStep(config);
            }
        });
    }

    function renderWaitingStep(message) {
        const body = modalEl.querySelector('.lux-payment-modal-body');
        body.innerHTML = `
            <div class="lux-payment-spinner"></div>
            <p class="lux-payment-waiting-text">${message}</p>
        `;
    }

    function renderResultStep(success, message, config) {
        const body = modalEl.querySelector('.lux-payment-modal-body');
        body.innerHTML = `
            <div class="lux-payment-result ${success ? 'is-success' : 'is-error'}">
                <i class="fa-solid ${success ? 'fa-circle-check' : 'fa-circle-xmark'}"></i>
            </div>
            <p class="lux-payment-result-text">${message}</p>
            ${!success ? '<button type="button" class="lux-btn lux-payment-retry-btn" style="width:100%;">Try Again</button>' : ''}
        `;

        if (!success) {
            body.querySelector('.lux-payment-retry-btn').addEventListener('click', () => renderPhoneStep(config));
        } else if (typeof config.onSuccess === 'function') {
            config.onSuccess();
        }
    }

    function renderManualVerifyStep(paymentId, config) {
        const body = modalEl.querySelector('.lux-payment-modal-body');
        body.innerHTML = `
            <p class="lux-payment-waiting-text">This is taking longer than expected. If you already paid, paste the M-Pesa confirmation message (or just the code) below.</p>
            <textarea id="luxManualReceiptInput" class="lux-payment-input" rows="3" placeholder="e.g. UIEQ46JHI7 Confirmed. Ksh5.00 sent to..."></textarea>
            <div class="lux-payment-error" id="luxManualError" hidden></div>
            <button type="button" class="lux-btn" id="luxManualVerifyBtn" style="width:100%; margin-top:10px;">Verify Payment</button>
            <button type="button" class="lux-payment-retry-btn" id="luxManualRetryBtn" style="width:100%; margin-top:8px;">Start a New Payment Instead</button>
        `;

        body.querySelector('#luxManualVerifyBtn').addEventListener('click', async () => {
            const input = document.getElementById('luxManualReceiptInput').value.trim();
            const errorEl = document.getElementById('luxManualError');

            if (!input) {
                errorEl.textContent = 'Paste the M-Pesa message or code.';
                errorEl.hidden = false;
                return;
            }

            renderWaitingStep('Verifying...');

            try {
                const res = await fetch(window.LUX_PAYMENT_CONFIG.baseUrl + '/api/payments/submit_receipt.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        csrf_token: window.LUX_PAYMENT_CONFIG.csrfToken,
                        payment_id: paymentId,
                        receipt_input: input,
                    }),
                });
                const data = await res.json();

                if (data.success) {
                    renderResultStep(!data.pending_review, data.message, config);
                } else {
                    renderManualVerifyStep(paymentId, config);
                    document.getElementById('luxManualError').textContent = data.message || 'Could not verify.';
                    document.getElementById('luxManualError').hidden = false;
                }
            } catch (e) {
                renderManualVerifyStep(paymentId, config);
            }
        });

        body.querySelector('#luxManualRetryBtn').addEventListener('click', () => renderPhoneStep(config));
    }

    function showError(message) {
        const errorEl = modalEl.querySelector('#luxPaymentError');
        if (!errorEl) return;
        errorEl.textContent = message;
        errorEl.hidden = false;
    }

    async function initiate(config) {
        const phone = modalEl.querySelector('#luxPaymentPhone').value.trim();
        const amountInput = modalEl.querySelector('#luxPaymentAmount');
        const amount = amountInput ? parseFloat(amountInput.value) : undefined;

        if (!phone) {
            showError('Enter your M-Pesa phone number.');
            return;
        }

        if (amountInput && (!amount || amount <= 0)) {
            showError('Enter a valid amount.');
            return;
        }

        renderWaitingStep('Sending payment request to your phone...');

        try {
            const payload = {
                csrf_token: window.LUX_PAYMENT_CONFIG.csrfToken,
                purpose: config.purpose,
                phone: phone,
            };
            if (config.bookingId) payload.booking_id = config.bookingId;
            if (config.houseId) payload.house_id = config.houseId;
            if (amount) payload.amount = amount;

            const res = await fetch(window.LUX_PAYMENT_CONFIG.baseUrl + '/api/payments/initiate_payment.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });

            const data = await res.json();

            if (!data.success) {
                renderResultStep(false, data.message || 'Could not start payment.', config);
                return;
            }

            if (data.waived) {
                renderResultStep(true, data.message || 'Granted at no charge!', config);
                return;
            }

            renderWaitingStep('Enter your M-Pesa PIN on your phone to complete payment...');
            pollDeadline = Date.now() + POLL_TIMEOUT_MS;
            pollStatus(data.payment_id, config);

        } catch (e) {
            renderResultStep(false, 'Network error. Please check your connection and try again.', config);
        }
    }

    async function pollStatus(paymentId, config) {
        if (Date.now() > pollDeadline) {
            renderManualVerifyStep(paymentId, config);
            return;
        }

        try {
            const res = await fetch(
                window.LUX_PAYMENT_CONFIG.baseUrl + '/api/payments/check_payment_status.php?payment_id=' + paymentId
            );
            const data = await res.json();

            if (data.success && data.status === 'completed') {
                renderResultStep(true, 'Payment successful!', config);
                return;
            }

            if (data.success && (data.status === 'failed' || data.status === 'timeout')) {
                renderResultStep(false, 'Payment was not completed. You can try again.', config);
                return;
            }

            // still pending — keep polling
            pollTimer = setTimeout(() => pollStatus(paymentId, config), POLL_INTERVAL_MS);

        } catch (e) {
            pollTimer = setTimeout(() => pollStatus(paymentId, config), POLL_INTERVAL_MS);
        }
    }

    function open(config) {
        ensureModal();

        // startStep: 'paybill' skips straight to the paste-your-code
        // screen — for "I already paid, just let me report it"
        // entry points (e.g. wallet.php's second button below),
        // where making someone click through the STK phone-entry
        // screen first would make no sense.
        if (config.startStep === 'paybill') {
            renderPaybillStep(config);
        } else {
            renderPhoneStep(config);
        }

        modalEl.classList.add('is-open');
    }

    window.LuxPayment = { open };

})();
