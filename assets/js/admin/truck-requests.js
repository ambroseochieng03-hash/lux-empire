(function () {
    'use strict';

    var baseUrl = (window.LUX_ADMIN || {}).baseUrl || '';
    var grid = document.getElementById('luxTruckGrid');
    var statusFilter = document.getElementById('luxTruckStatusFilter');

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

        var requestId = btn.getAttribute('data-truck-id');
        var card = grid.querySelector('[data-truck-card="' + requestId + '"]');

        LuxAdmin.confirm({
            title: 'Delete this pending request?',
            message: 'This cannot be undone. Please provide a reason.',
            requireReason: true,
            onConfirm: function (reason) {
                LuxAdmin.request(baseUrl + '/api/admin/truck_request_delete.php', { body: { request_id: requestId, reason: reason } })
                    .then(function () {
                        LuxAdmin.toast('Request deleted.', 'success');
                        if (card) { card.remove(); }
                    }).catch(function (err) { LuxAdmin.toast(err.message, 'error'); });
            }
        });
    });

}());
