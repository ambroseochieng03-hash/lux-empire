<?php

require_once '../../includes/init.php';
require_once '../../includes/auth_check.php';
requireRoleAccess('driver');

require_once '../../config/db.php';

$db = new Database();
$pdo = $db->connect();

$driver_id = (int) Session::user()['id'];

// Fetch pending requests
$stmt = $pdo->prepare("
    SELECT
        truck_requests.*,
        users.full_name,
        users.phone
    FROM truck_requests
    JOIN users
    ON truck_requests.tenant_id = users.id
    WHERE truck_requests.status = 'pending'
    ORDER BY truck_requests.requested_at DESC
");

$stmt->execute();
$requests = $stmt->fetchAll();

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

/* tablet */
@media (max-width: 992px) {

    .driver-requests-page .driver-main {
        margin-left: 0 !important;
        width: 100% !important;
        box-sizing: border-box;
    }
}

/* mobile only */
@media (max-width: 768px) {

    .driver-requests-page .driver-main {
        padding: 18px !important;
    }

    /* ONLY this page headings */
    .driver-requests-page h1 {
        font-size: 2rem !important;
        line-height: 1.3;
    }

    /* ONLY grids inside this page */
    .driver-requests-page div[style*="grid-template-columns"] {
        grid-template-columns: 1fr !important;
    }

    /* ONLY flex containers in this page (safe scoped) */
    .driver-requests-page .lux-card > div {
        flex-wrap: wrap;
    }

    /* buttons ONLY inside this page */
    .driver-requests-page .lux-btn,
    .driver-requests-page button {
        width: 100%;
    }

    /* prevent text overflow only in this page */
    .driver-requests-page {
        word-break: break-word;
    }
}

/* small phones */
@media (max-width: 480px) {

    .driver-requests-page .driver-main {
        padding: 14px !important;
    }

    .driver-requests-page h1 {
        font-size: 1.7rem !important;
    }
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

    <!-- REQUEST GRID -->
    <div style="
        display:grid;
        grid-template-columns:repeat(auto-fit,minmax(340px,1fr));
        gap:30px;
    ">

        <?php if (count($requests) > 0): ?>

            <?php foreach ($requests as $request): ?>

                <div class="lux-card" style="
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
                        margin-bottom:25px;
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
                    <div style="margin-bottom:25px;">
                        <div style="color:var(--gray); margin-bottom:6px;">Destination</div>
                        <div style="color:white;">
                            <?php echo htmlspecialchars($request['destination']); ?>
                        </div>
                    </div>

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
                    <form action="<?php echo BASE_URL; ?>/api/trucks/accept_request.php" method="POST">

                        <input type="hidden" name="request_id"
                               value="<?php echo $request['id']; ?>">

                        <button type="submit" class="lux-btn" style="
                            width:100%;
                            border:none;
                            padding:16px;
                            border-radius:18px;
                            cursor:pointer;
                            font-size:1rem;
                        ">
                            Accept Request
                        </button>

                    </form>

                </div>

            <?php endforeach; ?>

        <?php else: ?>

            <div class="lux-card" style="
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

<?php require_once '../../includes/chat_starter_modal.php'; ?>

<?php require_once '../../includes/footer.php'; ?>