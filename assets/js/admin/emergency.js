(function () {
    'use strict';

    var baseUrl = (window.LUX_ADMIN || {}).baseUrl || '';
    var grid = document.getElementById('luxEmergencyGrid');

    var statusBadgeClassMap = {
        active: 'lux-emergency-status-active',
        responding: 'lux-emergency-status-responding',
        resolved: 'lux-emergency-status-resolved',
        dismissed: 'lux-emergency-status-dismissed'
    };

    grid.addEventListener('click', function (event) {
        var btn = event.target.closest('[data-action]');
        if (!btn) { return; }

        var action = btn.getAttribute('data-action');
        var alertId = btn.getAttribute('data-alert-id');
        var card = grid.querySelector('[data-alert-card="' + alertId + '"]');
        
        if (action === 'resolve-trip-completed' || action === 'resolve-trip-cancelled') {
            var outcome = action === 'resolve-trip-completed' ? 'trip_completed' : 'trip_cancelled';
            var tripId = btn.getAttribute('data-trip-id');

            LuxAdmin.confirm({
                title: outcome === 'trip_completed' ? 'Mark this trip as completed?' : 'Mark this trip as cancelled?',
                message: outcome === 'trip_completed'
                    ? 'Confirm the delivery actually happened. Commission will be deducted from the driver as normal. Explain what you confirmed.'
                    : 'Confirm the move did NOT happen. No commission will be charged. Explain what you confirmed.',
                requireReason: true,
                onConfirm: function (reason) {
                    LuxAdmin.request(baseUrl + '/api/admin/truck_resolve_stuck.php', {
                        body: { request_id: tripId, outcome: outcome, reason: reason }
                    }).then(function () {
                        LuxAdmin.toast('Trip resolved.', 'success');
                        setTimeout(function () { window.location.reload(); }, 700);
                    }).catch(function (err) { LuxAdmin.toast(err.message, 'error'); });
                }
            });
            return;
        }

        if (action === 'force-cancel-trip') {
            var cancelTripId = btn.getAttribute('data-trip-id');

            LuxAdmin.confirm({
                title: 'Cancel this trip right now?',
                message: 'The trip stops immediately, both tenant and driver are notified with your reason. No commission is charged.',
                requireReason: true,
                onConfirm: function (reason) {
                    LuxAdmin.request(baseUrl + '/api/admin/truck_force_cancel.php', {
                        body: { request_id: cancelTripId, reason: reason }
                    }).then(function () {
                        LuxAdmin.toast('Trip cancelled.', 'success');
                        setTimeout(function () { window.location.reload(); }, 700);
                    }).catch(function (err) { LuxAdmin.toast(err.message, 'error'); });
                }
            });
            return;
        }

        if (action === 'delete') {
            LuxAdmin.confirm({
                title: 'Permanently delete this alert?',
                message: 'This cannot be undone. Please provide a reason.',
                requireReason: true,
                onConfirm: function (reason) {
                    LuxAdmin.request(baseUrl + '/api/admin/emergency_delete.php', { body: { id: alertId, reason: reason } })
                        .then(function () {
                            LuxAdmin.toast('Alert deleted.', 'success');
                            if (card) { card.remove(); }
                        }).catch(function (err) { LuxAdmin.toast(err.message, 'error'); });
                }
            });
            return;
        }

        // responding / resolved / dismissed
        LuxAdmin.request(baseUrl + '/api/admin/update_emergency_status.php', { body: { id: alertId, status: action } })
            .then(function (data) {
                LuxAdmin.toast('Status updated to ' + data.status + '.', 'success');
                var badge = card.querySelector('[data-status-badge]');
                if (badge) {
                    badge.textContent = data.status.charAt(0).toUpperCase() + data.status.slice(1);
                    badge.className = 'lux-badge ' + (statusBadgeClassMap[data.status] || '');
                }
            }).catch(function (err) { LuxAdmin.toast(err.message, 'error'); });
    });

}());
