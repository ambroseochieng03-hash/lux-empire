(function () {
    const cfg = window.LUX_TRIP_ACTION_CONFIG;
    if (!cfg) return;

    document.addEventListener('click', async (event) => {
        const btn = event.target.closest('.trip-action-btn');
        if (!btn || btn.disabled) return;

        if (btn.dataset.confirm && !confirm(btn.dataset.confirm)) return;

        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = 'Please wait...';

        try {
            const body = new URLSearchParams({
                trip_id: btn.dataset.tripId,
                status: btn.dataset.status,
                csrf_token: cfg.csrfToken
            });

            const res = await fetch(`${cfg.baseUrl}/api/trucks/update_trip_status.php`, {
                method: 'POST', body, headers: { 'Accept': 'application/json' }
            });

            const data = await res.json();

            if (data.success) {
                window.location.reload();
                return;
            }

            alert(data.message || 'Something went wrong.');
            btn.disabled = false;
            btn.innerHTML = originalText;

        } catch (e) {
            alert('Network error. Please check your connection and try again.');
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    });
})();