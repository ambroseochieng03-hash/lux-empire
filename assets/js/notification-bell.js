(function () {
    const cfg = window.LUX_NOTIF_BELL_CONFIG;
    if (!cfg) return;

    const badge = document.getElementById('luxNotifBellBadge');
    const bellWrap = document.getElementById('luxNotifBell');
    if (!badge || !bellWrap) return;

    function showCount(count) {
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : String(count);
            badge.style.display = '';
            badge.classList.remove('is-hidden');
            bellWrap.classList.add('has-unread');
        } else {
            // Nothing new: show NOTHING at all (not even a 0).
            badge.textContent = '';
            badge.style.display = 'none';
            badge.classList.add('is-hidden');
            bellWrap.classList.remove('has-unread');
        }
    }

    function poll() {
        if (document.hidden) return;

        fetch(`${cfg.baseUrl}/api/notifications/counts.php`)
            .then(r => r.json())
            .then(data => {
                // An error or a "too many requests" reply has no count — keep
                // showing the last known state instead of resetting to 0.
                if (typeof data.notifications !== 'number') return;
                showCount(data.notifications);
            })
            .catch(() => {});
    }

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) poll();
    });

    showCount(0);
    poll();
    setInterval(poll, 10000);
})();