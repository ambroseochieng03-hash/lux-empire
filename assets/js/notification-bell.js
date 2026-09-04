(function () {
    const cfg = window.LUX_NOTIF_BELL_CONFIG;
    if (!cfg) return;

    const badge = document.getElementById('luxNotifBellBadge');
    const bellWrap = document.getElementById('luxNotifBell');

    function poll() {
        fetch(`${cfg.baseUrl}/api/notifications/fetch_notifications.php`)
            .then(r => r.json())
            .then(data => {
                const count = data.unread_count || 0;

                if (count > 0) {
                    badge.textContent = count > 99 ? '99+' : count;
                    badge.classList.remove('is-hidden');
                    bellWrap.classList.add('has-unread');
                } else {
                    badge.classList.add('is-hidden');
                    bellWrap.classList.remove('has-unread');
                }
            })
            .catch(() => {});
    }

    poll();
    setInterval(poll, 10000);
})();