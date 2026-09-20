/**
 * LUX EMPIRE — Admin Bookings Page
 * Status filter + the three booking actions (cancel & refund, archive,
 * delete unpaid), all via LuxAdmin (admin-core.js). Every action asks for a reason.
 */
(function () {
    'use strict';

    var baseUrl = (window.LUX_ADMIN || {}).baseUrl || '';
    var grid = document.getElementById('luxBookingGrid');
    var statusFilter = document.getElementById('luxBookingStatusFilter');

    if (statusFilter) {
        statusFilter.addEventListener('change', function () {
            var url = new URL(window.location.href);

            if (statusFilter.value) {
                url.searchParams.set('status', statusFilter.value);
            } else {
                url.searchParams.delete('status');
            }

            url.searchParams.delete('page'); // a new filter always starts at page 1
            window.location.href = url.toString();
        });
    }

    if (!grid) {
        return;
    }

    var ACTIONS = {
        'cancel-refund': {
            url: '/api/admin/booking_cancel_refund.php',
            title: 'Cancel this booking and refund the tenant?',
            message: 'The booking is cancelled, the property is freed, and the tenant\'s fee is refunded to their M-Pesa. Both people are notified. Explain why.',
            success: 'Booking cancelled — refund queued.',
            reload: true
        },
        'archive': {
            url: '/api/admin/booking_archive.php',
            title: 'Archive this booking?',
            message: 'It disappears from this list only. The booking, its payment and any refund are kept for disputes and accounting. Explain why.',
            success: 'Booking archived.',
            reload: false
        },
        'delete': {
            url: '/api/admin/booking_delete.php',
            title: 'Delete this unpaid booking?',
            message: 'It has no payment attached, so it is removed permanently. Explain why.',
            success: 'Booking deleted.',
            reload: false
        }
    };

    grid.addEventListener('click', function (event) {
        var btn = event.target.closest('[data-action]');

        if (!btn) {
            return;
        }

        var config = ACTIONS[btn.getAttribute('data-action')];
        var bookingId = btn.getAttribute('data-booking-id');

        if (!config || !bookingId) {
            return;
        }

        var card = grid.querySelector('[data-booking-card="' + bookingId + '"]');

        LuxAdmin.confirm({
            title: config.title,
            message: config.message,
            requireReason: true,
            onConfirm: function (reason) {
                LuxAdmin.request(baseUrl + config.url, {
                    body: { booking_id: bookingId, reason: reason }
                }).then(function () {
                    LuxAdmin.toast(config.success, 'success');

                    if (config.reload) {
                        setTimeout(function () { window.location.reload(); }, 700);
                    } else if (card) {
                        card.remove();
                    }
                }).catch(function (err) {
                    LuxAdmin.toast(err.message, 'error');
                });
            }
        });
    });

}());