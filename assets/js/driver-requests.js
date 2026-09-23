/*
=========================================
LUX EMPIRE — DRIVER AVAILABLE REQUESTS
=========================================
Polls api/trucks/fetch_requests.php on an interval and diffs the
result against what's on screen, rather than reloading the page or
rebuilding the whole grid — a driver reading a card shouldn't have
it yanked away every 15 seconds. Accept is AJAX (accept_request.php
now returns JSON), so a successful accept redirects, and a failure
(another driver won the race, or a scheduled trip's window hasn't
opened after all) shows an inline message without losing the rest
of the list.

Card HTML for entries the server hasn't rendered yet (a genuinely
NEW request that appeared after page load) is built here in JS,
mirroring the PHP template closely enough to look identical —
kept deliberately simple/plain rather than pixel-perfect fancy,
since this is a working list, not a marketing page.
=========================================
*/

(function () {

    const cfg = window.LUX_DRIVER_REQUESTS_CONFIG;
    if (!cfg) return;

    const grid = document.getElementById('driverRequestsGrid');
    if (!grid) return;

    const POLL_INTERVAL_MS = 15000;
    let pollTimer = null;

    function formatMoney(amount) {
        return 'KES ' + Number(amount).toLocaleString();
    }

    function formatScheduledAt(isoLike) {
        const d = new Date(isoLike.replace(' ', 'T'));
        if (isNaN(d.getTime())) return isoLike;
        return d.toLocaleString(undefined, {
            month: 'short', day: 'numeric', year: 'numeric',
            hour: 'numeric', minute: '2-digit'
        });
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.innerText = str == null ? '' : String(str);
        return div.innerHTML;
    }

    function buildAcceptActionHtml(request) {

        if (request.is_acceptable_now) {
            return `
                <button type="button" class="lux-btn accept-request-btn"
                        data-request-id="${request.id}"
                        style="width:100%; border:none; padding:16px; border-radius:18px; cursor:pointer; font-size:1rem;">
                    Accept Request
                </button>
            `;
        }

        return `
            <button type="button" class="accept-locked-btn" disabled data-window-opens-at="${request.window_opens_at || ''}">
                <i class="fa-solid fa-lock"></i>
                Opens in <span class="countdown-text">calculating...</span>
            </button>
        `;
    }

    function buildCard(request) {

        const card = document.createElement('div');
        card.className = 'lux-card';
        card.dataset.requestId = request.id;
        card.style.cssText = 'padding:30px; border-radius:28px; position:relative; overflow:hidden;';

        const isScheduled = request.trip_type === 'scheduled' && request.scheduled_at;

        const tripTypeHtml = isScheduled
            ? `<span class="trip-type-tag scheduled"><i class="fa-solid fa-calendar-days"></i> Scheduled</span>
               <div style="color:var(--gray); font-size:0.85rem; margin-top:8px;">Move time: ${escapeHtml(formatScheduledAt(request.scheduled_at))}</div>`
            : `<span class="trip-type-tag instant"><i class="fa-solid fa-bolt"></i> Move Now</span>`;

        const distanceHtml = request.distance_km
            ? `<div style="color:var(--gray); font-size:0.85rem; margin-top:6px;">Approx. ${escapeHtml(request.distance_km)} km</div>`
            : '';

        const itemsHtml = request.items_description
            ? `<div style="margin-bottom:25px;">
                   <div style="color:var(--gray); margin-bottom:6px;">Items</div>
                   <div style="color:white; white-space:pre-line; font-size:0.9rem;">${escapeHtml(request.items_description)}</div>
               </div>`
            : '';

        // No tenant name/phone and no Message button here — pending requests
        // show a driver only pickup, destination and price, same as the
        // server-rendered page. Chat and contact only appear once accepted.
        card.innerHTML = `
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:10px;">
                <div>
                    <h2 style="color:white; margin-bottom:8px;">Transport Request</h2>
                    <div style="color:var(--gray); font-size:0.9rem;">Request #${request.id}</div>
                </div>
                <div style="background:rgba(255,215,0,0.15); color:var(--gold); padding:10px 16px; border-radius:14px; font-weight:bold;">
                    ${formatMoney(request.price)}
                </div>
            </div>

            <div style="margin-bottom:20px;">
                ${tripTypeHtml}
                ${distanceHtml}
            </div>

            <div style="margin-bottom:20px;">
                <div style="color:var(--gray); margin-bottom:6px;">Pickup Location</div>
                <div style="color:white;">${escapeHtml(request.pickup_location)}</div>
            </div>

            <div style="margin-bottom:${request.items_description ? '20' : '25'}px;">
                <div style="color:var(--gray); margin-bottom:6px;">Destination</div>
                <div style="color:white;">${escapeHtml(request.destination)}</div>
            </div>

            ${itemsHtml}

            <div style="margin-bottom:25px;">
                <span style="background:rgba(255,165,0,0.15); color:orange; padding:10px 15px; border-radius:12px; font-weight:bold; font-size:0.9rem;">
                    PENDING REQUEST
                </span>
            </div>

            <div class="accept-action-wrap">
                ${buildAcceptActionHtml(request)}
            </div>
        `;

        return card;
    }

    function removeEmptyState() {
        const empty = document.getElementById('driverRequestsEmptyState');
        if (empty) empty.remove();
    }

    function showEmptyStateIfNeeded() {
        if (grid.querySelectorAll('.lux-card[data-request-id]').length > 0) return;
        if (document.getElementById('driverRequestsEmptyState')) return;

        const empty = document.createElement('div');
        empty.className = 'lux-card';
        empty.id = 'driverRequestsEmptyState';
        empty.style.cssText = 'padding:50px; border-radius:28px; text-align:center; grid-column:1/-1;';
        empty.innerHTML = `
            <h2 style="color:white; margin-bottom:15px;">No Requests Available</h2>
            <p style="color:var(--gray); max-width:500px; margin:auto; line-height:1.8;">
                There are currently no pending logistics requests.
            </p>
        `;
        grid.appendChild(empty);
    }

    async function pollAndDiff() {

        let data;

        try {
            const response = await fetch(`${cfg.baseUrl}/api/trucks/fetch_requests.php`);
            data = await response.json();
        } catch (e) {
            return; // network blip — just wait for the next poll, don't disrupt the view
        }

        if (!data || data.success !== true || !Array.isArray(data.requests)) {
            return;
        }

        const incoming = new Map(data.requests.map((r) => [String(r.id), r]));
        const existingCards = grid.querySelectorAll('.lux-card[data-request-id]');

        // Remove cards for requests no longer pending (accepted by
        // someone else, or otherwise no longer available).
        existingCards.forEach((card) => {
            const id = card.dataset.requestId;
            if (!incoming.has(id)) {
                card.remove();
            }
        });

        // Add or update.
        incoming.forEach((request, id) => {

            let card = grid.querySelector(`.lux-card[data-request-id="${id}"]`);

            if (!card) {
                removeEmptyState();
                card = buildCard(request);
                // New instant requests lead; scheduled/older ones trail —
                // matches the server's own ORDER BY.
                if (request.trip_type === 'instant') {
                    grid.prepend(card);
                } else {
                    grid.appendChild(card);
                }
                return;
            }

            // Existing card: only touch the accept action if its
            // acceptability actually changed (a scheduled trip's
            // window just opened) — leave everything else alone so
            // nothing visually jumps for no reason.
            const wrap = card.querySelector('.accept-action-wrap');
            const currentlyLocked = !!card.querySelector('.accept-locked-btn');

            if (wrap && currentlyLocked === request.is_acceptable_now) {
                wrap.innerHTML = buildAcceptActionHtml(request);
            }
        });

        showEmptyStateIfNeeded();
    }

    async function handleAcceptClick(button) {

        const requestId = button.dataset.requestId;
        if (!requestId || button.disabled) return;

        button.disabled = true;
        const originalText = button.innerHTML;
        button.innerHTML = 'Accepting...';

        try {

            const body = new URLSearchParams({
                request_id: requestId,
                csrf_token: cfg.csrfToken
            });

            const response = await fetch(`${cfg.baseUrl}/api/trucks/accept_request.php`, {
                method: 'POST',
                body,
                headers: { 'Accept': 'application/json' }
            });

            const data = await response.json();

            if (data.success) {
                window.location.href = data.redirect || `${cfg.baseUrl}/driver/active-trip`;
                return;
            }

            alert(data.message || 'Unable to accept this request.');

            // Refresh immediately rather than waiting for the next
            // scheduled poll — a failure here usually means the list
            // is already stale (someone else took it).
            pollAndDiff();

        } catch (error) {
            alert('Network error. Please try again.');
            button.disabled = false;
            button.innerHTML = originalText;
        }
    }

    grid.addEventListener('click', (event) => {
        const btn = event.target.closest('.accept-request-btn');
        if (btn) {
            handleAcceptClick(btn);
        }
    });

    function startPolling() {
        if (pollTimer) return;
        pollTimer = setInterval(pollAndDiff, POLL_INTERVAL_MS);
    }

    function stopPolling() {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    // Pause polling when the tab isn't visible — no point hammering
    // the server (or the driver's battery/data) for a screen no one
    // is looking at.
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            stopPolling();
        } else {
            pollAndDiff(); // catch up immediately on return
            startPolling();
        }
    });

    startPolling();

})();
