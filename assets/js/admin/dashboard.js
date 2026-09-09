(function () {
    'use strict';

    var baseUrl = (window.LUX_ADMIN || {}).baseUrl || '';

    document.addEventListener('click', function (event) {
        var btn = event.target.closest('[data-dashboard-action]');
        if (!btn) { return; }

        var action = btn.getAttribute('data-dashboard-action');

        if (action === 'delete-user') {
            var userId = btn.getAttribute('data-user-id');

            LuxAdmin.confirm({
                title: 'Permanently delete this account?',
                message: 'This cannot be undone. The user and all associated data will be permanently removed.',
                requireReason: true,
                onConfirm: function (reason) {
                    LuxAdmin.request(baseUrl + '/api/admin/user_delete.php', { body: { user_id: userId, reason: reason } })
                        .then(function () {
                            LuxAdmin.toast('User deleted.', 'success');
                            btn.closest('div[style]').remove();
                        }).catch(function (err) { LuxAdmin.toast(err.message, 'error'); });
                }
            });
        }

        if (action === 'delete-trip') {
            var tripId = btn.getAttribute('data-trip-id');

            LuxAdmin.confirm({
                title: 'Delete this pending request?',
                message: 'This cannot be undone. Please provide a reason.',
                requireReason: true,
                onConfirm: function (reason) {
                    LuxAdmin.request(baseUrl + '/api/admin/truck_request_delete.php', { body: { request_id: tripId, reason: reason } })
                        .then(function () {
                            LuxAdmin.toast('Request deleted.', 'success');
                            btn.closest('div[style]').remove();
                        }).catch(function (err) { LuxAdmin.toast(err.message, 'error'); });
                }
            });
        }
    });

}());
