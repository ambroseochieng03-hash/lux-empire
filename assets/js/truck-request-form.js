/*
=========================================
LUX EMPIRE — TRUCK REQUEST FORM
=========================================
Handles: instant/scheduled toggle, the items-list modal, and the
live (informational-only) price preview via
api/maps/calculate_distance.php. The actual submit/offline-draft
logic stays in request_truck.php's own inline script — this file
only manages this form's own UI state.
=========================================
*/

(function () {

    const tripTypeInput = document.getElementById('tripTypeInput');
    const instantBtn = document.getElementById('tripTypeInstantBtn');
    const scheduledBtn = document.getElementById('tripTypeScheduledBtn');
    const scheduledField = document.getElementById('scheduledAtField');
    const scheduledInput = document.getElementById('scheduledAtInput');

    function setTripType(type) {
        tripTypeInput.value = type;
        instantBtn.classList.toggle('is-active', type === 'instant');
        scheduledBtn.classList.toggle('is-active', type === 'scheduled');
        scheduledField.hidden = type !== 'scheduled';
        scheduledInput.required = type === 'scheduled';
    }

    instantBtn.addEventListener('click', () => setTripType('instant'));
    scheduledBtn.addEventListener('click', () => setTripType('scheduled'));

    /* ===================== ITEMS MODAL ===================== */

    const itemsModal = document.getElementById('itemsModal');
    const itemsRowsContainer = document.getElementById('itemsRowsContainer');
    const itemsDescriptionInput = document.getElementById('itemsDescriptionInput');
    const itemsCountBadge = document.getElementById('itemsCountBadge');

    function addItemRow(value = '') {
        const row = document.createElement('div');
        row.style.cssText = 'display:flex; gap:8px;';
        row.innerHTML = `
            <input type="text" class="request-input item-row-input" placeholder="e.g. Queen bed, 3 boxes" value="${value.replace(/"/g, '&quot;')}" style="flex:1;">
            <button type="button" class="remove-item-row-btn" style="background:none; border:none; color:#ff6b6b; font-size:1.3rem; cursor:pointer; padding:0 8px;">×</button>
        `;
        row.querySelector('.remove-item-row-btn').addEventListener('click', () => row.remove());
        itemsRowsContainer.appendChild(row);
    }

    function openItemsModal() {
        if (itemsRowsContainer.children.length === 0) {
            addItemRow();
        }
        itemsModal.style.display = 'flex';
    }

    function closeItemsModal() {
        itemsModal.style.display = 'none';
    }

    function saveItems() {
        const values = Array.from(itemsRowsContainer.querySelectorAll('.item-row-input'))
            .map((el) => el.value.trim())
            .filter((v) => v !== '');

        itemsDescriptionInput.value = values.join('\n');
        itemsCountBadge.textContent = values.length > 0 ? `(${values.length})` : '';
        closeItemsModal();
    }

    document.getElementById('openItemsModalBtn').addEventListener('click', openItemsModal);
    document.getElementById('addItemRowBtn').addEventListener('click', () => addItemRow());
    document.getElementById('saveItemsBtn').addEventListener('click', saveItems);
    document.getElementById('closeItemsModalBtn').addEventListener('click', closeItemsModal);
    document.getElementById('itemsModalOverlay').addEventListener('click', closeItemsModal);

    /* ===================== LIVE PRICE PREVIEW ===================== */

    const priceAmountEl = document.getElementById('pricePreviewAmount');
    const priceMetaEl = document.getElementById('pricePreviewMeta');

    let previewDebounceTimer = null;

    function maybeFetchPricePreview() {

        clearTimeout(previewDebounceTimer);

        previewDebounceTimer = setTimeout(async () => {

            const pickupLat = document.getElementById('pickupLatInput').value;
            const pickupLng = document.getElementById('pickupLngInput').value;
            const destLat = document.getElementById('destinationLatInput').value;
            const destLng = document.getElementById('destinationLngInput').value;

            if (!pickupLat || !pickupLng || !destLat || !destLng) {
                return;
            }

            priceAmountEl.textContent = 'Calculating...';

            try {
                const baseUrl = (window.LUX_TRUCK_FORM_CONFIG && window.LUX_TRUCK_FORM_CONFIG.baseUrl) || '';

                const params = new URLSearchParams({
                    pickup_lat: pickupLat, pickup_lng: pickupLng,
                    destination_lat: destLat, destination_lng: destLng
                });

                const response = await fetch(`${baseUrl}/api/maps/calculate_distance.php?${params.toString()}`);
                const data = await response.json();

                if (data.success) {
                    priceAmountEl.textContent = 'KES ' + Number(data.price).toLocaleString();
                    priceMetaEl.textContent = `${data.distance_km} km · approx. ${data.duration_text}`;
                } else {
                    priceAmountEl.textContent = 'Price will be calculated on submit';
                    priceMetaEl.textContent = '';
                }
            } catch (e) {
                priceAmountEl.textContent = 'Price will be calculated on submit';
                priceMetaEl.textContent = '';
            }

        }, 600);
    }

    // Polling instead of MutationObserver: request-truck-location.js
    // may set these hidden inputs' .value as a plain DOM property
    // (not an HTML attribute), which a MutationObserver watching
    // attributes would never see fire. Polling doesn't care how the
    // value changed, only that it did — so it works regardless of
    // that file's internals, without needing to read or modify it.
    let lastSeenCoords = '';

    setInterval(() => {

        const pickupLat = document.getElementById('pickupLatInput').value;
        const pickupLng = document.getElementById('pickupLngInput').value;
        const destLat = document.getElementById('destinationLatInput').value;
        const destLng = document.getElementById('destinationLngInput').value;

        const current = pickupLat + '|' + pickupLng + '|' + destLat + '|' + destLng;

        if (current !== lastSeenCoords) {
            lastSeenCoords = current;
            maybeFetchPricePreview();
        }

    }, 800);

})();
