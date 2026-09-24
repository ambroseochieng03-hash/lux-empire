/**
 * LUX EMPIRE — Sidebar unread counters
 * Keeps the numbers on the sidebar's Chats and Notifications links up to date on
 * EVERY dashboard page, and makes the whole row glow while anything is unread.
 * (chat.js and notifications.js also write to these badges; the observer below
 * keeps the glow in step with them.)
 */
(function () {
    'use strict';

    var cfg = window.LUX_SIDEBAR_COUNTERS;

    if (!cfg) {
        return;
    }

    var chatBadge = document.getElementById('sidebarChatBadge');
    var notifBadge = document.getElementById('sidebarNotifBadge');

    if (!chatBadge && !notifBadge) {
        return;
    }

    var baseTitle = document.title;

    function syncGlow(badge) {
        if (!badge) {
            return;
        }

        var link = badge.closest('a');

        if (!link) {
            return;
        }

        var visible = badge.style.display !== 'none' && badge.textContent.trim() !== '';
        link.classList.toggle('has-unread', visible);
    }

    function setBadge(badge, count) {
        if (!badge) {
            return;
        }

        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : String(count);
            badge.style.display = 'inline-flex';
        } else {
            badge.textContent = '';
            badge.style.display = 'none';
        }

        syncGlow(badge);
    }

    /*
     * Mirrors a count onto the bottom-nav equivalents of the sidebar
     * badge: a small dot on the primary icon (chat) or the More
     * button + its row inside the sheet (notifications). Same
     * "disappears once read" rule as setBadge() above — driven by
     * the exact same count, just written to more places.
     */
    function mirrorToBottomNav(key, count) {

        const hasUnread = count > 0;
        const text = count > 99 ? '99+' : (count > 0 ? String(count) : '');

        const primaryBadge = document.getElementById('luxBottomPrimaryBadge_' + key);
        if (primaryBadge) {
            primaryBadge.textContent = text;
            primaryBadge.style.display = hasUnread ? 'flex' : 'none';
            const parentItem = primaryBadge.closest('.lux-bottom-nav-item');
            if (parentItem) parentItem.classList.toggle('has-unread', hasUnread);
        }

        const moreRowBadge = document.getElementById('luxBottomMoreBadge_' + key);
        if (moreRowBadge) {
            moreRowBadge.textContent = text;
            moreRowBadge.style.display = hasUnread ? 'flex' : 'none';
            const parentRow = document.getElementById('luxBottomMoreRow_' + key);
            if (parentRow) parentRow.classList.toggle('has-unread', hasUnread);

            /*
             * The "More" button itself only glows when the badge it's
             * summarizing actually LIVES inside More — gated on
             * moreRowBadge existing at all. For a role where
             * notifications sit in the primary row instead (landlord,
             * after this change), luxBottomMoreBadge_notif simply
             * isn't in the DOM, so this block never runs and "More"
             * correctly stays quiet for something that isn't in it.
             */
            if (key === 'notif') {
                const moreBtn = document.getElementById('luxBottomNavMoreBtn');
                const moreIndicator = document.getElementById('luxBottomMoreIndicator');
                if (moreBtn) moreBtn.classList.toggle('has-unread', hasUnread);
                if (moreIndicator) {
                    moreIndicator.textContent = text;
                    moreIndicator.style.display = hasUnread ? 'flex' : 'none';
                }
            }
        }
    }

    [chatBadge, notifBadge].forEach(function (badge) {
        if (!badge) {
            return;
        }

        new MutationObserver(function () {
            syncGlow(badge);
        }).observe(badge, {
            attributes: true,
            attributeFilter: ['style'],
            childList: true,
            characterData: true,
            subtree: true
        });
    });

    function poll() {
        if (document.hidden) {
            return;
        }

        fetch(cfg.baseUrl + '/api/notifications/counts.php')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                // An error or a "too many requests" reply has no counts — keep the last known state.
                if (typeof data.notifications !== 'number' || typeof data.chats !== 'number') {
                    return;
                }

                setBadge(notifBadge, data.notifications);
                setBadge(chatBadge, data.chats);

                mirrorToBottomNav('notif', data.notifications);
                mirrorToBottomNav('chat', data.chats);

                // New chat messages are also notifications, so this is the overall unread count.
                document.title = (data.notifications > 0 ? '(' + data.notifications + ') ' : '') + baseTitle;
            })
            .catch(function () {});
    }

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            poll();
        }
    });

    window.LuxSidebarCounters = { refresh: poll };

    poll();
    setInterval(poll, 10000);
}());
