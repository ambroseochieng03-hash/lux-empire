(function () {

    const cfg = window.LUX_ADMIN;
    if (!cfg) return;

    const scopeSelect = document.getElementById('luxWaiverScope');
    const emailWrap = document.getElementById('luxWaiverEmailWrap');

    scopeSelect.addEventListener('change', () => {
        emailWrap.style.display = scopeSelect.value === 'user' ? 'block' : 'none';
    });

    document.getElementById('luxWaiverForm').addEventListener('submit', async (event) => {
        event.preventDefault();

        const scope = scopeSelect.value;
        const email = document.getElementById('luxWaiverEmail').value.trim();
        const days = document.getElementById('luxWaiverDays').value;
        const reason = document.getElementById('luxWaiverReason').value.trim();

        if (scope === 'user' && !email) {
            alert('Enter an email address.');
            return;
        }

        try {
            const body = new URLSearchParams({
                csrf_token: cfg.csrfToken,
                form_action: 'grant',
                scope: scope,
                email: email,
                days: days,
                reason: reason,
            });

            const res = await fetch(`${cfg.baseUrl}/api/admin/waiver_action.php`, { method: 'POST', body });
            const data = await res.json();

            if (data.success) {
                window.location.reload();
            } else {
                alert(data.message || 'Something went wrong.');
            }
        } catch (e) {
            alert('Network error. Please try again.');
        }
    });

    document.addEventListener('click', async (event) => {

        const btn = event.target.closest('[data-action="revoke"]');
        if (!btn) return;

        if (!confirm('Revoke this waiver now?')) return;

        try {
            const body = new URLSearchParams({
                csrf_token: cfg.csrfToken,
                form_action: 'revoke',
                waiver_id: btn.dataset.waiverId,
            });

            const res = await fetch(`${cfg.baseUrl}/api/admin/waiver_action.php`, { method: 'POST', body });
            const data = await res.json();

            if (data.success) {
                btn.closest('.lux-entity-card').remove();
            } else {
                alert(data.message || 'Something went wrong.');
            }
        } catch (e) {
            alert('Network error. Please try again.');
        }
    });

})();
