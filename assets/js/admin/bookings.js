(function () {
    'use strict';

    var baseUrl = (window.LUX_ADMIN || {}).baseUrl || '';
    var grid = document.getElementById('luxBookingGrid');
    var statusFilter = document.getElementById('luxBookingStatusFilter');

    if (statusFilter) {
        statusFilter.addEventListener('change', function () {
            var value = statusFilter.value;
            grid.querySelectorAll('.lux-entity-card').forEach(function (card) {
                card.style.display = (!value || card.getAttribute('data-status') === value) ? '' : 'none';
            });
        });
    }

    grid.addEventListener('click', function (event) {
        var btn = event.target.closest('[data-action="delete"]');
        if (!btn) { return; }

        var bookingId = btn.getAttribute('data-booking-id');
        var card = grid.querySelector('[data-booking-card="' + bookingId + '"]');

        LuxAdmin.confirm({
            title: 'Delete this booking?',
            message: 'This cannot be undone. Please provide a reason.',
            requireReason: true,
            onConfirm: function (reason) {
                LuxAdmin.request(baseUrl + '/api/admin/booking_delete.php', { body: { booking_id: bookingId, reason: reason } })
                    .then(function () {
                        LuxAdmin.toast('Booking deleted.', 'success');
                        if (card) { card.remove(); }
                    }).catch(function (err) { LuxAdmin.toast(err.message, 'error'); });
            }
        });
    });

}());
