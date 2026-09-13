/*
=========================================
LUX EMPIRE — OFFLINE DRAFT SYNC
=========================================
Generic replay engine for anything saved via LuxOfflineDB.saveDraft().
Deliberately NOT using the Background Sync API (registration.sync) —
it's Chromium-only and silently no-ops on Firefox/Safari, which would
mean drafts quietly never sync for a chunk of users. Plain 'online'
event + a visible manual button instead, so syncing is something the
person can see happen rather than trust invisibly.
=========================================
*/

(function () {

    if (!window.LuxOfflineDB) {
        return;
    }

    let banner = null;
    let syncing = false;

    function ensureBanner() {

        if (banner) {
            return banner;
        }

        banner = document.createElement('div');
        banner.id = 'luxOfflineBanner';
        banner.style.cssText = `
            position:fixed; bottom:0; left:0; right:0; z-index:9999;
            background:#1a1a1a; color:#D4AF37; padding:14px 20px;
            display:none; align-items:center; justify-content:space-between;
            gap:14px; font-size:0.9rem; border-top:1px solid rgba(212,175,55,0.3);
        `;

        banner.innerHTML = `
            <span id="luxOfflineBannerText"></span>
            <button type="button" id="luxOfflineSyncBtn" style="
                background:rgba(212,175,55,0.15); color:#D4AF37; border:1px solid #D4AF37;
                border-radius:10px; padding:8px 16px; cursor:pointer;
            ">Sync now</button>
        `;

        document.body.appendChild(banner);

        banner.querySelector('#luxOfflineSyncBtn').addEventListener('click', syncDrafts);

        return banner;
    }

    function updateBanner(pendingCount) {

        const el = ensureBanner();
        const text = el.querySelector('#luxOfflineBannerText');

        if (pendingCount > 0) {
            text.textContent = pendingCount === 1
                ? '1 saved item is waiting to sync.'
                : pendingCount + ' saved items are waiting to sync.';
            el.style.display = 'flex';
        } else {
            el.style.display = 'none';
        }
    }

    function refreshBanner() {
        window.LuxOfflineDB.getPendingDrafts().then((drafts) => updateBanner(drafts.length));
    }

    async function syncDrafts() {

        if (syncing || !navigator.onLine) {
            return;
        }

        syncing = true;

        const drafts = await window.LuxOfflineDB.getPendingDrafts();

        for (const draft of drafts) {

            // book_house.php was retired in favor of payment-first booking —
            // any draft still pointing at it predates that change and can
            // never succeed. Discard silently rather than leaving it stuck
            // forever with no way to clear itself.
            if (draft.endpoint.includes('book_house.php')) {
                await window.LuxOfflineDB.deleteDraft(draft.id);
                continue;
            }

            try {

                const body = new URLSearchParams(draft.payload);

                if (draft.csrfToken) {
                    body.append('csrf_token', draft.csrfToken);
                }

                const response = await fetch(draft.endpoint, {
                    method: 'POST',
                    body,
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin'
                });

                const data = await response.json().catch(() => ({ success: false }));

                if (response.ok && data.success !== false) {
                    await window.LuxOfflineDB.deleteDraft(draft.id);
                }
                // On failure, leave it queued — could be a real rejection
                // (e.g. CSRF token expired since it was saved), not just
                // still-offline. Left for the person to see and retry
                // manually rather than silently dropped.

            } catch (error) {
                // Still offline, or a network blip — stop this pass,
                // the 'online' listener or the next manual click will
                // retry the rest.
                break;
            }
        }

        syncing = false;
        refreshBanner();
    }

    window.addEventListener('online', syncDrafts);
    document.addEventListener('DOMContentLoaded', () => {
        refreshBanner();
        if (navigator.onLine) {
            syncDrafts();
        }
    });

    window.LuxOfflineSync = { syncDrafts, refreshBanner };

})();
