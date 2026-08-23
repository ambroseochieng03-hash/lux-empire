<?php

require_once '../../includes/init.php';
require_once '../../includes/auth_check.php';
requireRoleAccess('driver');

require_once '../../config/db.php';

$db = new Database();
$pdo = $db->connect();

$driver_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'Driver';

/* AVAILABLE REQUESTS */
$stmt = $pdo->prepare("
    SELECT *
    FROM truck_requests
    WHERE status = 'pending'
    ORDER BY requested_at DESC
");
$stmt->execute();
$availableRequests = $stmt->fetchAll();

/* ACTIVE TRIP */
$stmt2 = $pdo->prepare("
    SELECT *
    FROM truck_requests
    WHERE driver_id = ?
    AND status IN ('accepted', 'in_transit')
    LIMIT 1
");
$stmt2->execute([$driver_id]);
$activeTrip = $stmt2->fetch();

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
require_once '../../includes/sidebar.php';
?>

<style>
/* ================================
   RESPONSIVE FIX (SAFE PATCH ONLY)
   DOES NOT CHANGE DESKTOP (>992px)
================================ */

/* Tablet & below */
@media (max-width: 992px) {

    main {
        margin-left: 0 !important;
        width: 100% !important;
        padding: 25px !important;
    }

    /* prevent overflow issues */
    body {
        overflow-x: hidden;
    }
}

/* Phones */
@media (max-width: 768px) {

    main {
        padding: 18px !important;
    }

    /* headings scale down */
    h1 {
        font-size: 2rem !important;
        line-height: 1.3 !important;
    }

    /* grid stacking safety */
    div[style*="grid-template-columns"] {
        grid-template-columns: 1fr !important;
    }

    /* cards spacing tighter */
    .lux-card {
        padding: 20px !important;
        border-radius: 20px !important;
    }

    /* make quick actions stack nicely */
    a.lux-card {
        display: block;
        width: 100%;
    }
}

/* Small phones */
@media (max-width: 480px) {

    main {
        padding: 14px !important;
    }

    h1 {
        font-size: 1.7rem !important;
    }

    p {
        font-size: 0.95rem !important;
    }

    .lux-card {
        padding: 16px !important;
        border-radius: 16px !important;
    }
}
</style>

<div style="display:flex; min-height:100vh;">

<!-- MAIN -->
<main style="flex:1; padding:40px; margin-left:280px;">

    <!-- HERO -->
    <div style="margin-bottom:45px;">

        <h1 style="
            font-family:'Cinzel', serif;
            color:var(--gold);
            font-size:3rem;
            margin-bottom:15px;
        ">
            Driver Control Center
        </h1>

        <p style="
            color:var(--gray);
            line-height:1.9;
            max-width:750px;
        ">
            Welcome back, <?php echo htmlspecialchars($user_name); ?>.
            Manage transport requests, monitor active trips,
            and operate the LUX EMPIRE logistics network.
        </p>

    </div>

    <!-- STATS -->
    <div style="
        display:grid;
        grid-template-columns:repeat(auto-fit,minmax(230px,1fr));
        gap:25px;
        margin-bottom:45px;
    ">

        <div class="lux-card" style="padding:30px; border-radius:24px; text-align:center;">
            <div style="font-size:2.5rem;">📦</div>
            <h2 style="color:var(--gold); font-size:2.2rem; margin:15px 0 10px;">
                <?php echo count($availableRequests); ?>
            </h2>
            <p style="color:var(--gray);">Available Requests</p>
        </div>

        <div class="lux-card" style="padding:30px; border-radius:24px; text-align:center;">
            <div style="font-size:2.5rem;"></div>
            <h2 style="color:lightgreen; font-size:2.2rem; margin:15px 0 10px;">
                <?php echo $activeTrip ? '1' : '0'; ?>
            </h2>
            <p style="color:var(--gray);">Active Trips</p>
        </div>

        <div class="lux-card" style="padding:30px; border-radius:24px; text-align:center;">
            <div style="font-size:2.5rem;">🟢</div>
            <h2 style="color:var(--gold); font-size:1.5rem; margin:15px 0 10px;">
                ONLINE
            </h2>
            <p style="color:var(--gray);">Driver Status</p>
        </div>

    </div>

    <!-- QUICK ACTIONS -->
    <div style="
        display:grid;
        grid-template-columns:repeat(auto-fit,minmax(260px,1fr));
        gap:25px;
        margin-bottom:45px;
    ">

        <a href="available_requests.php" class="lux-card"
           style="padding:30px; border-radius:24px; text-decoration:none;">
            <h3 style="color:white;">Available Requests</h3>
            <p style="color:var(--gray);">View incoming transport jobs.</p>
        </a>

        <a href="active_trip.php" class="lux-card"
           style="padding:30px; border-radius:24px; text-decoration:none;">
            <h3 style="color:white;">Active Trip</h3>
            <p style="color:var(--gray);">Manage current delivery trip.</p>
        </a>

        <a href="location_tracker.php" class="lux-card"
           style="padding:30px; border-radius:24px; text-decoration:none;">
            <h3 style="color:white;">Live GPS Tracker</h3>
            <p style="color:var(--gray);">Share live driver location.</p>
        </a>

    </div>

    <!-- ACTIVE TRIP -->
    <div class="lux-card" style="padding:35px; border-radius:28px;">

        <h2 style="color:white; font-size:1.8rem; margin-bottom:25px;">
            Current Logistics Status
        </h2>

        <?php if ($activeTrip): ?>

            <div style="
                display:grid;
                grid-template-columns:repeat(auto-fit,minmax(250px,1fr));
                gap:25px;
            ">

                <div>
                    <div style="color:var(--gray);">Pickup</div>
                    <div style="color:white;">
                        <?php echo htmlspecialchars($activeTrip['pickup_location']); ?>
                    </div>
                </div>

                <div>
                    <div style="color:var(--gray);">Destination</div>
                    <div style="color:white;">
                        <?php echo htmlspecialchars($activeTrip['destination']); ?>
                    </div>
                </div>

                <div>
                    <div style="color:var(--gray);">Status</div>
                    <div style="color:lightgreen; font-weight:bold;">
                        <?php echo strtoupper($activeTrip['status']); ?>
                    </div>
                </div>

            </div>

        <?php else: ?>

            <div style="color:var(--gray); line-height:1.8;">
                No active trip currently assigned.
            </div>

        <?php endif; ?>

    </div>

</main>

</div>

<?php require_once '../../includes/footer.php'; ?>