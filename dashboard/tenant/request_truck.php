<?php

require_once '../../includes/init.php';
require_once '../../includes/auth_check.php';
requireRoleAccess('tenant');

$existingTripStmt = (new Database())->connect()->prepare("
    SELECT id, status, pickup_location, destination
    FROM truck_requests
    WHERE tenant_id = :tenant_id AND status IN ('pending', 'accepted', 'arrived_at_pickup', 'in_transit')
    ORDER BY requested_at DESC
");
$existingTripStmt->execute([':tenant_id' => Session::user()['id']]);
$existingTrips = $existingTripStmt->fetchAll(PDO::FETCH_ASSOC);

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
require_once '../../includes/sidebar.php';
?>

<style>

/* =========================================
   RESPONSIVE REQUEST TRUCK PAGE
========================================= */

.request-layout {
    display:flex;
    min-height:100vh;
}

.request-main {
    flex:1;
    padding:40px;
    margin-left:280px;
}

.request-grid {
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(340px,1fr));
    gap:35px;
    align-items:start;
}

.request-card {
    padding:35px;
    border-radius:28px;
}

.request-input {
    width:100%;
    padding:16px;
    border:1px solid var(--lux-card-border);
    border-radius:16px;
    background:var(--glass);
    color:var(--white);
    outline:none;
    font-size:1rem;
}

.request-input::placeholder {
    color:var(--gray);
}

.info-stack {
    display:flex;
    flex-direction:column;
    gap:25px;
}

/* =========================================
   MOBILE RESPONSIVE
========================================= */

@media (max-width: 768px) {

    .request-main {

        margin-left:0;
        padding:
            110px 18px 30px 18px;
    }

    .request-grid {

        grid-template-columns:1fr;
        gap:22px;
    }

    .request-card {

        padding:22px;
        border-radius:24px;
    }

    .request-main h1 {

        font-size:2rem !important;
        line-height:1.3;
    }

    .request-main p {

        font-size:0.95rem;
    }

    .request-input {

        font-size:16px;
    }
}

.trip-type-btn.is-active {
    background: linear-gradient(135deg, var(--gold), var(--gold-secondary)) !important;
    color: var(--black) !important;
}

</style>

<div class="request-layout">

    <!-- MAIN -->
    <main class="request-main">

        <!-- HEADER -->
        <?php if (!empty($existingTrips)): ?>
            <div class="lux-card" style="padding:22px; border-radius:20px; margin-bottom:30px; border:1px solid var(--lux-info);">
                <i class="fa-solid fa-circle-info" style="color:var(--lux-info);"></i>
                <strong style="color:var(--lux-info);">You already have <?php echo count($existingTrips); ?> move request(s) in progress.</strong>
                <div style="color:var(--gray); margin-top:8px;">
                    That's fine — you can request another. Just know they'll run independently.
                    <a href="<?php echo BASE_URL; ?>/tenant/my-bookings" style="color:var(--gold);">View your current requests →</a>
                </div>
            </div>
        <?php endif; ?>

        <div style="margin-bottom:40px;">

            <h1 style="
                font-family:'Cinzel', serif;
                color:var(--gold);
                font-size:3rem;
                margin-bottom:15px;
            ">
                Luxury Moving Service
            </h1>

            <p style="
                color:var(--gray);
                max-width:700px;
                line-height:1.8;
            ">
                Request elite logistics and professional moving services
                powered by LUX EMPIRE intelligent transport system.
            </p>

        </div>

        <!-- GRID -->
        <div class="request-grid">

            <!-- FORM -->
            <div class="lux-card request-card">

                <h2 style="
                    color:var(--white);
                    margin-bottom:25px;
                    font-size:1.8rem;
                ">
                    Request Truck
                </h2>

                <form
                    id="requestTruckPlainForm"
                    action="<?php echo BASE_URL; ?>/api/trucks/request_truck.php"
                    method="POST"
                >
                    <input type="hidden" name="idempotency_key" id="requestTruckIdemKey" value="">
                    <input type="hidden" name="items_description" id="itemsDescriptionInput" value="">

                    <!-- TRIP TYPE TOGGLE -->
                    <div style="margin-bottom:26px;">
                        <label style="display:block; margin-bottom:10px; color:var(--gold); font-weight:600;">
                            When do you need this move?
                        </label>

                        <div style="display:flex; gap:12px;">
                            <button type="button" id="tripTypeInstantBtn" class="lux-btn trip-type-btn is-active" data-trip-type="instant" style="flex:1; padding:14px;">
                                <i class="fa-solid fa-bolt"></i> Move Now
                            </button>
                            <button type="button" id="tripTypeScheduledBtn" class="lux-btn trip-type-btn" data-trip-type="scheduled" style="flex:1; padding:14px;">
                                <i class="fa-solid fa-calendar-days"></i> Schedule for Later
                            </button>
                        </div>

                        <input type="hidden" name="trip_type" id="tripTypeInput" value="instant">
                    </div>

                    <!-- SCHEDULED DATE/TIME — hidden unless "Schedule for Later" is active -->
                    <div style="margin-bottom:22px;" id="scheduledAtField" hidden>
                        <label style="display:block; margin-bottom:10px; color:var(--gold); font-weight:600;">
                            Move Date &amp; Time
                        </label>

                        <input
                            type="datetime-local"
                            name="scheduled_at"
                            id="scheduledAtInput"
                            class="request-input"
                        >

                        <div style="color:var(--gray); font-size:0.85rem; margin-top:8px;">
                            Must be at least <?php echo TRUCK_MIN_SCHEDULE_LEAD_MINUTES; ?> minutes from now, so drivers have a fair chance to accept.
                        </div>
                    </div>

                    <!-- PICKUP -->
                    <div style="margin-bottom:22px;">
                        <label style="display:block; margin-bottom:10px; color:var(--gold); font-weight:600;">
                            Pickup Location
                        </label>

                        <div style="display:flex; gap:10px;">
                            <input
                                type="text"
                                name="pickup_location"
                                id="pickupLocationInput"
                                placeholder="Enter pickup location"
                                required
                                class="request-input"
                            >

                            <button type="button" id="useMyLocationBtn" class="lux-btn" style="white-space:nowrap; padding:0 18px;">
                                <i class="fa-solid fa-location-crosshairs"></i>
                            </button>
                        </div>
                    </div>

                    <!-- DESTINATION -->
                    <div style="margin-bottom:22px;">

                        <label style="
                            display:block;
                            margin-bottom:10px;
                            color:var(--gold);
                            font-weight:600;
                        ">
                            Destination
                        </label>

                        <input
                            type="text"
                            name="destination"
                            id="destinationInput"
                            placeholder="Enter destination"
                            required
                            class="request-input"
                        >

                    </div>

                    <!-- ITEMS -->
                    <div style="margin-bottom:22px;">
                        <button type="button" id="openItemsModalBtn" class="lux-btn" style="width:100%; padding:14px; background:var(--glass); color:var(--white);">
                            <i class="fa-solid fa-list-check"></i> List Your Items <span id="itemsCountBadge" style="color:var(--gold);"></span>
                        </button>
                    </div>

                    <!-- LIVE PRICE PREVIEW — informational only. The
                         real price is ALWAYS recomputed server-side
                         on submit; nothing here is ever trusted. -->
                    <div style="margin-bottom:30px; padding:18px; border-radius:16px; background:rgba(212,175,55,0.08); border:1px solid rgba(212,175,55,0.25);" id="pricePreviewBox">
                        <div style="color:var(--gray); font-size:0.85rem; margin-bottom:6px;">Estimated Price</div>
                        <div style="color:var(--gold); font-size:1.4rem; font-weight:bold;" id="pricePreviewAmount">
                            Enter pickup &amp; destination to see a price
                        </div>
                        <div style="color:var(--gray); font-size:0.8rem; margin-top:4px;" id="pricePreviewMeta"></div>
                    </div>

                    <!-- HIDDEN GPS -->
                    <input type="hidden" name="pickup_lat" id="pickupLatInput">
                    <input type="hidden" name="pickup_lng" id="pickupLngInput">
                    <input type="hidden" name="destination_lat" id="destinationLatInput">
                    <input type="hidden" name="destination_lng" id="destinationLngInput">

                    <!-- BUTTON -->
                    <button
                        type="submit"
                        class="lux-btn"
                        id="requestTruckSubmitBtn"
                        style="width:100%; padding:18px; border:none; border-radius:18px; cursor:pointer; font-size:1rem;"
                    >
                        <i class="fa-solid fa-truck-fast"></i> Request Luxury Truck
                    </button>

                </form>

            </div>

            <!-- INFO PANEL -->
            <div class="info-stack">

                <!-- SERVICE CARD -->
                <div class="lux-card request-card">

                    <h2 style="
                        color:var(--gold);
                        margin-bottom:20px;
                    ">
                        Why Use Our Logistics?
                    </h2>

                    <div style="
                        display:flex;
                        flex-direction:column;
                        gap:18px;
                    ">

                        <?php
                        $benefits = [

                            "Professional verified drivers",
                            "Real-time GPS tracking",
                            "Fast property relocation",
                            "Secure and reliable transport",
                            "Mobile live updates"
                        ];

                        foreach ($benefits as $benefit):
                        ?>

                        <div style="
                            background:var(--glass);
                            border:1px solid var(--lux-card-border);
                            padding:15px;
                            border-radius:14px;
                            color:var(--white);
                        ">
                            <?php echo $benefit; ?>
                        </div>

                        <?php endforeach; ?>

                    </div>

                </div>

                <!-- STATUS CARD -->
                <div class="lux-card request-card">

                    <h2 style="
                        color:var(--white);
                        margin-bottom:20px;
                    ">
                        Smart Logistics
                    </h2>

                    <p style="
                        color:var(--gray);
                        line-height:1.9;
                    ">
                        LUX EMPIRE integrates modern logistics technology
                        allowing tenants to request moving services,
                        track truck movement in real-time,
                        and manage relocation seamlessly.
                    </p>

                </div>

            </div>

        </div>

    </main>

    <!-- ITEMS MODAL — reusable pattern (overlay + box), matches the
        existing lux-booking-modal visual language rather than a new
        style system. -->
    <div id="itemsModal" style="display:none; position:fixed; inset:0; z-index:2000; align-items:center; justify-content:center; padding:20px;">
        <div id="itemsModalOverlay" style="position:absolute; inset:0; background:rgba(0,0,0,0.75); backdrop-filter:blur(4px);"></div>

        <div style="position:relative; max-width:480px; width:100%; max-height:80vh; overflow-y:auto; background:var(--lux-modal-bg); border:1px solid var(--lux-modal-border); border-radius:22px; padding:28px;">

            <h2 style="color:var(--gold); font-family:'Cinzel', serif; font-size:1.3rem; margin-bottom:8px;">
                What are you moving?
            </h2>
            <p style="color:var(--gray); font-size:0.9rem; margin-bottom:20px;">
                List the items so your driver can prepare — optional, but helps them bring the right vehicle and manpower.
            </p>

            <div id="itemsRowsContainer" style="display:flex; flex-direction:column; gap:10px; margin-bottom:16px;"></div>

            <button type="button" id="addItemRowBtn" class="lux-btn" style="width:100%; background:var(--glass); color:var(--white); padding:12px; margin-bottom:20px;">
                <i class="fa-solid fa-plus"></i> Add Another Item
            </button>

            <div style="display:flex; gap:12px;">
                <button type="button" id="saveItemsBtn" class="lux-btn" style="flex:1; padding:14px;">Save</button>
                <button type="button" id="closeItemsModalBtn" style="flex:1; padding:14px; background:var(--glass); color:var(--white); border:1px solid var(--lux-card-border); border-radius:14px; cursor:pointer;">Cancel</button>
            </div>

        </div>
    </div>

</div>

<script src="<?php echo BASE_URL; ?>/assets/js/request-truck-location.js"></script>

<script>
    window.LUX_TRUCK_FORM_CONFIG = { baseUrl: "<?php echo BASE_URL; ?>" };
</script>
<script src="<?php echo BASE_URL; ?>/assets/js/truck-request-form.js"></script>

<script src="<?php echo BASE_URL; ?>/assets/js/idempotency.js"></script>
<script>
document.getElementById('requestTruckPlainForm').addEventListener('submit', function () {
    document.getElementById('requestTruckIdemKey').value = window.LuxIdempotency.get(this);
});
</script>

<script src="<?php echo BASE_URL; ?>/assets/js/offline-db.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/offline-drafts.js"></script>
<script>
(function () {
    const form = document.getElementById('requestTruckPlainForm');
    if (!form) return;

    form.addEventListener('submit', async function (e) {

        e.preventDefault();

        const submitButton = form.querySelector('button[type="submit"]');
        const originalHtml = submitButton.innerHTML;
        submitButton.disabled = true;
        submitButton.textContent = 'Submitting...';

        const payload = {
            trip_type: document.getElementById('tripTypeInput').value,
            scheduled_at: document.getElementById('scheduledAtInput').value,
            items_description: document.getElementById('itemsDescriptionInput').value,
            pickup_location: document.getElementById('pickupLocationInput').value,
            destination: document.getElementById('destinationInput').value,
            pickup_lat: document.getElementById('pickupLatInput').value,
            pickup_lng: document.getElementById('pickupLngInput').value,
            destination_lat: document.getElementById('destinationLatInput').value,
            destination_lng: document.getElementById('destinationLngInput').value,
            idempotency_key: window.LuxIdempotency.get(form)
        };

        try {

            const body = new URLSearchParams(payload);
            const response = await fetch(form.action, {
                method: 'POST',
                body,
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin'
            });

            // request_truck.php currently redirects rather than
            // returning JSON — treat any successful HTTP response as
            // success and just follow through to my_bookings.php,
            // matching today's post-submit behavior.
            if (response.ok) {
                const baseUrl = (window.LUX_TRUCK_FORM_CONFIG && window.LUX_TRUCK_FORM_CONFIG.baseUrl) || '';
                window.location.href = baseUrl + '/tenant/my-bookings?success=' + encodeURIComponent('Truck request submitted successfully!');
                return;
            }

            throw new Error('Request failed.');

        } catch (error) {

            // No network (or a genuine failure indistinguishable from
            // one at this layer) — save as a draft instead of losing
            // what the person typed.
            if (window.LuxOfflineDB) {

                await window.LuxOfflineDB.saveDraft({
                    type: 'truck_request',
                    endpoint: form.action,
                    payload: payload
                });

                if (window.LuxOfflineSync) {
                    window.LuxOfflineSync.refreshBanner();
                }

                alert('You appear to be offline. Your truck request has been saved and will be submitted automatically once you\'re back online.');

                form.reset();

            } else {
                alert('Unable to submit right now. Please check your connection and try again.');
            }
        }

        submitButton.disabled = false;
        submitButton.innerHTML = originalHtml;
    });
})();
</script>

<script
    async
    defer
    src="https://maps.googleapis.com/maps/api/js?key=<?php echo GOOGLE_MAPS_API_KEY; ?>&libraries=places&callback=initRequestTruckMap">
</script>

<?php require_once '../../includes/footer.php'; ?>