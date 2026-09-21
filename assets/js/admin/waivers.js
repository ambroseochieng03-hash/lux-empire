/**
 * LUX EMPIRE — Admin Waivers Page
 * Grant / revoke / history for single-use vouchers, via LuxAdmin (admin-core.js).
 */
(function () {
    'use strict';

    var baseUrl = (window.LUX_ADMIN || {}).baseUrl || '';

    var form = document.getElementById('luxWaiverForm');
    var targetSelect = document.getElementById('luxWaiverTarget');
    var emailWrap = document.getElementById('luxWaiverEmailWrap');
    var statusFilter = document.getElementById('luxWaiverStatusFilter');
    var grid = document.getElementById('luxWaiverGrid');

    var historyModal = document.getElementById('luxWaiverHistoryModal');
    var historyTitle = document.getElementById('luxWaiverHistoryTitle');
    var historyBody = document.getElementById('luxWaiverHistoryBody');

    var EVENT_LABELS = {
        granted: 'Granted',
        redeemed: 'Used on a booking',
        restored: 'Returned (booking not approved)',
        used: 'Used (booking approved)',
        exhausted: 'Used up (second attempt not approved)',
        expired: 'Expired',
        revoked: 'Revoked'
    };

    function updateEmailField() {
        emailWrap.style.display = targetSelect.value === 'user' ? 'block' : 'none';
    }

    targetSelect.addEventListener('change', updateEmailField);
    updateEmailField();

    if (statusFilter) {
        statusFilter.addEventListener('change', function () {
            var url = new URL(window.location.href);

            if (statusFilter.value) {
                url.searchParams.set('status', statusFilter.value);
            } else {
                url.searchParams.delete('status');
            }

            url.searchParams.delete('page');
            window.location.href = url.toString();
        });
    }

    function grant(body) {
        LuxAdmin.request(baseUrl + '/api/admin/waiver_grant.php', { body: body })
            .then(function (data) {
                LuxAdmin.toast(data.message || 'Voucher granted.', 'success');
                setTimeout(function () { window.location.reload(); }, 800);
            })
            .catch(function (err) {
                LuxAdmin.toast(err.message, 'error');
            });
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        var target = targetSelect.value;
        var body = {
            target: target,
            email: document.getElementById('luxWaiverEmail').value.trim(),
            amount: document.getElementById('luxWaiverAmount').value,
            unit: document.getElementById('luxWaiverUnit').value,
            reason: document.getElementById('luxWaiverReason').value.trim()
        };

        if (target === 'user' && !body.email) {
            LuxAdmin.toast('Enter an email address.', 'error');
            return;
        }

        if (!body.reason) {
            LuxAdmin.toast('A reason is required.', 'error');
            return;
        }

        if (target === 'user') {
            grant(body);
            return;
        }

        var label = target === 'role:tenant' ? 'tenant' : 'landlord';

        LuxAdmin.confirm({
            title: 'Give every active ' + label + ' a voucher?',
            message: 'One separate, single-use voucher is created for each active ' + label + ' who does not already hold a live one. This cannot be undone in one click (each voucher can be revoked individually).',
            onConfirm: function () {
                grant(body);
            }
        });
    });

    function renderHistory(events) {
        if (!events.length) {
            historyBody.innerHTML = '<p class="lux-entity-meta">No history yet.</p>';
            return;
        }

        historyBody.innerHTML = '';

        events.forEach(function (e) {
            var row = document.createElement('div');
            row.className = 'lux-entity-meta';
            row.style.cssText = 'padding:10px 0; border-bottom:1px solid rgba(255,255,255,0.08);';

            var parts = [];
            parts.push('<strong></strong>');
            parts.push(' — ' + e.created_at);

            if (e.actor_name) { parts.push(' · by ' + e.actor_name); }
            if (e.booking_id) { parts.push(' · booking #' + e.booking_id); }

            row.innerHTML = parts.join('');
            row.querySelector('strong').textContent = EVENT_LABELS[e.event] || e.event;

            if (e.note) {
                var note = document.createElement('div');
                note.textContent = e.note;
                note.style.opacity = '0.75';
                row.appendChild(note);
            }

            historyBody.appendChild(row);
        });
    }

    grid.addEventListener('click', function (event) {
        var btn = event.target.closest('[data-action]');

        if (!btn) {
            return;
        }

        var action = btn.getAttribute('data-action');
        var waiverId = btn.getAttribute('data-waiver-id');

        if (action === 'history') {
            historyTitle.textContent = 'Voucher history — ' + btn.getAttribute('data-waiver-name');
            historyBody.innerHTML = '<p class="lux-entity-meta">Loading...</p>';
            historyModal.classList.add('is-open');
            historyModal.setAttribute('aria-hidden', 'false');

            LuxAdmin.request(baseUrl + '/api/admin/waiver_history.php?waiver_id=' + encodeURIComponent(waiverId), { method: 'GET' })
                .then(function (data) { renderHistory(data.events || []); })
                .catch(function (err) {
                    historyBody.innerHTML = '<p class="lux-entity-meta"></p>';
                    historyBody.firstChild.textContent = err.message;
                });

            return;
        }

        if (action === 'revoke') {
            LuxAdmin.confirm({
                title: 'Revoke this voucher?',
                message: 'The person can no longer use it. Explain why.',
                requireReason: true,
                onConfirm: function (reason) {
                    LuxAdmin.request(baseUrl + '/api/admin/waiver_revoke.php', {
                        body: { waiver_id: waiverId, reason: reason }
                    }).then(function () {
                        LuxAdmin.toast('Voucher revoked.', 'success');
                        setTimeout(function () { window.location.reload(); }, 600);
                    }).catch(function (err) {
                        LuxAdmin.toast(err.message, 'error');
                    });
                }
            });
        }
    });

    document.querySelectorAll('[data-history-close]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            historyModal.classList.remove('is-open');
            historyModal.setAttribute('aria-hidden', 'true');
        });
    });

}());