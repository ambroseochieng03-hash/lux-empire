/*
=========================================
LUX EMPIRE — BOOKING ENGINE
=========================================
Handles:
  - Tenant "Book Now" (dashboard/tenant/search_houses.php)
  - Landlord Approve/Reject (dashboard/landlord/booking_requests.php)

All requests are AJAX (no page reload, no scroll jump), CSRF-protected,
and use event delegation so dynamically-updated cards keep working
without re-binding handlers.
=========================================
*/

(function () {

    const cfg = window.LUX_BOOKING_CONFIG;

    if (!cfg) {
        return;
    }

    document.addEventListener('DOMContentLoaded', () => {
        ensureModal();
        bindTenantBookButtons();
        bindLandlordActionButtons();
        bindTenantBookingAjaxForms();
        bindTenantTruckAjaxForms();
    });

    /*
    =========================================
    SHARED MODAL (§18)
    =========================================
    */

    function ensureModal() {

        if (document.getElementById('luxBookingModal')) {
            return;
        }

        const modal = document.createElement('div');
        modal.id = 'luxBookingModal';
        modal.className = 'lux-booking-modal';
        modal.setAttribute('aria-hidden', 'true');

        modal.innerHTML = `
            <div class="lux-booking-modal-overlay" data-modal-close></div>
            <div class="lux-booking-modal-box" role="alertdialog" aria-live="assertive">
                <div class="lux-booking-modal-icon"><i class="fa-solid fa-crown"></i></div>
                <div class="lux-booking-modal-message"></div>
                <button type="button" class="lux-booking-modal-ok" data-modal-close>OK</button>
            </div>
        `;

        document.body.appendChild(modal);

        modal.addEventListener('click', (event) => {
            if (event.target.hasAttribute('data-modal-close')) {
                closeModal();
            }
        });
    }

    let modalTimer = null;

    function showBookingModal(message, type = 'success') {

        ensureModal();

        const modal = document.getElementById('luxBookingModal');
        const messageEl = modal.querySelector('.lux-booking-modal-message');
        const icon = modal.querySelector('.lux-booking-modal-icon i');

        messageEl.textContent = message;

        modal.classList.remove('is-success', 'is-error');
        modal.classList.add(type === 'error' ? 'is-error' : 'is-success');

        icon.className = type === 'error'
            ? 'fa-solid fa-circle-exclamation'
            : 'fa-solid fa-circle-check';

        modal.classList.add('is-visible');
        modal.setAttribute('aria-hidden', 'false');

        if (modalTimer) {
            clearTimeout(modalTimer);
        }

        modalTimer = setTimeout(closeModal, 4000);
    }

    function closeModal() {

        const modal = document.getElementById('luxBookingModal');

        if (!modal) {
            return;
        }

        modal.classList.remove('is-visible');
        modal.setAttribute('aria-hidden', 'true');

        if (modalTimer) {
            clearTimeout(modalTimer);
            modalTimer = null;
        }
    }

    /*
    =========================================
    HELPERS
    =========================================
    */

    function setLoading(button, loading, loadingText) {

        if (!button) {
            return;
        }

        if (loading) {
            button.dataset.originalHtml = button.innerHTML;
            button.disabled = true;
            button.innerHTML = loadingText || 'Please wait...';
        } else {
            button.disabled = false;

            if (button.dataset.originalHtml) {
                button.innerHTML = button.dataset.originalHtml;
            }
        }
    }

    async function postForm(url, fields) {

        const formData = new URLSearchParams();

        Object.keys(fields).forEach((key) => {
            formData.append(key, fields[key]);
        });

        const response = await fetch(url, {
            method: 'POST',
            body: formData,
            headers: {
                'Accept': 'application/json'
            }
        });

        let data;

        try {
            data = await response.json();
        } catch (e) {
            data = { success: false, message: 'Unexpected server response.' };
        }

        return data;
    }

    /*
    =========================================
    TENANT — BOOK NOW
    =========================================
    */

    function bindTenantBookButtons() {

        document.addEventListener('click', async (event) => {

            const button = event.target.closest('.book-now-btn');

            if (!button || button.disabled) {
                return;
            }

            const houseId = button.dataset.houseId;

            if (!houseId) {
                return;
            }

            setLoading(button, true);

            try {

                const data = await postForm(
                    `${cfg.baseUrl}/api/houses/book_house.php`,
                    {
                        house_id: houseId,
                        csrf_token: cfg.csrfToken
                    }
                );

                if (data.success) {

                    button.textContent = 'Request Pending';
                    button.classList.remove('book-now-btn');
                    button.classList.add('lux-explore-btn-pending');
                    button.disabled = true;

                    showBookingModal(data.message || 'Your booking request has been submitted.', 'success');

                } else {

                    setLoading(button, false);
                    showBookingModal(data.message || 'Unable to submit booking request.', 'error');
                }

            } catch (error) {

                setLoading(button, false);
                showBookingModal('Network error. Please try again.', 'error');
            }
        });
    }

    /*
    =========================================
    LANDLORD — APPROVE / REJECT
    =========================================
    */

    function bindLandlordActionButtons() {

        document.addEventListener('click', async (event) => {

            const button = event.target.closest('.booking-action-btn');

            if (!button || button.disabled) {
                return;
            }

            const bookingId = button.dataset.bookingId;
            const action = button.dataset.action;

            if (!bookingId || !action) {
                return;
            }

            const card = button.closest('.booking-card');

            const cardButtons = card
                ? card.querySelectorAll('.booking-action-btn')
                : [button];

            cardButtons.forEach((btn) => setLoading(btn, true, btn === button ? 'Please wait...' : btn.innerHTML));

            try {

                const data = await postForm(
                    `${cfg.baseUrl}/api/houses/update_booking_status.php`,
                    {
                        booking_id: bookingId,
                        action: action,
                        csrf_token: cfg.csrfToken
                    }
                );

                if (data.success) {

                    showBookingModal(data.message || 'Booking updated.', 'success');

                    if (card) {
                        card.remove();
                    }

                    if (Array.isArray(data.rejected_ids)) {
                        data.rejected_ids.forEach((rejectedId) => {

                            const rejectedCard = document.querySelector(
                                `.booking-card[data-booking-id="${rejectedId}"]`
                            );

                            if (rejectedCard) {
                                rejectedCard.remove();
                            }
                        });
                    }

                    maybeShowEmptyState();

                } else {

                    cardButtons.forEach((btn) => setLoading(btn, false));
                    showBookingModal(data.message || 'Unable to process this request.', 'error');
                }

            } catch (error) {

                cardButtons.forEach((btn) => setLoading(btn, false));
                showBookingModal('Network error. Please try again.', 'error');
            }
        });
    }

    function maybeShowEmptyState() {

        const grid = document.getElementById('landlordBookingsGrid');

        if (!grid) {
            return;
        }

        const remainingCards = grid.querySelectorAll('.booking-card');

        if (remainingCards.length > 0) {
            return;
        }

        if (document.getElementById('landlordBookingsEmpty')) {
            return;
        }

        const empty = document.createElement('div');
        empty.className = 'lux-card empty-card';
        empty.id = 'landlordBookingsEmpty';
        empty.innerHTML = `
            <h2 class="empty-title">No Booking Requests</h2>
            <p class="empty-text">Tenant requests will appear here.</p>
        `;

        grid.appendChild(empty);
    }

    /*
    =========================================
    TENANT — CANCEL / DELETE (my_bookings.php)
    =========================================

    Scoped deliberately to `.booking-ajax-form` only (NOT the older
    `.booking-action-form` class still used by the truck-request
    forms on the same page) — those go to different endpoints
    (api/trucks/*) that don't return JSON yet, so they're
    intentionally left as normal form submissions until that's
    handled as its own task.

    Delete uses a `data-confirm="..."` attribute (checked here,
    since the old inline `onsubmit="return confirm(...)"` would not
    reliably stop this delegated submit handler — see house-booking
    forms in my_bookings.php).
    */

    function bindTenantBookingAjaxForms() {

        document.addEventListener('submit', async (event) => {

            const form = event.target.closest('.booking-ajax-form');

            if (!form) {
                return;
            }

            event.preventDefault();

            if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
                return;
            }

            const submitButton = form.querySelector('button[type="submit"]');
            const card = form.closest('.booking-card');

            setLoading(submitButton, true);

            try {

                const data = await postForm(form.action, {
                    booking_id: form.querySelector('input[name="booking_id"]').value,
                    csrf_token: cfg.csrfToken
                });

                if (data.success) {

                    showBookingModal(data.message || 'Done.', 'success');

                    if (card) {
                        card.remove();
                    }

                } else {

                    setLoading(submitButton, false);
                    showBookingModal(data.message || 'Unable to process this request.', 'error');
                }

            } catch (error) {

                setLoading(submitButton, false);
                showBookingModal('Network error. Please try again.', 'error');
            }
        });
    }

    /*
    =========================================
    TENANT — TRUCK REQUEST CANCEL / DELETE (my_bookings.php)
    =========================================

    Mirrors bindTenantBookingAjaxForms() above but for the truck-request
    forms, which post `trip_id` (not `booking_id`) to api/trucks/*.php.
    Kept as a separate function rather than a generalized one so each
    stays easy to read and trace independently.
    */

    function bindTenantTruckAjaxForms() {

        document.addEventListener('submit', async (event) => {

            const form = event.target.closest('.truck-ajax-form');

            if (!form) {
                return;
            }

            event.preventDefault();

            if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
                return;
            }

            const submitButton = form.querySelector('button[type="submit"]');
            const card = form.closest('.booking-card');

            setLoading(submitButton, true);

            try {

                const data = await postForm(form.action, {
                    trip_id: form.querySelector('input[name="trip_id"]').value,
                    csrf_token: cfg.csrfToken
                });

                if (data.success) {

                    showBookingModal(data.message || 'Done.', 'success');

                    if (card) {
                        card.remove();
                    }

                } else {

                    setLoading(submitButton, false);
                    showBookingModal(data.message || 'Unable to process this request.', 'error');
                }

            } catch (error) {

                setLoading(submitButton, false);
                showBookingModal('Network error. Please try again.', 'error');
            }
        });
    }

})();