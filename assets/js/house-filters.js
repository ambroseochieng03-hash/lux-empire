/*
=========================================
LUX EMPIRE — HOUSE FILTERS + AJAX RESULTS
=========================================
Requires window.LUX_BOOKING_CONFIG (baseUrl/csrfToken).
Optional page globals it will use if present:
  - window.LUX_TENANT_BOOKING_STATUS  ({ [houseId]: status })
  - window.LUX_CURRENT_TENANT_ID      (int, to detect "own listing")
  - window.LUX_IS_GUEST               (bool)
Works on any page that includes:
  - includes/house_filter_modal.php
  - a results grid with id="housesResultsGrid"
  - a search input with id="houseKeywordInput" (optional)
=========================================
*/

(function () {

    const cfg = window.LUX_BOOKING_CONFIG;
    if (!cfg) return;

    const grid = document.getElementById('housesResultsGrid');
    if (!grid) return;

    const bookingStatus = window.LUX_TENANT_BOOKING_STATUS || {};
    const currentTenantId = window.LUX_CURRENT_TENANT_ID || null;
    const isGuest = !!window.LUX_IS_GUEST;

    // Keep our local booking-status cache in sync with bookings.js,
    // so a filter/search re-render right after a successful "Book
    // Now" doesn't forget about it and show a clickable button again.
    document.addEventListener('click', (event) => {
        const btn = event.target.closest('.book-now-btn');
        if (!btn) return;

        const houseId = parseInt(btn.dataset.houseId, 10);
        if (!houseId) return;

        // bookings.js's own handler runs async; we just need to know
        // a request went out for this house so a re-render treats it
        // as pending rather than bookable again. If bookings.js's
        // call fails, the button reverts and this optimistic entry
        // becomes stale until the next real page load — acceptable,
        // since a failed booking is a rare path and the worst case
        // is just a "Request Pending" card the user can refresh.
        bookingStatus[houseId] = 'pending';
    });

    let state = {
        offset: 0,
        limit: 12,
        mode: 'exact',
        filters: {}
    };

    document.addEventListener('DOMContentLoaded', () => {
        initModalOpenClose();
        loadMeta();
        wireApplyReset();
        wireKeywordSearch();
    });

    /* ===================== MODAL OPEN/CLOSE ===================== */

    function initModalOpenClose() {
        const modal = document.getElementById('houseFilterModal');
        if (!modal) return;

        document.querySelectorAll('[data-open-house-filters]').forEach((btn) => {
            btn.addEventListener('click', () => {
                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
            });
        });

        modal.querySelectorAll('[data-hf-close]').forEach((el) => {
            el.addEventListener('click', () => {
                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden', 'true');
            });
        });
    }

    /* ===================== META (DB-DRIVEN OPTIONS) ===================== */

    async function loadMeta() {
        try {
            const res = await fetch(`${cfg.baseUrl}/api/houses/filter_meta.php`);
            const data = await res.json();
            if (!data.success) return;

            populateHouseTypes(data.house_types || []);
            populateLocations(data.locations || []);
            populateInstitutions(data.institutions || []);
            initPriceSlider(data.price || { min: 0, max: 0 });

        } catch (e) {
            console.error('LUX EMPIRE: failed to load filter meta', e);
        }
    }

    function populateHouseTypes(types) {
        const select = document.getElementById('hfHouseType');
        if (!select) return;
        types.forEach((type) => {
            const opt = document.createElement('option');
            opt.value = type;
            opt.textContent = type;
            select.appendChild(opt);
        });
    }

    function populateLocations(locations) {
        const list = document.getElementById('hfLocationList');
        if (!list) return;
        locations.forEach((loc) => {
            const opt = document.createElement('option');
            opt.value = loc;
            list.appendChild(opt);
        });
    }

    function populateInstitutions(institutions) {
        const select = document.getElementById('hfInstitution');
        const distanceField = document.getElementById('hfDistanceField');
        if (!select) return;

        institutions.forEach((inst) => {
            const opt = document.createElement('option');
            opt.value = inst.id;
            opt.textContent = inst.name;
            select.appendChild(opt);
        });

        select.addEventListener('change', () => {
            if (distanceField) {
                distanceField.style.display = select.value ? 'block' : 'none';
            }
        });
    }

    /* ===================== PRICE RANGE SLIDER ===================== */

    function initPriceSlider(bounds) {
        const min = Math.floor(bounds.min || 0);
        const max = Math.ceil(bounds.max || 0) || min + 1;

        const minInput = document.getElementById('hfPriceMin');
        const maxInput = document.getElementById('hfPriceMax');
        const minLabel = document.getElementById('hfPriceMinLabel');
        const maxLabel = document.getElementById('hfPriceMaxLabel');
        const fill = document.getElementById('hfPriceTrackFill');

        [minInput, maxInput].forEach((input) => {
            input.min = min;
            input.max = max;
        });

        minInput.value = min;
        maxInput.value = max;

        function render() {
            let lo = parseInt(minInput.value, 10);
            let hi = parseInt(maxInput.value, 10);

            if (lo > hi) {
                [lo, hi] = [hi, lo];
            }

            minLabel.textContent = 'KES ' + lo.toLocaleString();
            maxLabel.textContent = 'KES ' + hi.toLocaleString();

            const pctLo = ((lo - min) / (max - min)) * 100;
            const pctHi = ((hi - min) / (max - min)) * 100;

            fill.style.left = pctLo + '%';
            fill.style.width = (pctHi - pctLo) + '%';
        }

        minInput.addEventListener('input', () => {
            if (parseInt(minInput.value, 10) > parseInt(maxInput.value, 10)) {
                minInput.value = maxInput.value;
            }
            render();
        });

        maxInput.addEventListener('input', () => {
            if (parseInt(maxInput.value, 10) < parseInt(minInput.value, 10)) {
                maxInput.value = minInput.value;
            }
            render();
        });

        render();
    }

    /* ===================== APPLY / RESET ===================== */

    function wireApplyReset() {
        const applyBtn = document.getElementById('hfApplyBtn');
        const resetBtn = document.getElementById('hfResetBtn');
        const modal = document.getElementById('houseFilterModal');

        if (applyBtn) {
            applyBtn.addEventListener('click', () => {
                state.filters = collectFilters();
                state.offset = 0;
                loadResults(true);
                if (modal) {
                    modal.classList.remove('is-open');
                    modal.setAttribute('aria-hidden', 'true');
                }
            });
        }

        if (resetBtn) {
            resetBtn.addEventListener('click', () => {
                document.getElementById('hfHouseType').value = '';
                document.getElementById('hfLocation').value = '';
                document.getElementById('hfBedrooms').value = '';
                document.getElementById('hfBathrooms').value = '';
                document.getElementById('hfSort').value = 'newest';

                const minInput = document.getElementById('hfPriceMin');
                const maxInput = document.getElementById('hfPriceMax');
                minInput.value = minInput.min;
                maxInput.value = maxInput.max;
                minInput.dispatchEvent(new Event('input'));

                state.filters = collectFilters();
                state.offset = 0;
                loadResults(true);
            });
        }
    }

    function wireKeywordSearch() {
        const form = document.querySelector('.tenant-search-form, .guest-browse-search-form');
        const input = document.getElementById('houseKeywordInput');
        if (!form || !input) return;

        form.addEventListener('submit', (e) => {
            e.preventDefault();
            state.filters = collectFilters();
            state.offset = 0;
            loadResults(true);
        });
    }

    function collectFilters() {
        const get = (id) => {
            const el = document.getElementById(id);
            return el ? el.value : '';
        };

        return {
            keyword: get('houseKeywordInput'),
            min_price: get('hfPriceMin'),
            max_price: get('hfPriceMax'),
            house_type: get('hfHouseType'),
            location: get('hfLocation'),
            bedrooms: get('hfBedrooms'),
            bathrooms: get('hfBathrooms'),
            institution_id: get('hfInstitution'),
            max_distance_km: get('hfInstitution') ? get('hfMaxDistance') : '',
            sort: get('hfSort') || 'newest'
        };
    }

    /* ===================== FETCH + RENDER RESULTS ===================== */

    async function loadResults(reset) {
        if (reset) {
            grid.innerHTML = '<div class="hf-results-loading">Loading properties...</div>';
        }

        const params = new URLSearchParams({
            ...state.filters,
            limit: state.limit,
            offset: state.offset,
            mode: state.mode
        });

        try {
            const res = await fetch(`${cfg.baseUrl}/api/houses/filter_houses.php?${params.toString()}`);
            const data = await res.json();

            if (!data.success) {
                grid.innerHTML = '<div class="hf-results-empty">Something went wrong loading properties.</div>';
                return;
            }

            if (reset) {
                grid.innerHTML = '';
                state.mode = data.exact_match ? 'exact' : 'relaxed';

                if (!data.exact_match && data.houses.length > 0) {
                    grid.appendChild(buildBroadBanner(data.relaxed));
                }
            }

            removeLoadMoreButton();

            if (data.houses.length === 0 && reset) {
                grid.innerHTML = '<div class="hf-results-empty">' +
                    'We searched according to your requirements but could not find a match.' +
                    '</div>';
                return;
            }

            data.houses.forEach((house) => grid.appendChild(buildCard(house)));

            state.offset += data.houses.length;

            if (data.has_more) {
                grid.appendChild(buildLoadMoreButton());
            }

        } catch (e) {
            grid.innerHTML = '<div class="hf-results-empty">Network error. Please try again.</div>';
        }
    }

    function buildBroadBanner(relaxedFields) {
        const wrap = document.createElement('div');
        wrap.className = 'hf-broad-banner';

        const parts = [];
        if (relaxedFields.includes('max_price')) parts.push('a higher price than requested');
        if (relaxedFields.includes('max_distance_km')) parts.push('a bit farther from your chosen institution');
        if (relaxedFields.includes('house_type')) parts.push('a different house type');

        wrap.textContent = 'We searched according to your requirements but couldn\'t find an exact match. ' +
            'These properties are close, with ' + (parts.join(' or ') || 'slightly different criteria') + '.';

        return wrap;
    }

    function removeLoadMoreButton() {
        const existing = grid.querySelector('.hf-load-more-wrap');
        if (existing) existing.remove();
    }

    function buildLoadMoreButton() {
        const wrap = document.createElement('div');
        wrap.className = 'hf-load-more-wrap';

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'lux-btn';
        btn.textContent = 'Load More';
        btn.addEventListener('click', () => {
            btn.disabled = true;
            btn.textContent = 'Loading...';
            loadResults(false);
        });

        wrap.appendChild(btn);
        return wrap;
    }

    function buildCard(house) {
        const houseId = parseInt(house.id, 10);
        const isOwnHouse = currentTenantId && parseInt(house.landlord_id, 10) === currentTenantId;
        const isBooked = house.status === 'booked';
        const status = bookingStatus[houseId];
        const variant = window.LUX_CARD_VARIANT || 'guest';

        const card = document.createElement('div');
        card.className = 'lux-card tenant-card lux-explore-card' + (isBooked ? ' lux-explore-card-unavailable' : '');
        card.dataset.houseId = houseId;
        if (variant === 'tenant') {
            card.dataset.houseStatus = house.status;
        }

        const unavailableBadge = isBooked
            ? '<div class="lux-explore-unavailable-badge">No Longer Available</div>'
            : '';

        const mediaHtml = buildMediaHtml(house);

        let actionHtml;

        if (isOwnHouse) {
            actionHtml = '';
        } else if (isBooked) {
            actionHtml = '<button type="button" class="lux-explore-btn-book lux-explore-btn-unavailable" disabled>Unavailable</button>';
        } else if (status === 'pending') {
            actionHtml = '<button type="button" class="lux-explore-btn-book lux-explore-btn-pending" disabled>Request Pending</button>';
        } else if (status === 'approved') {
            actionHtml = '<button type="button" class="lux-explore-btn-book lux-explore-btn-pending" disabled>Booked by You</button>';
        } else if (variant === 'guest') {
            actionHtml = `<button type="button" class="lux-explore-btn-book guest-book-btn" data-house-id="${houseId}" data-house-title="${escapeAttr(house.title)}">Book Now</button>`;
        } else {
            actionHtml = `<button type="button" class="lux-explore-btn-book book-now-btn" data-house-id="${houseId}">Book Now</button>`;
        }

        let viewDetailsHtml;
        let ratingHtml = '';
        let chatHtml = '';

        if (variant === 'tenant') {

            viewDetailsHtml = `<a href="${cfg.baseUrl}/dashboard/tenant/view_house.php?id=${houseId}" class="lux-btn lux-explore-btn-view">View Details</a>`;

            const rating = parseInt(house.rating, 10) || 0;
            if (rating > 0) {
                ratingHtml = `<div class="lux-explore-rating">${'★ '.repeat(rating)}${'☆ '.repeat(5 - rating)}</div>`;
            }

            if (!isOwnHouse) {
                chatHtml = `
                    <button type="button"
                            class="lux-btn chat-starter-btn lux-explore-btn-chat"
                            data-other-user-id="${parseInt(house.landlord_id, 10)}"
                            data-other-role="landlord"
                            data-house-id="${houseId}"
                            data-other-name="${escapeAttr(house.landlord_name || '')}">
                        <i class="fa-solid fa-comment-dots"></i> Message Landlord
                    </button>
                `;
            }

        } else {
            viewDetailsHtml = `<button type="button" class="lux-btn lux-explore-btn-view guest-view-details-btn" data-house-id="${houseId}">View Details</button>`;
        }

        card.innerHTML = `
            <div class="tenant-image lux-explore-media">
                ${unavailableBadge}
                ${mediaHtml}
                <div class="lux-explore-price-badge">KES ${Number(house.price).toLocaleString()}</div>
            </div>
            <div class="tenant-card-padding lux-explore-content">
                <h2 class="lux-explore-card-title">${escapeHtml(house.title)}</h2>
                ${ratingHtml}
                <p class="lux-explore-desc">${escapeHtml((house.description || '').substring(0, 120))}...</p>
                <div class="tenant-meta lux-explore-meta-row2">
                    <span>${escapeHtml(house.location)}</span>
                    <span>${house.bedrooms} Beds · ${house.bathrooms} Baths</span>
                </div>
                <div class="tenant-actions lux-explore-actions">
                    ${viewDetailsHtml}
                    ${actionHtml}
                    ${chatHtml}
                </div>
            </div>
        `;

        return card;
    }

    function escapeAttr(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function buildMediaHtml(house) {
        const media = Array.isArray(house.media) ? house.media : [];

        const imageUrls = [];
        let videoUrl = null;

        media.forEach((item) => {
            const path = `${cfg.baseUrl}/assets/uploads/house_images/${item.image_path}`;
            if (/\.mp4$/i.test(item.image_path)) {
                videoUrl = path;
            } else {
                imageUrls.push(path);
            }
        });

        const caption = escapeAttr(house.title);

        if (videoUrl) {
            return `
                <div class="media-frame" data-video="${escapeAttr(videoUrl)}" data-caption="${caption}">
                    <video class="media-video" src="${videoUrl}" controls preload="metadata" playsinline></video>
                    <button type="button" class="media-enlarge-btn" aria-label="Enlarge video">⤢</button>
                </div>
            `;
        }

        if (imageUrls.length > 0) {

            const imagesJson = escapeAttr(JSON.stringify(imageUrls));

            const slides = imageUrls.map((url, index) => `
                <img class="media-slide${index === 0 ? ' is-active' : ''}"
                     src="${url}"
                     data-index="${index}"
                     alt="${caption} ${index + 1}">
            `).join('');

            const navHtml = imageUrls.length > 1 ? `
                <button type="button" class="media-carousel-btn media-carousel-prev" aria-label="Previous image">‹</button>
                <button type="button" class="media-carousel-btn media-carousel-next" aria-label="Next image">›</button>
                <div class="media-carousel-dots">
                    ${imageUrls.map((_, index) => `<span class="media-dot${index === 0 ? ' is-active' : ''}" data-index="${index}"></span>`).join('')}
                </div>
            ` : '';

            return `
                <div class="media-frame" data-images='${imagesJson}' data-caption="${caption}" data-current-index="0">
                    <div class="media-carousel">
                        <div class="media-carousel-track">${slides}</div>
                    </div>
                    ${navHtml}
                    <button type="button" class="media-enlarge-btn" aria-label="Enlarge image">⤢</button>
                </div>
            `;
        }

        return '<div class="tenant-image-placeholder">No Image</div>';
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.innerText = str || '';
        return div.innerHTML;
    }

})();