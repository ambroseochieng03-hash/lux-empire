<?php
require_once '../../includes/init.php';
require_once '../../includes/auth_check.php';
requireRoleAccess('driver');

require_once '../../config/db.php';

$db = new Database();
$pdo = $db->connect();

$driver_id = $_SESSION['user_id'];

// Fetch active trip
$stmt = $pdo->prepare("
    SELECT
        truck_requests.*,
        users.full_name,
        users.phone
    FROM truck_requests
    JOIN users
    ON truck_requests.tenant_id = users.id
    WHERE truck_requests.driver_id = ?
    AND truck_requests.status IN (
        'accepted',
        'in_transit',
        'cancelled'
    )
    LIMIT 1
");

$stmt->execute([$driver_id]);
$trip = $stmt->fetch();

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
require_once '../../includes/sidebar.php';
?>

<style>

/* ================================
   SAFE RESPONSIVE FIX (NO DESKTOP CHANGES)
================================= */

body {
    overflow-x: hidden;
}

/* ONLY adjust main layout on smaller screens */
@media (max-width: 992px) {

    main {
        margin-left: 0 !important;
        width: 100% !important;
        box-sizing: border-box;
    }
}

/* TABLETS + PHONES */
@media (max-width: 768px) {

    main {
        padding: 18px !important;
    }

    /* ONLY headings inside main content */
    main h1 {
        font-size: 2rem !important;
        line-height: 1.3;
    }

    /* Stack only THIS page grids safely */
    main div[style*="grid-template-columns"] {
        grid-template-columns: 1fr !important;
    }

    /* prevent overflow inside cards */
    main div,
    main p,
    main span {
        word-break: break-word;
    }

    /* make flex sections wrap ONLY inside main content blocks */
    main div[style*="justify-content:space-between"] {
        flex-wrap: wrap !important;
    }

    /* buttons full width ONLY in main content */
    main button {
        width: 100%;
    }

    /* soften cards on mobile */
    .lux-card {
        border-radius: 18px !important;
        padding: 22px !important;
    }
}

/* SMALL PHONES */
@media (max-width: 480px) {

    main {
        padding: 14px !important;
    }

    main h1 {
        font-size: 1.7rem !important;
    }
}

</style>

<div style="display:flex; min-height:100vh;">

<!-- MAIN -->
<main style="
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
            Active Logistics Trip
        </h1>

        <p style="
            color:var(--gray);
            line-height:1.9;
            max-width:700px;
        ">
            Monitor and manage your current
            transport assignment inside the
            LUX EMPIRE logistics network.
        </p>

    </div>

    <?php if ($trip): ?>

        <!-- TRIP CARD -->
        <div class="lux-card" style="
            padding:40px;
            border-radius:30px;
        ">

            <!-- STATUS -->
            <div style="
                display:flex;
                justify-content:space-between;
                align-items:center;
                flex-wrap:wrap;
                gap:20px;
                margin-bottom:35px;
            ">

                <div>

                    <h2 style="color:white; margin-bottom:10px;">
                        Trip #<?php echo $trip['id']; ?>
                    </h2>

                    <div style="color:var(--gray);">
                        Premium transport assignment
                    </div>

                </div>

                <div style="
                    background:
                    <?php
                        echo $trip['status'] === 'accepted'
                        ? 'rgba(255,165,0,0.15)'
                        : 'rgba(0,255,120,0.15)';
                    ?>;
                    color:
                    <?php
                        echo $trip['status'] === 'accepted'
                        ? 'orange'
                        : 'lightgreen';
                    ?>;
                    padding:14px 20px;
                    border-radius:16px;
                    font-weight:bold;
                ">
                    <?php echo strtoupper($trip['status']); ?>
                </div>

            </div>

            <!-- GRID -->
            <div style="
                display:grid;
                grid-template-columns:repeat(auto-fit,minmax(260px,1fr));
                gap:30px;
                margin-bottom:35px;
            ">

                <div>
                    <div style="color:var(--gray); margin-bottom:8px;">Tenant</div>
                    <div style="color:white; font-size:1.1rem;">
                        <?php echo htmlspecialchars($trip['full_name']); ?>
                    </div>
                </div>

                <div>
                    <div style="color:var(--gray); margin-bottom:8px;">Contact</div>
                    <div style="color:white; font-size:1.1rem;">
                        <?php echo htmlspecialchars($trip['phone']); ?>
                    </div>
                </div>

                <div>
                    <div style="color:var(--gray); margin-bottom:8px;">Trip Value</div>
                    <div style="color:var(--gold); font-size:1.3rem; font-weight:bold;">
                        KES <?php echo number_format($trip['price']); ?>
                    </div>
                </div>

            </div>

            <!-- ROUTE -->
            <div style="margin-bottom:35px;">

                <div style="margin-bottom:25px;">
                    <div style="color:var(--gray); margin-bottom:8px;">Pickup Location</div>
                    <div style="color:white;">
                        <?php echo htmlspecialchars($trip['pickup_location']); ?>
                    </div>
                </div>

                <div>
                    <div style="color:var(--gray); margin-bottom:8px;">Destination</div>
                    <div style="color:white;">
                        <?php echo htmlspecialchars($trip['destination']); ?>
                    </div>
                </div>

            </div>

            <!-- ACTIONS -->
            <div style="
                display:flex;
                gap:20px;
                flex-wrap:wrap;
            ">

                <?php if ($trip['status'] === 'accepted'): ?>

                    <!-- START -->
                    <form
                        action="../../api/trucks/update_trip_status.php"
                        method="POST"
                    >

                        <input
                            type="hidden"
                            name="trip_id"
                            value="<?php echo $trip['id']; ?>"
                        >

                        <input
                            type="hidden"
                            name="status"
                            value="in_transit"
                        >

                        <button
                            class="lux-btn"
                            style="
                                border:none;
                                padding:16px 28px;
                                border-radius:18px;
                                cursor:pointer;
                            "
                        >
                            Start Trip
                        </button>

                    </form>

                <?php elseif ($trip['status'] === 'in_transit'): ?>

                    <!-- COMPLETE -->
                    <form
                        action="../../api/trucks/update_trip_status.php"
                        method="POST"
                    >

                        <input
                            type="hidden"
                            name="trip_id"
                            value="<?php echo $trip['id']; ?>"
                        >

                        <input
                            type="hidden"
                            name="status"
                            value="completed"
                        >

                        <button
                            style="
                                background:lightgreen;
                                color:black;
                                border:none;
                                padding:16px 28px;
                                border-radius:18px;
                                cursor:pointer;
                                font-weight:bold;
                            "
                        >
                            Complete Trip
                        </button>

                    </form>

                <?php elseif ($trip['status'] === 'cancelled'): ?>

                <div style="
                    display:flex;
                    gap:15px;
                    align-items:center;
                    flex-wrap:wrap;
                ">

                    <!-- CANCELLED MESSAGE -->
                    <div style="
                        background:rgba(255,0,0,0.12);
                        color:#ff6b6b;
                        padding:18px 24px;
                        border-radius:18px;
                        font-weight:bold;
                    ">
                        Tenant cancelled this trip
                    </div>

                    <!-- DELETE BUTTON -->
                    <form
                        action="../../api/trucks/delete_trip.php"
                        method="POST"
                    >

                        <input
                            type="hidden"
                            name="trip_id"
                            value="<?php echo $trip['id']; ?>"
                        >

                        <button
                            type="submit"
                            style="
                                background:linear-gradient(135deg,#ff3b3b,#ff1744);
                                color:white;
                                border:none;
                                padding:16px 24px;
                                border-radius:18px;
                                cursor:pointer;
                                font-weight:bold;
                                box-shadow:0 8px 20px rgba(255,0,0,0.25);
                                transition:0.3s;
                            "

                            onmouseover="
                                this.style.transform='translateY(-2px)';
                            "

                            onmouseout="
                                this.style.transform='translateY(0)';
                            "
                        >
                            🗑 Delete Trip
                        </button>

                    </form>

                </div>

                <?php elseif ($trip['status'] === 'completed'): ?>

                    <div style="
                        background:rgba(0,255,120,0.12);
                        color:lightgreen;
                        padding:18px 24px;
                        border-radius:18px;
                        font-weight:bold;
                    ">
                        Trip completed
                    </div>

                <?php endif; ?>

            </div>

        </div>

    <?php else: ?>

        <div class="lux-card" style="
            padding:60px;
            border-radius:30px;
            text-align:center;
        ">
            <h2 style="color:white; margin-bottom:15px;">
                No Active Trip
            </h2>

            <p style="
                color:var(--gray);
                line-height:1.9;
                max-width:550px;
                margin:auto;
            ">
                You currently have no active transport assignment.
            </p>
        </div>

    <?php endif; ?>

</main>

</div>

<?php require_once '../../includes/footer.php'; ?>