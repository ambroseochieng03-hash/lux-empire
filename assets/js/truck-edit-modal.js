/*
=========================================
LUX EMPIRE — TRUCK REQUEST EDIT MODAL
=========================================
Only items_description (both types) and scheduled_at (scheduled
only) are editable — pickup/destination/price are immutable once
created, since price is tied to the original coordinates. Triggered
by .edit-truck-trigger-btn buttons carrying data-* attributes with
the current values (see dashboard/tenant/my_bookings.php).
=========================================
*/

(function () {

    const cfg = window.LUX_BOOKING_CONFIG;
    if (!cfg || !window.LuxModal) return;

    function buildItemsRowsHtml(itemsText) {
        const items = (itemsText || '').split('\n').map((s) => s.trim()).filter((s) => s !== '');
        if (items.length === 0) items.push('');

        return items.map((val) => `
            <div style="display:flex; gap:8px; margin-bottom:10px;">
                <input type="text" class="request-input edit-item-row-input" value="${val.replace(/"/g, '&quot;')}" placeholder="e.g. Queen bed, 3 boxes" style="flex:1; padding:12px; border:none; border-radius:12px; background:rgba(255,255,255,0.06); color:white;">
                <button type="button" class="remove-edit-item-row-btn" style="background:none; border:none; color:#ff6b6b; font-size:1.3rem; cursor:pointer;">×</button>
            </div>
        `).join('');
    }

    function openEditModal(button) {

        const requestId = button.dataset.requestId;
        const tripType = button.dataset.tripType;
        const scheduledAtRaw = button.dataset.scheduledAt || '';
        const itemsText = button.dataset.items || '';

        // scheduledAtRaw comes from the DB as "Y-m-d H:i:s" — datetime-local wants "Y-m-dTH:i".
        const scheduledAtValue = scheduledAtRaw ? scheduledAtRaw.replace(' ', 'T').slice(0, 16) : '';

        const scheduledFieldHtml = tripType === 'scheduled' ? `
            <div style="margin-bottom:20px;">
                <label style="display:block; margin-bottom:8px; color:var(--gold); font-weight:600;">Move Date &amp; Time</label>
                <input type="datetime-local" id="editScheduledAtInput" value="${scheduledAtValue}"
                       style="width:100%; padding:14px; border:none; border-radius:14px; background:rgba(255,255,255,0.06); color:white;">
            </div>
        ` : '';

        window.LuxModal.open(`
            <h2 style="color:gold; font-family:'Cinzel', serif; font-size:1.3rem; margin-bottom:6px;">Edit Truck Request</h2>
            <p style="color:var(--gray); font-size:0.85rem; margin-bottom:20px;">
                Pickup, destination, and price can't be changed after a request is created.
            </p>

            <div id="editModalError" style="display:none; background:rgba(255,0,0,0.08); border:1px solid rgba(255,0,0,0.25); color:#ffb3b3; padding:12px; border-radius:12px; margin-bottom:18px;"></div>

            ${scheduledFieldHtml}

            <label style="display:block; margin-bottom:8px; color:var(--gold); font-weight:600;">Items</label>
            <div id="editItemsRowsContainer">${buildItemsRowsHtml(itemsText)}</div>

            <button type="button" id="addEditItemRowBtn" class="lux-btn" style="width:100%; background:rgba(255,255,255,0.06); color:white; padding:10px; margin:8px 0 22px;">
                <i class="fa-solid fa-plus"></i> Add Item
            </button>

            <div style="display:flex; gap:12px;">
                <button type="button" id="saveTruckEditBtn" class="lux-btn" style="flex:1; padding:14px;">Save Changes</button>
                <button type="button" id="cancelTruckEditBtn" style="flex:1; padding:14px; background:rgba(255,255,255,0.06); color:white; border:1px solid rgba(255,255,255,0.15); border-radius:14px; cursor:pointer;">Close</button>
            </div>
        `);

        const box = window.LuxModal.box();

        box.querySelector('#cancelTruckEditBtn').addEventListener('click', window.LuxModal.close);

        box.querySelector('#addEditItemRowBtn').addEventListener('click', () => {
            const container = box.querySelector('#editItemsRowsContainer');
            container.insertAdjacentHTML('beforeend', buildItemsRowsHtml('')); // adds one empty row
            wireRemoveButtons(box);
        });

        wireRemoveButtons(box);

        box.querySelector('#saveTruckEditBtn').addEventListener('click', async () => {

            const errorEl = box.querySelector('#editModalError');
            errorEl.style.display = 'none';

            const itemsValues = Array.from(box.querySelectorAll('.edit-item-row-input'))
                .map((el) => el.value.trim())
                .filter((v) => v !== '');

            const payload = {
                request_id: requestId,
                items_description: itemsValues.join('\n'),
                csrf_token: cfg.csrfToken
            };

            const scheduledInput = box.querySelector('#editScheduledAtInput');
            if (scheduledInput) {
                payload.scheduled_at = scheduledInput.value;
            }

            const saveBtn = box.querySelector('#saveTruckEditBtn');
            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving...';

            try {

                const body = new URLSearchParams(payload);
                const response = await fetch(`${cfg.baseUrl}/api/trucks/update_truck_request.php`, {
                    method: 'POST', body, headers: { 'Accept': 'application/json' }
                });
                const data = await response.json();

                if (!data.success) {
                    errorEl.textContent = data.message || 'Unable to save changes.';
                    errorEl.style.display = 'block';
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Save Changes';
                    return;
                }

                // Simplest correct path: reload so the card re-renders
                // from the server with the new values, rather than
                // duplicating my_bookings.php's card-rendering logic
                // here in JS.
                window.location.reload();

            } catch (error) {
                errorEl.textContent = 'Network error. Please try again.';
                errorEl.style.display = 'block';
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save Changes';
            }
        });
    }

    function wireRemoveButtons(box) {
        box.querySelectorAll('.remove-edit-item-row-btn').forEach((btn) => {
            btn.onclick = () => btn.closest('div').remove();
        });
    }

    document.addEventListener('click', (event) => {
        const btn = event.target.closest('.edit-truck-trigger-btn');
        if (btn) openEditModal(btn);
    });

})();
