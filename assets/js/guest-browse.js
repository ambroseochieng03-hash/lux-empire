/*
=========================================
LUX EMPIRE — GUEST BROWSE PAGE
=========================================
Requires window.LUX_BOOKING_CONFIG (baseUrl/csrfToken — reused from
the booking system) and window.openTenantRegisterModal (exposed by
assets/js/tenant-register-modal.js, which must load before this file).
=========================================
*/

(function () {

    const cfg = window.LUX_BOOKING_CONFIG;

    if (!cfg) {
        return;
    }

    document.addEventListener('DOMContentLoaded', () => {

        initTabs();
        initHouseDetailModal();
        initBookNowGate();
        initTruckFormGate();
    });

    /*
    =========================================
    TABS
    =========================================
    */

    function initTabs() {

        const tabButtons = document.querySelectorAll('.guest-browse-tab-btn');
        const housesSection = document.getElementById('guestHousesSection');
        const truckSection = document.getElementById('guestTruckSection');

        tabButtons.forEach((btn) => {
            btn.addEventListener('click', () => {

                const tab = btn.dataset.guestTab;

                tabButtons.forEach((b) => b.classList.toggle('is-active', b === btn));

                housesSection.hidden = tab !== 'houses';
                truckSection.hidden = tab !== 'truck';
            });
        });
    }

    /*
    =========================================
    HOUSE DETAIL QUICK-VIEW (api/houses/fetch_house.php)
    =========================================
    */

    function initHouseDetailModal() {

        const modal = document.getElementById('guestDetailModal');
        const content = document.getElementById('guestDetailModalContent');

        if (!modal) {
            return;
        }

        function closeModal() {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
        }

        modal.querySelectorAll('[data-guest-detail-close]').forEach((el) => {
            el.addEventListener('click', closeModal);
        });

        document.addEventListener('click', async (event) => {

            const btn = event.target.closest('.guest-view-details-btn');

            if (!btn) {
                return;
            }

            const houseId = btn.dataset.houseId;

            content.innerHTML = '<div class="guest-detail-loading">Loading...</div>';
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');

            try {

                const response = await fetch(`${cfg.baseUrl}/api/houses/fetch_house.php?id=${encodeURIComponent(houseId)}`);
                const data = await response.json();

                if (!data.success) {
                    content.innerHTML = `<p class="guest-detail-loading">${data.message || 'Could not load this property.'}</p>`;
                    return;
                }

                renderHouseDetail(content, data.house);

            } catch (error) {
                content.innerHTML = '<p class="guest-detail-loading">Network error. Please try again.</p>';
            }
        });
    }

    function renderHouseDetail(content, house) {

        const images = (house.images || []).map((path) => `${cfg.baseUrl}/assets/uploads/house_images/${path}`);
        const videoUrl = images.find((url) => /\.mp4$/i.test(url));
        const imageUrls = images.filter((url) => !/\.mp4$/i.test(url));

        let mediaHtml;

        if (videoUrl) {
            mediaHtml = `<video src="${videoUrl}" controls preload="metadata"></video>`;
        } else if (imageUrls.length > 0) {
            mediaHtml = `<img src="${imageUrls[0]}" alt="${escapeHtml(house.title)}">`;
        } else {
            mediaHtml = '';
        }

        content.innerHTML = `
            <div class="guest-detail-media">${mediaHtml}</div>
            <h2 class="guest-detail-title">${escapeHtml(house.title)}</h2>
            <div class="guest-detail-price">KES ${Number(house.price).toLocaleString()}</div>
            <div class="guest-detail-meta">
                <span>${escapeHtml(house.location)}</span>
                <span>${house.bedrooms} Bedrooms</span>
                <span>${house.bathrooms} Bathrooms</span>
            </div>
            <p class="guest-detail-desc">${escapeHtml(house.description)}</p>
            <button type="button" class="lux-explore-btn-book guest-book-btn"
                    data-house-id="${house.id}"
                    data-house-title="${escapeHtml(house.title)}">
                Book Now
            </button>
        `;
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.innerText = str || '';
        return div.innerHTML;
    }

    /*
    =========================================
    BOOK NOW — gated behind registration
    =========================================
    */

    function initBookNowGate() {

        document.addEventListener('click', (event) => {

            const btn = event.target.closest('.guest-book-btn');

            if (!btn) {
                return;
            }

            sessionStorage.setItem('luxPendingGuestAction', JSON.stringify({
                type: 'book_house',
                houseId: btn.dataset.houseId
            }));

            if (typeof window.openTenantRegisterModal === 'function') {
                window.openTenantRegisterModal();
            }
        });
    }

    /*
    =========================================
    TRUCK REQUEST FORM — gated behind registration
    =========================================
    */

    function initTruckFormGate() {

        const form = document.getElementById('guestTruckForm');

        if (!form) {
            return;
        }

        form.addEventListener('submit', (event) => {

            event.preventDefault();

            const fields = {
                pickup_location: document.getElementById('pickupLocationInput').value,
                destination: document.getElementById('destinationInput').value,
                price: document.getElementById('guestTruckPrice').value,
                pickup_lat: document.getElementById('pickupLatInput').value,
                pickup_lng: document.getElementById('pickupLngInput').value,
                destination_lat: document.getElementById('destinationLatInput').value,
                destination_lng: document.getElementById('destinationLngInput').value
            };

            if (!fields.pickup_location || !fields.destination || !fields.price) {
                return;
            }

            sessionStorage.setItem('luxPendingGuestAction', JSON.stringify({
                type: 'request_truck',
                fields: fields
            }));

            if (typeof window.openTenantRegisterModal === 'function') {
                window.openTenantRegisterModal();
            }
        });
    }

})();
