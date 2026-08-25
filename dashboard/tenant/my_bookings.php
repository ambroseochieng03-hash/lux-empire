<?php

require_once '../../includes/init.php';
require_once '../../includes/auth_check.php';
requireRoleAccess('tenant');

require_once '../../classes/Booking.php';
require_once '../../classes/House.php';
require_once '../../config/db.php';

$tenantId = (int) Session::user()['id'];

$bookingModel = new Booking();
$houseModel = new House();

$bookings = $bookingModel->getBookingsByTenant($tenantId);

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
    $tenantId
]);

$truckRequests = $truckStmt->fetchAll();

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
require_once '../../includes/sidebar.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/property-media.css">

<style>

.booking-controls-bar{
    display:flex;
    flex-wrap:wrap;
    align-items:center;
    gap:16px;
    margin-bottom:35px;
}

.booking-tabs{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
}

.booking-tab-btn{
    background:rgba(255,255,255,0.06);
    border:1px solid rgba(255,255,255,0.1);
    color:var(--gray);
    padding:12px 20px;
    border-radius:14px;
    font-weight:bold;
    cursor:pointer;
    transition:0.25s;
}

.booking-tab-btn:hover{
    border-color:rgba(212,175,55,0.4);
    color:white;
}

.booking-tab-btn.is-active{
    background:linear-gradient(135deg,#d4af37,#f5d76e);
    color:black;
    border-color:transparent;
}

.booking-filters{
    display:flex;
    gap:12px;
    flex-wrap:wrap;
    margin-left:auto;
}

.booking-search-input{
    padding:12px 16px;
    border-radius:14px;
    border:1px solid rgba(255,255,255,0.1);
    background:rgba(255,255,255,0.05);
    color:white;
    min-width:220px;
}

.booking-search-input::placeholder{
    color:var(--gray);
}

.booking-sort-select,
.booking-status-select{
    padding:12px 16px;
    border-radius:14px;
    border:1px solid rgba(255,255,255,0.1);
    background:rgba(255,255,255,0.05);
    color:white;
    cursor:pointer;
}

.booking-sort-select[hidden],
.booking-status-select[hidden]{
    display:none;
}

@media (max-width:768px){

    .booking-filters{
        margin-left:0;
        width:100%;
    }

    .booking-search-input{
        flex:1;
        min-width:0;
    }

}

</style>

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
                <i class="fa-solid fa-crown"></i> My Luxury Bookings
            </h1>

            <p style="
                color:var(--gray);
                margin-top:10px;
                line-height:1.8;
            ">
                Track your property requests inside the Empire.
            </p>

        </div>

        <!-- BOOKING CONTROLS: tabs, search, sort, status filter -->
        <div class="booking-controls-bar">

            <div class="booking-tabs">
                <button type="button" class="booking-tab-btn is-active" data-tab="all">All</button>
                <button type="button" class="booking-tab-btn" data-tab="house">House Bookings</button>
                <button type="button" class="booking-tab-btn" data-tab="truck">Truck Requests</button>
            </div>

            <div class="booking-filters">

                <input type="text"
                       id="bookingSearchInput"
                       class="booking-search-input"
                       placeholder="Search bookings...">

                <select id="bookingSortSelect" class="booking-sort-select" hidden>
                    <option value="newest">Newest First</option>
                    <option value="oldest">Oldest First</option>
                </select>

                <select id="bookingStatusSelect" class="booking-status-select" hidden>
                    <option value="">All Statuses</option>
                </select>

            </div>

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
        <div id="houseBookingsSection">

        <div class="tenant-grid" style="
            display:grid;
            grid-template-columns:repeat(auto-fit,minmax(340px,1fr));
            gap:30px;
        ">

            <?php if (count($bookings) > 0): ?>

                <?php foreach ($bookings as $booking): ?>

                    <?php
                        /*
                         * Bookings don't carry media directly — Booking::getBookingsByTenant()
                         * only returns one image_path via a LIMIT 1 subquery. But the row
                         * does include house_id (from bookings.house_id), so we pull ALL
                         * media for that house the same way the listing pages do, via the
                         * existing House::getHouseMedia() method.
                         */
                        $mediaItems = $houseModel->getHouseMedia((int) $booking['house_id']);

                        $imageUrls = [];
                        $videoUrl  = null;

                        foreach ($mediaItems as $mediaItem) {

                            $path = BASE_URL . '/assets/uploads/house_images/' . $mediaItem['image_path'];

                            if (preg_match('/\.mp4$/i', $mediaItem['image_path'])) {
                                $videoUrl = $path;
                            } else {
                                $imageUrls[] = $path;
                            }
                        }
                    ?>

                    <div class="lux-card tenant-card booking-card"
                         data-type="house"
                         data-status="<?php echo htmlspecialchars($booking['status']); ?>"
                         data-timestamp="<?php echo (int) strtotime($booking['booking_date']); ?>"
                         data-search="<?php echo htmlspecialchars(strtolower($booking['title'] . ' ' . $booking['location'])); ?>"
                         style="
                        border-radius:24px;
                        overflow:hidden;
                    ">

                        <!-- MEDIA -->
                        <div class="tenant-image" style="
                            height:220px;
                            overflow:hidden;
                            position:relative;
                        ">

                            <?php if ($videoUrl !== null): ?>

                                <div class="media-frame"
                                     data-video="<?php echo htmlspecialchars($videoUrl); ?>"
                                     data-caption="<?php echo htmlspecialchars($booking['title']); ?>">

                                    <video class="media-video"
                                           src="<?php echo htmlspecialchars($videoUrl); ?>"
                                           controls
                                           preload="metadata"
                                           playsinline>
                                    </video>

                                    <button type="button" class="media-enlarge-btn" aria-label="Enlarge video">⤢</button>

                                </div>

                            <?php elseif (!empty($imageUrls)): ?>

                                <?php $mediaImagesJson = json_encode($imageUrls); ?>

                                <div class="media-frame"
                                     data-images='<?php echo htmlspecialchars($mediaImagesJson, ENT_QUOTES); ?>'
                                     data-caption="<?php echo htmlspecialchars($booking['title']); ?>"
                                     data-current-index="0">

                                    <div class="media-carousel">

                                        <div class="media-carousel-track">

                                            <?php foreach ($imageUrls as $index => $url): ?>

                                                <img class="media-slide<?php echo $index === 0 ? ' is-active' : ''; ?>"
                                                     src="<?php echo htmlspecialchars($url); ?>"
                                                     data-index="<?php echo $index; ?>"
                                                     alt="<?php echo htmlspecialchars($booking['title']); ?> image <?php echo $index + 1; ?>">

                                            <?php endforeach; ?>

                                        </div>

                                    </div>

                                    <?php if (count($imageUrls) > 1): ?>

                                        <button type="button" class="media-carousel-btn media-carousel-prev" aria-label="Previous image">‹</button>
                                        <button type="button" class="media-carousel-btn media-carousel-next" aria-label="Next image">›</button>

                                        <div class="media-carousel-dots">
                                            <?php foreach ($imageUrls as $index => $url): ?>
                                                <span class="media-dot<?php echo $index === 0 ? ' is-active' : ''; ?>" data-index="<?php echo $index; ?>"></span>
                                            <?php endforeach; ?>
                                        </div>

                                    <?php endif; ?>

                                    <button type="button" class="media-enlarge-btn" aria-label="Enlarge image">⤢</button>

                                </div>

                            <?php else: ?>

                                <div style="
                                    width:100%;
                                    height:100%;
                                    display:flex;
                                    align-items:center;
                                    justify-content:center;
                                    color:var(--gold);
                                    font-size:1rem;
                                    letter-spacing:0.5px;
                                    text-transform:uppercase;
                                    opacity:0.6;
                                ">
                                    No Image
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

                                    <form class="booking-action-form"
                                        action="<?php echo BASE_URL; ?>/api/bookings/cancel_booking.php"
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
                                <form class="booking-action-form"
                                    action="<?php echo BASE_URL; ?>/api/bookings/delete_booking.php"
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
                        href="<?php echo BASE_URL; ?>/dashboard/tenant/search_houses.php"
                        class="lux-btn"
                        style="text-decoration:none;"
                    >
                        Explore Houses
                    </a>

                </div>

            <?php endif; ?>

        </div>

        </div>
        <!-- /houseBookingsSection -->

        <!-- ========================================= -->
        <!-- TRUCK REQUESTS -->
        <!-- ========================================= -->

        <div id="truckRequestsSection" style="margin-top:70px;">

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

                        <div class="lux-card tenant-card tenant-card-padding booking-card"
                             data-type="truck"
                             data-status="<?php echo htmlspecialchars($trip['status']); ?>"
                             data-timestamp="<?php echo (int) strtotime($trip['requested_at']); ?>"
                             data-search="<?php echo htmlspecialchars(strtolower($trip['pickup_location'] . ' ' . $trip['destination'])); ?>"
                             style="
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

                                    <!-- MESSAGE DRIVER (only once a driver is actually assigned) -->
                                    <?php if ($trip['driver_id'] && in_array($trip['status'], ['accepted', 'in_transit'], true)): ?>

                                        <button type="button"
                                                class="lux-btn chat-starter-btn"
                                                data-other-user-id="<?php echo (int) $trip['driver_id']; ?>"
                                                data-other-role="driver"
                                                data-truck-request-id="<?php echo (int) $trip['id']; ?>"
                                                data-other-name="<?php echo htmlspecialchars($trip['driver_name']); ?>"
                                                style="
                                                    text-decoration:none;
                                                    background:rgba(255,255,255,0.08);
                                                    color:var(--gold);
                                                    border:1px solid var(--gold);
                                                    padding:14px 20px;
                                                    border-radius:16px;
                                                    font-weight:bold;
                                                    text-align:center;
                                                    cursor:pointer;
                                                ">
                                            <i class="fa-solid fa-comment-dots"></i> Message Driver
                                        </button>

                                    <?php endif; ?>

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
                                        href="<?php echo BASE_URL; ?>/dashboard/tenant/track_driver.php?trip_id=<?php echo $trip['id']; ?>"
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
                                        href="<?php echo BASE_URL; ?>/dashboard/tenant/edit_truck_request.php?id=<?php echo $trip['id']; ?>"
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

                                    <form class="booking-action-form"
                                        action="<?php echo BASE_URL; ?>/api/trucks/cancel_trip.php"
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
                                <form class="booking-action-form"
                                    action="<?php echo BASE_URL; ?>/api/trucks/delete_trip_tenant.php"
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
                            href="<?php echo BASE_URL; ?>/dashboard/tenant/request_truck.php"
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

<!-- MEDIA LIGHTBOX (shared, single instance) -->
<div class="media-lightbox" id="mediaLightbox" aria-hidden="true">
    <div class="media-lightbox-overlay" data-media-close></div>
    <div class="media-lightbox-content">
        <button type="button" class="media-lightbox-close" data-media-close aria-label="Close">×</button>
        <button type="button" class="media-lightbox-nav media-lightbox-prev" aria-label="Previous image">‹</button>
        <div class="media-lightbox-stage">
            <img class="media-lightbox-image" src="" alt="">
        </div>
        <button type="button" class="media-lightbox-nav media-lightbox-next" aria-label="Next image">›</button>
        <div class="media-lightbox-counter"></div>
    </div>
</div>

<script src="<?php echo BASE_URL; ?>/assets/js/property-media.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/bookings-filter.js"></script>

<?php require_once '../../includes/chat_starter_modal.php'; ?>

<?php require_once '../../includes/footer.php'; ?>