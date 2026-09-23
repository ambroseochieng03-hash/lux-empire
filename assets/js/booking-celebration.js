/**
 * LUX EMPIRE — Booking approval celebration
 * Checks once per page load (any tenant dashboard page) for an unseen "your booking
 * was approved" moment. Reaches the tenant whether they were online at the moment
 * of approval or log in much later — the check, not a live push, is what finds it.
 */
(function () {
    'use strict';

    var cfg = window.LUX_CELEBRATION;

    if (!cfg) {
        return;
    }

    function showModal(celebration) {

        var overlay = document.createElement('div');
        overlay.className = 'lux-celebrate-overlay';

        var box = document.createElement('div');
        box.className = 'lux-celebrate-box';
        box.setAttribute('role', 'dialog');
        box.setAttribute('aria-modal', 'true');

        box.innerHTML =
            '<div class="lux-celebrate-icon">🎉</div>' +
            '<h2>You\'re in!</h2>' +
            '<p>Your booking for <strong></strong> has been approved by the landlord. Welcome home.</p>' +
            '<p class="lux-celebrate-sub">Moving in soon? Our Lux Moves team can handle the truck, the boxes, and the heavy lifting — book whenever you\'re ready.</p>' +
            '<div class="lux-celebrate-actions">' +
                '<button type="button" class="lux-celebrate-primary">Request a Lux Move</button>' +
                '<button type="button" class="lux-celebrate-secondary">Maybe later</button>' +
            '</div>';

        box.querySelector('strong').textContent = celebration.house_title;
        overlay.appendChild(box);
        document.body.appendChild(overlay);

        function close() {
            overlay.remove();
        }

        box.querySelector('.lux-celebrate-secondary').addEventListener('click', close);
        overlay.addEventListener('click', function (event) {
            if (event.target === overlay) {
                close();
            }
        });

        box.querySelector('.lux-celebrate-primary').addEventListener('click', function () {
            window.location.href = cfg.baseUrl + '/tenant/request-truck';
        });

        document.addEventListener('keydown', function onKey(event) {
            if (event.key === 'Escape') {
                document.removeEventListener('keydown', onKey);
                close();
            }
        });
    }

    fetch(cfg.baseUrl + '/api/notifications/pending_celebration.php')
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data && data.celebration) {
                showModal(data.celebration);
            }
        })
        .catch(function () {});
}());
