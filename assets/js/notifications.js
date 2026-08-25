(function () {
    const cfg = window.LUX_NOTIF_CONFIG;
    const list = document.getElementById('notifList');
    const markAllBtn = document.getElementById('notifMarkAllBtn');

    function timeAgo(dateStr) {
        const diff = (Date.now() - new Date(dateStr.replace(' ', 'T') + 'Z')) / 1000;
        if (diff < 60) return 'just now';
        if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        return Math.floor(diff / 86400) + 'd ago';
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.innerText = str || '';
        return div.innerHTML;
    }

    function render(notifications) {
        list.innerHTML = '';

        if (notifications.length === 0) {
            list.innerHTML = '<div class="notif-empty">No notifications yet.</div>';
            return;
        }

        notifications.forEach(n => {
            const item = document.createElement('div');
            item.className = 'notif-item' + (parseInt(n.is_read, 10) === 0 ? ' unread' : '');
            item.innerHTML = `
                <div class="notif-icon"><i class="fa-solid fa-bell"></i></div>
                <div class="notif-body">
                    <div class="notif-title">${escapeHtml(n.title)}</div>
                    <div class="notif-message">${escapeHtml(n.message)}</div>
                    <div class="notif-time">${timeAgo(n.created_at)}</div>
                </div>
            `;

            item.addEventListener('click', () => {
                const formData = new URLSearchParams();
                formData.append('id', n.id);
                formData.append('csrf_token', cfg.csrfToken);
                fetch(`${cfg.baseUrl}/api/notifications/mark_read.php`, { method: 'POST', body: formData })
                    .then(() => { if (n.link) window.location.href = n.link; else load(); });
            });

            list.appendChild(item);
        });
    }

    function load() {
        fetch(`${cfg.baseUrl}/api/notifications/fetch_notifications.php`)
            .then(r => r.json())
            .then(data => {
                if (data.notifications) render(data.notifications);

                const badge = document.getElementById('sidebarNotifBadge');
                if (badge) {
                    if (data.unread_count > 0) {
                        badge.textContent = data.unread_count;
                        badge.style.display = 'inline-flex';
                    } else {
                        badge.style.display = 'none';
                    }
                }
            });
    }

    if (markAllBtn) {
        markAllBtn.addEventListener('click', () => {
            const formData = new URLSearchParams();
            formData.append('csrf_token', cfg.csrfToken);
            fetch(`${cfg.baseUrl}/api/notifications/mark_read.php`, { method: 'POST', body: formData }).then(load);
        });
    }

    load();
    setInterval(load, 10000);
})();
