<?php

require_once '../../includes/init.php';
require_once '../../includes/auth_check.php';
requireRoleAccess('driver');

require_once '../../config/db.php';

require_once '../../config/csrf.php';

$db = new Database();
$pdo = $db->connect();

$driver_id = (int) Session::user()['id'];
$csrfToken = Csrf::token();

// A driver who is already on a trip cannot take another job.
$activeTripStmt = $pdo->prepare("
    SELECT id FROM truck_requests
    WHERE driver_id = ? AND status IN ('accepted', 'arrived_at_pickup', 'in_transit')
    LIMIT 1
");
$activeTripStmt->execute([$driver_id]);
$hasActiveTrip = $activeTripStmt->fetchColumn() !== false;

/*
 * Both instant and scheduled pending requests are shown — a
 * scheduled request is visible immediately (per spec: "drivers
 * should be able to see it but can't accept it before the date
 * reaches"), just not acceptable until within the window computed
 * below. Ordered so instant + already-acceptable-scheduled trips
 * float to the top, since those are the ones a driver can actually
 * act on right now.
 */
$stmt = $pdo->prepare("
    SELECT
        truck_requests.*,
        users.full_name,
        users.phone
    FROM truck_requests
    JOIN users
    ON truck_requests.tenant_id = users.id
    WHERE truck_requests.status = 'pending'
    ORDER BY
        (truck_requests.trip_type = 'instant') DESC,
        truck_requests.scheduled_at ASC,
        truck_requests.requested_at DESC
");

$stmt->execute();
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
require_once '../../includes/sidebar.php';
?>

<style>

/* ================================
   DRIVER REQUESTS PAGE ONLY
   SAFE RESPONSIVE FIX
================================= */

.driver-requests-page {
    overflow-x: hidden;
}

@media (max-width: 992px) {
    .driver-requests-page .driver-main {
        margin-left: 0 !important;
        width: 100% !important;
        box-sizing: border-box;
    }
}

@media (max-width: 768px) {
    .driver-requests-page .driver-main {
        padding: 18px !important;
    }
    .driver-requests-page h1 {
        font-size: 2rem !important;
        line-height: 1.3;
    }
    .driver-requests-page div[style*="grid-template-columns"] {
        grid-template-columns: 1fr !important;
    }
    .driver-requests-page .lux-card > div {
        flex-wrap: wrap;
    }
    .driver-requests-page .lux-btn,
    .driver-requests-page button {
        width: 100%;
    }
    .driver-requests-page {
        word-break: break-word;
    }
}

@media (max-width: 480px) {
    .driver-requests-page .driver-main {
        padding: 14px !important;
    }
    .driver-requests-page h1 {
        font-size: 1.7rem !important;
    }
}

.trip-type-tag {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 10px;
    font-size: 0.8rem;
    font-weight: bold;
}

.trip-type-tag.instant {
    background: rgba(0, 255, 120, 0.12);
    color: lightgreen;
}

.trip-type-tag.scheduled {
    background: rgba(100, 180, 255, 0.12);
    color: #7fc4ff;
}

.accept-locked-btn {
    width: 100%;
    padding: 16px;
    border-radius: 18px;
    font-size: 1rem;
    border: 1px solid rgba(255,255,255,0.15);
    background: rgba(255,255,255,0.04);
    color: var(--gray);
    cursor: not-allowed;
}

</style>

<div class="driver-requests-page" style="display:flex; min-height:100vh;">
<main class="driver-main" style="
    flex:1;
    padding:40px;
    margin-left:280px;
">

    <!-- HERO -->
    <div style="margin-bottom:45px;">

        <h1 style="
            font-family:'Cinzel', serif;
            color:var(--gold);
            font-size:3rem;
            margin-bottom:15px;
        ">
            Available Logistics Requests
        </h1>

        <p style="
            color:var(--gray);
            line-height:1.9;
            max-width:750px;
        ">
            Explore available transport jobs across the
            LUX EMPIRE logistics network and accept
            premium moving requests.
        </p>

    </div>

    <?php if ($hasActiveTrip): ?>
        <div class="lux-card" style="padding:22px; border-radius:20px; margin-bottom:30px; border:1px solid rgba(255,165,0,0.45);">
            <i class="fa-solid fa-truck-fast" style="color:orange;"></i>
            <strong style="color:orange;">You have an active trip.</strong>
            <span style="color:var(--gray);">
                Finish it before accepting another job.
                <a href="<?php echo BASE_URL; ?>/driver/active-trip" style="color:var(--gold);">Go to your active trip →</a>
            </span>
        </div>
    <?php endif; ?>

    <!-- REQUEST GRID -->
    <div id="driverRequestsGrid" style="
        display:grid;
        grid-template-columns:repeat(auto-fit,minmax(340px,1fr));
        gap:30px;
    ">

        <?php if (count($requests) > 0): ?>

            <?php foreach ($requests as $request): ?>

                <?php
                    $isScheduled = ($request['trip_type'] === 'scheduled' && $request['scheduled_at'] !== null);
                    $windowOpensAtTimestamp = null;
                    $isAcceptableNow = true;

                    if ($isScheduled) {
                        $scheduledAtTimestamp = strtotime($request['scheduled_at']);
                        $windowOpensAtTimestamp = $scheduledAtTimestamp - (TRUCK_ACCEPT_WINDOW_MINUTES * 60);
                        $isAcceptableNow = (time() >= $windowOpensAtTimestamp);
                    }
                ?>

                <div class="lux-card" data-request-id="<?php echo (int) $request['id']; ?>" style="
                    padding:30px;
                    border-radius:28px;
                    position:relative;
                    overflow:hidden;
                ">

                    <!-- TOP -->
                    <div style="
                        display:flex;
                        justify-content:space-between;
                        align-items:center;
                        margin-bottom:20px;
                        flex-wrap:wrap;
                        gap:10px;
                    ">

                        <div>
                            <h2 style="color:white; margin-bottom:8px;">
                                Transport Request
                            </h2>

                            <div style="
                                color:var(--gray);
                                font-size:0.9rem;
                            ">
                                Request #<?php echo $request['id']; ?>
                            </div>
                        </div>

                        <div style="
                            background:rgba(255,215,0,0.15);
                            color:var(--gold);
                            padding:10px 16px;
                            border-radius:14px;
                            font-weight:bold;
                        ">
                            KES <?php echo number_format($request['price']); ?>
                        </div>
                    </div>

                    <!-- TRIP TYPE + SCHEDULE -->
                    <div style="margin-bottom:20px;">

                        <?php if ($isScheduled): ?>
                            <span class="trip-type-tag scheduled">
                                <i class="fa-solid fa-calendar-days"></i> Scheduled
                            </span>
                            <div style="color:var(--gray); font-size:0.85rem; margin-top:8px;">
                                Move time: <?php echo date('M d, Y g:i A', strtotime($request['scheduled_at'])); ?>
                            </div>
                        <?php else: ?>
                            <span class="trip-type-tag instant">
                                <i class="fa-solid fa-bolt"></i> Move Now
                            </span>
                        <?php endif; ?>

                        <?php if (!empty($request['distance_km'])): ?>
                            <div style="color:var(--gray); font-size:0.85rem; margin-top:6px;">
                                Approx. <?php echo htmlspecialchars($request['distance_km']); ?> km
                            </div>
                        <?php endif; ?>

                    </div>

                    <!-- TENANT -->
                    <div style="margin-bottom:20px;">
                        <div style="color:var(--gray); margin-bottom:6px;">Tenant</div>
                        <div style="color:white;">
                            <?php echo htmlspecialchars($request['full_name']); ?>
                        </div>
                    </div>

                    <!-- PICKUP -->
                    <div style="margin-bottom:20px;">
                        <div style="color:var(--gray); margin-bottom:6px;">Pickup Location</div>
                        <div style="color:white;">
                            <?php echo htmlspecialchars($request['pickup_location']); ?>
                        </div>
                    </div>

                    <!-- DESTINATION -->
                    <div style="margin-bottom:<?php echo !empty($request['items_description']) ? '20' : '25'; ?>px;">
                        <div style="color:var(--gray); margin-bottom:6px;">Destination</div>
                        <div style="color:white;">
                            <?php echo htmlspecialchars($request['destination']); ?>
                        </div>
                    </div>

                    <!-- ITEMS -->
                    <?php if (!empty($request['items_description'])): ?>
                        <div style="margin-bottom:25px;">
                            <div style="color:var(--gray); margin-bottom:6px;">Items</div>
                            <div style="color:white; white-space:pre-line; font-size:0.9rem;">
                                <?php echo htmlspecialchars($request['items_description']); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- STATUS -->
                    <div style="margin-bottom:25px;">
                        <span style="
                            background:rgba(255,165,0,0.15);
                            color:orange;
                            padding:10px 15px;
                            border-radius:12px;
                            font-weight:bold;
                            font-size:0.9rem;
                        ">
                            PENDING REQUEST
                        </span>
                    </div>

                    <!-- MESSAGE TENANT -->
                    <button type="button"
                            class="lux-btn chat-starter-btn"
                            data-tenant-id="<?php echo (int) $request['tenant_id']; ?>"
                            data-truck-request-id="<?php echo (int) $request['id']; ?>"
                            data-other-name="<?php echo htmlspecialchars($request['full_name']); ?>"
                            style="
                                width:100%;
                                border:1px solid var(--gold);
                                background:rgba(255,255,255,0.06);
                                color:var(--gold);
                                padding:14px;
                                border-radius:16px;
                                font-weight:bold;
                                cursor:pointer;
                                margin-bottom:12px;
                            ">
                        <i class="fa-solid fa-comment-dots"></i> Message Tenant
                    </button>

                    <!-- ACTION -->
                    <?php if ($hasActiveTrip): ?>

                        <button type="button" class="accept-locked-btn" disabled>
                            <i class="fa-solid fa-lock"></i> Finish your active trip first
                        </button>

                    <?php elseif ($isAcceptableNow): ?>

                        <button type="button" class="lux-btn accept-request-btn"
                                data-request-id="<?php echo (int) $request['id']; ?>"
                                style="
                                width:100%;
                                border:none;
                                padding:16px;
                                border-radius:18px;
                                cursor:pointer;
                                font-size:1rem;
                            ">
                            Accept Request
                        </button>

                    <?php else: ?>

                        <button type="button" class="accept-locked-btn" disabled
                                data-window-opens-at="<?php echo (int) $windowOpensAtTimestamp; ?>">
                            <i class="fa-solid fa-lock"></i>
                            Opens in <span class="countdown-text">calculating...</span>
                        </button>

                    <?php endif; ?>

                </div>

            <?php endforeach; ?>

        <?php else: ?>

            <div class="lux-card" id="driverRequestsEmptyState" style="
                padding:50px;
                border-radius:28px;
                text-align:center;
                grid-column:1/-1;
            ">
                <div style="font-size:4rem; margin-bottom:20px;"></div>

                <h2 style="color:white; margin-bottom:15px;">
                    No Requests Available
                </h2>

                <p style="
                    color:var(--gray);
                    max-width:500px;
                    margin:auto;
                    line-height:1.8;
                ">
                    There are currently no pending logistics requests.
                </p>
            </div>

        <?php endif; ?>

    </div>

</main>

</div>

<script>
    window.LUX_DRIVER_REQUESTS_CONFIG = {
        baseUrl: "<?php echo BASE_URL; ?>",
        csrfToken: "<?php echo htmlspecialchars($csrfToken); ?>"
    };
</script>
<script src="<?php echo BASE_URL; ?>/assets/js/driver-requests.js"></script>
<script>
/*
 * Live countdown text for locked "Opens in..." buttons — purely
 * cosmetic. If polling/refresh already reloads this page
 * periodically, the server-side re-check on each load is what
 * actually unlocks a button; this just keeps the displayed text
 * accurate between reloads instead of sitting stale.
 */
(function () {

    function formatRemaining(seconds) {
        if (seconds <= 0) return 'now — refresh the page';
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        if (hours > 0) return hours + 'h ' + minutes + 'm';
        return minutes + 'm';
    }

    function tick() {
        document.querySelectorAll('.accept-locked-btn[data-window-opens-at]').forEach((btn) => {
            const opensAt = parseInt(btn.dataset.windowOpensAt, 10);
            const remaining = opensAt - Math.floor(Date.now() / 1000);
            const textEl = btn.querySelector('.countdown-text');
            if (textEl) {
                textEl.textContent = formatRemaining(remaining);
            }
        });
    }

    tick();
    setInterval(tick, 30000);

})();
</script>

<?php require_once '../../includes/chat_starter_modal.php'; ?>

<?php require_once '../../includes/footer.php'; ?>