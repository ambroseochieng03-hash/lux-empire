<?php
require_once '../../includes/auth_check.php';
requireRoleAccess('tenant');

require_once '../../classes/Booking.php';
require_once '../../config/db.php';

$bookingModel = new Booking();

$bookings = $bookingModel->getBookingsByTenant(
    $_SESSION['user_id']
);

$db = new Database();
$pdo = $db->connect();

/*
|--------------------------------------------------------------------------
| FETCH TRUCK REQUESTS
|--------------------------------------------------------------------------
*/

$truckStmt = $pdo->prepare("
    SELECT
        truck_requests.*,
        users.full_name AS driver_name,
        users.phone AS driver_phone

    FROM truck_requests

    LEFT JOIN users
    ON truck_requests.driver_id = users.id

    WHERE truck_requests.tenant_id = ?

    ORDER BY truck_requests.requested_at DESC
");

$truckStmt->execute([
    $_SESSION['user_id']
]);

$truckRequests = $truckStmt->fetchAll();

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
require_once '../../includes/sidebar.php';
?>

<div style="
    display:flex;
    min-height:100vh;
">

    <!-- MAIN -->
    <main class="tenant-main" style="
        flex:1;
        padding:40px;
        margin-left:280px;
        min-width:0;
    ">

        <!-- HEADER -->
        <div style="margin-bottom:35px;">

            <h1 class="tenant-title" style="
                font-family:'Cinzel', serif;
                color:var(--gold);
                font-size:3rem;
            ">
                👑 My Luxury Bookings
            </h1>

            <p style="
                color:var(--gray);
                margin-top:10px;
                line-height:1.8;
            ">
                Track your property requests inside the Empire.
            </p>

        </div>

        <!-- SUCCESS MESSAGE -->
        <?php if (isset($_GET['success'])): ?>

            <div style="
                background:rgba(0,255,100,0.08);
                border:1px solid rgba(0,255,100,0.25);
                padding:14px;
                border-radius:14px;
                margin-bottom:25px;
                color:#b8ffd2;
            ">
                <?php echo htmlspecialchars($_GET['success']); ?>
            </div>

        <?php endif; ?>

        <!-- BOOKINGS -->
        <div class="tenant-grid" style="
            display:grid;
            grid-template-columns:repeat(auto-fit,minmax(340px,1fr));
            gap:30px;
        ">

            <?php if (count($bookings) > 0): ?>

                <?php foreach ($bookings as $booking): ?>

                    <div class="lux-card tenant-card" style="
                        border-radius:24px;
                        overflow:hidden;
                    ">

                        <!-- IMAGE -->
                        <div class="tenant-image" style="
                            height:220px;
                            overflow:hidden;
                        ">

                            <?php if (!empty($booking['image'])): ?>

                                <img
                                    src="../../assets/uploads/house_images/<?php echo htmlspecialchars($booking['image']); ?>"
                                    style="
                                        width:100%;
                                        height:100%;
                                        object-fit:cover;
                                        display:block;
                                    "
                                >

                            <?php else: ?>

                                <div style="
                                    width:100%;
                                    height:100%;
                                    display:flex;
                                    align-items:center;
                                    justify-content:center;
                                    color:var(--gold);
                                    font-size:2rem;
                                ">
                                    🏛
                                </div>

                            <?php endif; ?>

                        </div>

                        <!-- CONTENT -->
                        <div class="tenant-card-padding" style="
                            padding:25px;
                        ">

                            <h2 style="
                                color:white;
                                margin-bottom:10px;
                                line-height:1.5;
                            ">
                                <?php echo htmlspecialchars($booking['title']); ?>
                            </h2>  
                            
                            <br>

                            <!-- RATING STARS -->

                            <?php
                                $rating = (int)($booking['rating'] ?? 0);
                            ?>

                            <?php if ($rating > 0): ?>

                                <div style="
                                    display:flex;
                                    align-items:center;
                                    gap:8px;
                                    margin-bottom:15px;
                                ">

                                    <div style="
                                        display:flex;
                                        gap:3px;
                                        font-size:1.1rem;
                                    ">

                                        <?php for ($i = 1; $i <= 5; $i++): ?>

                                            <span style="
                                                color: <?php echo ($i <= $rating) ? '#d4af37' : 'rgba(255,255,255,0.15)'; ?>;
                                                text-shadow: <?php echo ($i <= $rating) ? '0 0 8px rgba(212,175,55,0.35)' : 'none'; ?>;
                                            ">
                                                ★
                                            </span>

                                        <?php endfor; ?>

                                    </div>

                                </div>

                            <?php endif; ?>

                            <br>

                            <p style="
                                color:var(--gray);
                                margin-bottom:15px;
                                line-height:1.7;
                            ">
                              <?php echo htmlspecialchars($booking['location']); ?>
                            </p>

                            <p style="
                                color:var(--gold);
                                margin-bottom:15px;
                                font-weight:bold;
                            ">
                                KES
                                <?php echo number_format($booking['price']); ?>
                            </p>

                            <!-- STATUS -->
                            <?php
                                $status = $booking['status'];
                                $color = "gray";

                                if ($status === "pending") {
                                    $color = "orange";
                                }

                                if ($status === "approved") {
                                    $color = "lightgreen";
                                }

                                if ($status === "rejected") {
                                    $color = "red";
                                }
                            ?>

                            <div style="
                                padding:10px 14px;
                                border-radius:12px;
                                background:rgba(255,255,255,0.05);
                                display:inline-block;
                                margin-bottom:20px;
                                color:<?php echo $color; ?>;
                                font-weight:bold;
                            ">
                                Status:
                                <?php echo ucfirst($status); ?>
                            </div>

                            <!-- ACTIONS -->
                            <div class="tenant-actions" style="
                                display:flex;
                                gap:12px;
                                flex-wrap:wrap;
                                margin-bottom:20px;
                            ">

                                <!-- CANCEL -->
                                <?php if ($booking['status'] === 'pending'): ?>

                                    <form
                                        action="../../api/bookings/cancel_booking.php"
                                        method="POST"
                                    >

                                        <input
                                            type="hidden"
                                            name="booking_id"
                                            value="<?php echo $booking['id']; ?>"
                                        >

                                        <button
                                            type="submit"
                                            style="
                                                background:#ff3b3b;
                                                color:white;
                                                border:none;
                                                padding:12px 18px;
                                                border-radius:14px;
                                                cursor:pointer;
                                                font-weight:bold;
                                                width:100%;
                                            "
                                        >
                                            Cancel
                                        </button>

                                    </form>

                                <?php endif; ?>

                                <!-- DELETE -->
                                <form
                                    action="../../api/bookings/delete_booking.php"
                                    method="POST"
                                    onsubmit="return confirm('Delete this booking permanently?');"
                                >

                                    <input
                                        type="hidden"
                                        name="booking_id"
                                        value="<?php echo $booking['id']; ?>"
                                    >

                                    <button
                                        type="submit"
                                        style="
                                            background:rgba(255,80,80,0.12);
                                            color:#ff5252;
                                            border:1px solid rgba(255,0,0,0.25);
                                            padding:12px 18px;
                                            border-radius:14px;
                                            cursor:pointer;
                                            font-weight:bold;
                                            width:100%;
                                        "
                                    >
                                        Delete
                                    </button>

                                </form>

                            </div>

                            <!-- META -->
                            <div class="tenant-meta" style="
                                display:flex;
                                justify-content:space-between;
                                color:var(--gray);
                                font-size:0.95rem;
                                gap:15px;
                            ">

                                <span>
                                    Bedrooms:
                                    <?php echo htmlspecialchars($booking['bedrooms'] ?? 0); ?> Beds
                                </span>

                                <span>
                                    Bathrooms:
                                    <?php echo htmlspecialchars($booking['bathrooms'] ?? 0); ?> Baths
                                </span>

                            </div>

                        </div>

                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <div class="lux-card tenant-card-padding" style="
                    padding:50px;
                    text-align:center;
                    grid-column:1/-1;
                    border-radius:28px;
                ">

                    <h2 style="
                        color:var(--gold);
                        margin-bottom:15px;
                    ">
                        No Bookings Yet
                    </h2>

                    <p style="
                        color:var(--gray);
                        margin-bottom:25px;
                        line-height:1.8;
                    ">
                        Start exploring luxury properties and
                        make your first request.
                    </p>

                    <a
                        href="search_houses.php"
                        class="lux-btn"
                        style="text-decoration:none;"
                    >
                        Explore Houses
                    </a>

                </div>

            <?php endif; ?>

        </div>

        <!-- ========================================= -->
        <!-- TRUCK REQUESTS -->
        <!-- ========================================= -->

        <div style="margin-top:70px;">

            <div style="margin-bottom:35px;">

                <h1 class="tenant-title" style="
                    font-family:'Cinzel', serif;
                    color:var(--gold);
                    font-size:3rem;
                ">
                    My Truck Requests
                </h1>

                <p style="
                    color:var(--gray);
                    line-height:1.8;
                ">
                    Track your logistics and relocation requests.
                </p>

            </div>

            <!-- GRID -->
            <div class="tenant-grid" style="
                display:grid;
                grid-template-columns:repeat(auto-fit,minmax(340px,1fr));
                gap:30px;
            ">

                <?php if (count($truckRequests) > 0): ?>

                    <?php foreach ($truckRequests as $trip): ?>

                        <?php

                        $statusColor = "gray";

                        if ($trip['status'] === 'pending') {
                            $statusColor = "orange";
                        }

                        elseif ($trip['status'] === 'accepted') {
                            $statusColor = "#00c853";
                        }

                        elseif ($trip['status'] === 'in_transit') {
                            $statusColor = "#42a5f5";
                        }

                        elseif ($trip['status'] === 'completed') {
                            $statusColor = "lightgreen";
                        }

                        elseif ($trip['status'] === 'cancelled') {
                            $statusColor = "#ff5252";
                        }

                        ?>

                        <div class="lux-card tenant-card tenant-card-padding" style="
                            padding:30px;
                            border-radius:28px;
                        ">

                            <!-- TOP -->
                            <div class="tenant-flex" style="
                                display:flex;
                                justify-content:space-between;
                                align-items:center;
                                margin-bottom:25px;
                                gap:20px;
                                flex-wrap:wrap;
                            ">

                                <div>

                                    <h2 style="
                                        color:white;
                                        margin-bottom:8px;
                                        line-height:1.5;
                                    ">
                                        Truck Request
                                        #<?php echo $trip['id']; ?>
                                    </h2>

                                    <div style="
                                        color:var(--gray);
                                        font-size:0.95rem;
                                    ">
                                        Requested on

                                        <?php echo date(
                                            "M d, Y",
                                            strtotime($trip['requested_at'])
                                        ); ?>
                                    </div>

                                </div>

                                <div style="
                                    background:rgba(255,255,255,0.05);
                                    padding:12px 16px;
                                    border-radius:14px;
                                    color:<?php echo $statusColor; ?>;
                                    font-weight:bold;
                                ">
                                    <?php echo strtoupper($trip['status']); ?>
                                </div>

                            </div>

                            <!-- PICKUP -->
                            <div style="margin-bottom:18px;">

                                <div style="
                                    color:var(--gray);
                                    margin-bottom:8px;
                                ">
                                    Pickup
                                </div>

                                <div style="
                                    color:white;
                                    line-height:1.7;
                                ">
                                    <?php echo htmlspecialchars(
                                        $trip['pickup_location']
                                    ); ?>
                                </div>

                            </div>

                            <!-- DESTINATION -->
                            <div style="margin-bottom:20px;">

                                <div style="
                                    color:var(--gray);
                                    margin-bottom:8px;
                                ">
                                    Destination
                                </div>

                                <div style="
                                    color:white;
                                    line-height:1.7;
                                ">
                                    
                                    <?php echo htmlspecialchars(
                                        $trip['destination']
                                    ); ?>
                                </div>

                            </div>

                            <!-- PRICE -->
                            <div style="
                                color:var(--gold);
                                font-weight:bold;
                                margin-bottom:25px;
                                font-size:1.2rem;
                            ">
                                KES
                                <?php echo number_format($trip['price']); ?>
                            </div>

                            <!-- DRIVER -->
                            <?php if ($trip['driver_id']): ?>

                                <div style="
                                    background:rgba(255,255,255,0.04);
                                    padding:16px;
                                    border-radius:16px;
                                    margin-bottom:25px;
                                ">

                                    <div style="
                                        color:var(--gray);
                                        margin-bottom:10px;
                                    ">
                                        Assigned Driver
                                    </div>

                                    <div style="
                                        color:white;
                                        margin-bottom:6px;
                                    ">

                                        <?php echo htmlspecialchars(
                                            $trip['driver_name']
                                        ); ?>
                                    </div>

                                    <div style="color:white;">

                                        <?php echo htmlspecialchars(
                                            $trip['driver_phone']
                                        ); ?>
                                    </div>

                                </div>

                            <?php endif; ?>

                            <!-- ACTIONS -->
                            <div class="tenant-actions" style="
                                display:flex;
                                gap:12px;
                                flex-wrap:wrap;
                            ">

                                <!-- TRACK DRIVER -->
                                <?php if (
                                    $trip['status'] === 'accepted' ||
                                    $trip['status'] === 'in_transit'
                                ): ?>

                                    <a
                                        href="track_driver.php?trip_id=<?php echo $trip['id']; ?>"
                                        style="
                                            text-decoration:none;
                                            background:#42a5f5;
                                            color:white;
                                            padding:14px 20px;
                                            border-radius:16px;
                                            font-weight:bold;
                                            text-align:center;
                                        "
                                    >
                                        Track Driver
                                    </a>

                                <?php endif; ?>

                                <!-- EDIT -->
                                <?php if ($trip['status'] === 'pending'): ?>

                                    <a
                                        href="edit_truck_request.php?id=<?php echo $trip['id']; ?>"
                                        style="
                                            text-decoration:none;
                                            background:rgba(255,255,255,0.08);
                                            color:white;
                                            padding:14px 20px;
                                            border-radius:16px;
                                            font-weight:bold;
                                            text-align:center;
                                        "
                                    >
                                        Edit
                                    </a>

                                <?php endif; ?>

                                <!-- CANCEL -->
                                <?php if (
                                    $trip['status'] === 'pending' ||
                                    $trip['status'] === 'accepted'
                                ): ?>

                                    <form
                                        action="../../api/trucks/cancel_trip.php"
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
                                                background:#ff3b3b;
                                                color:white;
                                                border:none;
                                                padding:14px 20px;
                                                border-radius:16px;
                                                cursor:pointer;
                                                font-weight:bold;
                                                width:100%;
                                            "
                                        >
                                            Cancel
                                        </button>

                                    </form>

                                <?php endif; ?>

                                <!-- DELETE -->
                                <form
                                    action="../../api/trucks/delete_trip_tenant.php"
                                    method="POST"
                                    onsubmit="return confirm('Delete this truck request permanently?');"
                                >

                                    <input
                                        type="hidden"
                                        name="trip_id"
                                        value="<?php echo $trip['id']; ?>"
                                    >

                                    <button
                                        type="submit"
                                        style="
                                            background:rgba(255,80,80,0.12);
                                            color:#ff5252;
                                            border:1px solid rgba(255,80,80,0.25);
                                            padding:14px 20px;
                                            border-radius:16px;
                                            cursor:pointer;
                                            font-weight:bold;
                                            width:100%;
                                        "
                                    >
                                        Delete
                                    </button>

                                </form>

                            </div>

                        </div>

                    <?php endforeach; ?>

                <?php else: ?>

                    <div class="lux-card tenant-card-padding" style="
                        padding:50px;
                        text-align:center;
                        border-radius:28px;
                        grid-column:1/-1;
                    ">

                        <h2 style="
                            color:var(--gold);
                            margin-bottom:15px;
                        ">
                            No Truck Requests Yet
                        </h2>

                        <p style="
                            color:var(--gray);
                            margin-bottom:25px;
                            line-height:1.8;
                        ">
                            Your logistics requests will appear here.
                        </p>

                        <a
                            href="request_truck.php"
                            class="lux-btn"
                            style="text-decoration:none;"
                        >
                            Request Truck
                        </a>

                    </div>

                <?php endif; ?>

            </div>

        </div>

    </main>

</div>

<?php require_once '../../includes/footer.php'; ?>