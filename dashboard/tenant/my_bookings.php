<?php

/**
 * LUX EMPIRE — MY BOOKINGS (tenant)
 *
 * Shows this tenant's house booking requests and truck requests in
 * one page, with client-side tabs/search/sort/status filtering
 * (assets/js/bookings-filter.js) and AJAX-driven Cancel/Delete
 * actions for both sections (assets/js/bookings.js).
 *
 * This view intentionally contains NO SQL of its own for the house
 * bookings — that lives in Booking::getBookingsByTenant() and
 * House::getHouseMedia(). The truck requests query is a straight
 * "all of this tenant's truck requests with driver contact info"
 * read with no business logic, kept here as a single prepared
 * statement (mirrors the pattern already used for the same purpose
 * elsewhere in the app) rather than adding a one-off model method
 * for a single read.
 */

require_once '../../includes/init.php';
require_once '../../includes/auth_check.php';
requireRoleAccess('tenant');

require_once '../../classes/Booking.php';
require_once '../../classes/House.php';
require_once '../../classes/ListingState.php';
require_once '../../classes/TruckRequest.php';
require_once '../../config/db.php';
require_once '../../config/csrf.php';

$tenantId = (int) Session::user()['id'];
$csrfToken = Csrf::token();

$bookingModel = new Booking();
$houseModel = new House();

$bookings = $bookingModel->getBookingsByTenant($tenantId);

/*
 * A tenant can have more than one APPROVED booking with the SAME
 * landlord (different properties, one after another) — only the
 * MOST RECENT one's "Message Landlord" button stays available,
 * mirroring ChatGuard::isFinishedLandlordConversation()'s "superseded"
 * rule so the tenant never sees two live-looking chat entry points
 * to the same person. Precomputed once per landlord here rather than
 * per booking inside the render loop below.
 */
$landlordIdsWithApprovedBooking = array_values(array_unique(array_filter(array_map(
    static fn ($b) => ($b['status'] === 'approved') ? (int) $b['landlord_id'] : null,
    $bookings
))));

$latestApprovedBookingIdByLandlord = [];
foreach ($landlordIdsWithApprovedBooking as $landlordIdForLookup) {
    $latestApprovedBookingIdByLandlord[$landlordIdForLookup] = $bookingModel->getLatestApprovedBookingId($tenantId, $landlordIdForLookup);
}

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
        users.phone AS driver_phone,
        CASE
            WHEN truck_requests.status IN ('pending', 'accepted', 'arrived_at_pickup', 'in_transit') THEN 0
            ELSE 1
        END AS priority_tier

    FROM truck_requests

    LEFT JOIN users
    ON truck_requests.driver_id = users.id

    WHERE truck_requests.tenant_id = ?
    AND (
        truck_requests.status NOT IN ('completed', 'cancelled')
        OR truck_requests.updated_at > (NOW() - INTERVAL " . (int) AUTO_CLEAR_FINISHED_TRIP_HOURS . " HOUR)
    )

    ORDER BY priority_tier ASC, truck_requests.requested_at DESC
");

$truckStmt->execute([
    $tenantId
]);

$truckRequests = $truckStmt->fetchAll();

/*
 * Vehicle plate, vehicle type and this driver's completed-trip count are
 * only ever shown to the tenant while a trip is actively assigned
 * (accepted / arrived_at_pickup / in_transit) — never before acceptance,
 * never after completion/cancellation. Fetched per-trip via the model
 * so decryption and the status gate live in one place, not duplicated
 * across pages.
 */
$truckModel = new TruckRequest();
$activeDriverInfoMap = [];

foreach ($truckRequests as $tripRow) {
    $info = $truckModel->getActiveTripDriverInfo((int) $tripRow['id'], $tenantId);
    if ($info !== null) {
        $activeDriverInfoMap[(int) $tripRow['id']] = $info;
    }
}

require_once '../../classes/VerificationLookup.php';

$verificationLookup = new VerificationLookup();

/**
 * House-booking cards show the LISTING's own verified badge (the
 * house itself, not the landlord — consistent with search_houses.php
 * / view_house.php, which badge house.verified_at separately from
 * the landlord's). Booking::getBookingsByTenant() already returns
 * h.* via its SELECT, so no extra query is needed for that — it's
 * available directly as $booking['verified_at'].
 *
 * Driver verification (truck requests) DOES need a lookup, since
 * driver_id isn't a listing and has no verified_at on the row
 * itself — it lives on users.verified_at for that driver.
 */
$driverIdsOnPage = array_filter(array_map(
    static fn ($t) => (int) ($t['driver_id'] ?? 0),
    $truckRequests
));

$verifiedDriverMap = $verificationLookup->getVerifiedMap($driverIdsOnPage);

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
require_once '../../includes/sidebar.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/property-media.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/bookings.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/my-bookings.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/verification-badges.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/listing-state.css">

<div class="mb-page">

    <!-- MAIN -->
    <main class="tenant-main mb-main">

        <!-- HEADER -->
        <div class="mb-header">

            <h1 class="tenant-title mb-title">
                My Bookings
            </h1>

            <p class="mb-subtitle">
                Track your property requests inside the Empire.
            </p>

        </div>

        <!-- BOOKING CONTROLS: tabs, search, sort, status filter (behavior: bookings-filter.js) -->
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

        <!-- SUCCESS MESSAGE (from older redirect-based flows, e.g. after booking a house) -->
        <?php if (isset($_GET['success'])): ?>

            <div class="mb-success-alert">
                <?php echo htmlspecialchars($_GET['success']); ?>
            </div>

        <?php endif; ?>

        <!-- ========================================= -->
        <!-- HOUSE BOOKINGS -->
        <!-- ========================================= -->

        <div id="houseBookingsSection">

            <div class="tenant-grid mb-grid">

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

                            $status = $booking['status'];
                        ?>

                        <!--
                            data-type / data-status / data-timestamp / data-search below are
                            READ BY assets/js/bookings-filter.js — do not rename/remove them
                            without updating that file too.
                        -->
                        <div class="lux-card tenant-card mb-house-card booking-card"
                             data-type="house"
                             data-status="<?php echo htmlspecialchars($status); ?>"
                             data-timestamp="<?php echo (int) strtotime($booking['booking_date']); ?>"
                             data-search="<?php echo htmlspecialchars(strtolower($booking['title'] . ' ' . $booking['location'])); ?>">

                            <!-- MEDIA -->
                            <div class="tenant-image mb-house-media">

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

                                    <div class="mb-no-image">
                                        No Image
                                    </div>

                                <?php endif; ?>

                            </div>

                            <!-- CONTENT -->
                            <div class="tenant-card-padding mb-house-content">

                                <h2 class="mb-house-title">
                                    <?php echo htmlspecialchars($booking['title']); ?>
                                    <?php if (!empty($booking['verified_at'])): ?>
                                        <span class="lux-verified-badge" title="Verified"><i class="fa-solid fa-circle-check"></i></span>
                                    <?php endif; ?>
                                </h2>

                                <br>

                                <!-- RATING STARS (reuses .lb-star / .is-filled from bookings.css) -->

                                <?php $rating = (int)($booking['rating'] ?? 0); ?>

                                <?php if ($rating > 0): ?>

                                    <div class="mb-rating-wrap">

                                        <div class="lb-stars">

                                            <?php for ($i = 1; $i <= 5; $i++): ?>

                                                <span class="lb-star<?php echo ($i <= $rating) ? ' is-filled' : ''; ?>">★</span>

                                            <?php endfor; ?>

                                        </div>

                                    </div>

                                <?php endif; ?>

                                <br>

                                <p class="mb-location">
                                    <?php echo htmlspecialchars($booking['location']); ?>
                                </p>

                                <p class="mb-price">
                                    KES <?php echo number_format($booking['price']); ?>
                                </p>

                                <!-- STATUS (reuses .status-pending/.status-approved/.status-rejected from bookings.css) -->
                                <div class="mb-status-badge status-<?php echo htmlspecialchars($status); ?>">
                                    Status:
                                    <?php echo ucfirst($status); ?>
                                </div>

                                <?php
                                $isPaid = ($booking['payment_status'] ?? 'unpaid') === 'paid';
                                $isLive = $isPaid && in_array($status, ['pending', 'approved'], true);
                                $showContact = $isLive && in_array($status, ListingState::contactRevealStatuses(), true);

                                // Only the tenant's MOST RECENT approved booking with this
                                // landlord keeps a working "Message Landlord" entry point —
                                // an older, superseded one no longer has a live conversation
                                // behind it (see ChatGuard's superseded-booking rule).
                                $canMessageLandlord = ($status !== 'approved')
                                    || (($latestApprovedBookingIdByLandlord[(int) $booking['landlord_id']] ?? null) === (int) $booking['id']);
                                    $refundStatus = $booking['refund_status'] ?? null;
                                    $usedVoucher = !empty($booking['waiver_id']);
                                    $waiverStatus = $booking['waiver_status'] ?? null;

                                    $refundLabel = match (true) {
                                        $refundStatus === 'completed' => 'Your booking fee has been refunded to your M-Pesa.',
                                        $refundStatus === 'failed' => 'Your refund is delayed — our team is completing it manually.',
                                        default => 'Your booking fee is being refunded to your M-Pesa.',
                                    };
                                ?>

                                <?php if ($showContact): ?>

                                    <div class="lux-landlord-contact">
                                        <span class="lux-landlord-contact-label">Your landlord</span>
                                        <?php echo htmlspecialchars($booking['landlord_name'] ?? ''); ?>
                                        <?php if (!empty($booking['landlord_phone'])): ?>
                                            <br><i class="fa-solid fa-phone"></i>
                                            <a href="tel:<?php echo htmlspecialchars($booking['landlord_phone']); ?>"><?php echo htmlspecialchars($booking['landlord_phone']); ?></a>
                                        <?php endif; ?>
                                        <?php if (!empty($booking['landlord_email'])): ?>
                                            <br><i class="fa-solid fa-envelope"></i>
                                            <a href="mailto:<?php echo htmlspecialchars($booking['landlord_email']); ?>"><?php echo htmlspecialchars($booking['landlord_email']); ?></a>
                                        <?php endif; ?>
                                    </div>

                                <?php endif; ?>

                                <?php if ($isPaid && in_array($status, ['rejected', 'cancelled'], true)): ?>

                                    <div class="lux-landlord-contact">
                                        <span class="lux-landlord-contact-label"><?php echo $usedVoucher ? 'Voucher' : 'Refund'; ?></span>
                                        <?php
                                            if ($usedVoucher) {
                                                echo htmlspecialchars($waiverStatus === 'active'
                                                    ? 'This booking used your free voucher. It did not go ahead, so your voucher is back — use it on another property.'
                                                    : 'This booking used your free voucher. It did not go ahead, and the voucher has now been used up.');
                                            } else {
                                                echo htmlspecialchars($refundLabel);
                                            }
                                        ?>
                                    </div>

                                <?php endif; ?>

                                <!-- ACTIONS -->
                                <div class="tenant-actions mb-actions">

                                    <?php if ($isLive && $canMessageLandlord): ?>

                                        <button type="button"
                                                class="lux-btn chat-starter-btn mb-message-driver-btn"
                                                data-other-user-id="<?php echo (int) $booking['landlord_id']; ?>"
                                                data-other-role="landlord"
                                                <?php if (!empty($booking['house_id'])): ?>data-house-id="<?php echo (int) $booking['house_id']; ?>"<?php endif; ?>
                                                data-other-name="<?php echo htmlspecialchars($booking['landlord_name'] ?? 'Landlord'); ?>">
                                            <i class="fa-solid fa-comment-dots"></i> Message Landlord
                                        </button>

                                    <?php endif; ?>

                                    <?php if ($status === 'pending'): ?>

                                        <!-- A LIVE request can only be CANCELLED (frees the house, refunds the fee). -->
                                        <form class="booking-ajax-form"
                                              action="<?php echo BASE_URL; ?>/api/bookings/cancel_booking.php"
                                              method="POST">

                                            <input type="hidden" name="booking_id" value="<?php echo (int) $booking['id']; ?>">

                                            <button type="submit" class="mb-btn-cancel">
                                                Cancel
                                            </button>

                                        </form>

                                    <?php else: ?>

                                        <!-- A FINISHED booking (approved / rejected / cancelled) can only be REMOVED from this list. -->
                                        <form class="booking-ajax-form"
                                              action="<?php echo BASE_URL; ?>/api/bookings/delete_booking.php"
                                              method="POST">

                                            <input type="hidden" name="booking_id" value="<?php echo (int) $booking['id']; ?>">

                                            <button type="submit" class="mb-btn-delete">
                                                Delete
                                            </button>

                                        </form>

                                    <?php endif; ?>

                                </div>

                                <!-- META -->
                                <div class="tenant-meta mb-meta-row">

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

                    <div class="lux-card tenant-card-padding mb-empty-card">

                        <h2 class="mb-empty-title">
                            No Bookings Yet
                        </h2>

                        <p class="mb-empty-text">
                            Start exploring luxury properties and
                            make your first request.
                        </p>

                        <a href="<?php echo BASE_URL; ?>/tenant/search-houses"
                           class="lux-btn mb-empty-link">
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

        <div id="truckRequestsSection" class="mb-truck-section">

            <div class="mb-header">

                <h1 class="tenant-title mb-title">
                    My Truck Requests
                </h1>

                <p class="mb-subtitle">
                    Track your logistics and relocation requests.
                </p>

            </div>

            <div class="tenant-grid mb-grid">

                <?php if (count($truckRequests) > 0): ?>

                    <?php foreach ($truckRequests as $trip): ?>

                        <?php $truckStatus = $trip['status']; ?>

                        <div class="lux-card tenant-card tenant-card-padding mb-truck-card booking-card"
                             data-type="truck"
                             data-status="<?php echo htmlspecialchars($truckStatus); ?>"
                             data-timestamp="<?php echo (int) strtotime($trip['requested_at']); ?>"
                             data-search="<?php echo htmlspecialchars(strtolower($trip['pickup_location'] . ' ' . $trip['destination'])); ?>">

                            <?php if (!empty($trip['trip_type']) && $trip['trip_type'] === 'scheduled'): ?>
                                <div style="margin-bottom:12px;">
                                    <span class="trip-type-tag scheduled" style="display:inline-block; padding:5px 10px; border-radius:8px; font-size:0.75rem; font-weight:bold; background:rgba(100,180,255,0.12); color:#7fc4ff;">
                                        <i class="fa-solid fa-calendar-days"></i> Scheduled
                                    </span>
                                </div>
                            <?php else: ?>
                                <div style="margin-bottom:12px;">
                                    <span class="trip-type-tag instant" style="display:inline-block; padding:5px 10px; border-radius:8px; font-size:0.75rem; font-weight:bold; background:rgba(0,255,120,0.12); color:lightgreen;">
                                        <i class="fa-solid fa-bolt"></i> Move Now
                                    </span>
                                </div>
                            <?php endif; ?>

                            <!-- TOP -->
                            <div class="tenant-flex mb-truck-top">

                                <div>

                                    <h2 class="mb-truck-title">
                                        Truck Request
                                        #<?php echo (int) $trip['id']; ?>
                                    </h2>

                                    <div class="mb-truck-meta-text">
                                        Requested on
                                        <?php echo date("M d, Y", strtotime($trip['requested_at'])); ?>
                                    </div>

                                </div>

                                <!-- Truck status color comes from .truck-status-* in my-bookings.css -->
                                <div class="mb-truck-status-badge truck-status-<?php echo htmlspecialchars($truckStatus); ?>">
                                    <?php echo strtoupper($truckStatus); ?>
                                </div>

                            </div>

                            <!-- PICKUP -->
                            <div class="mb-truck-field-tight">

                                <div class="mb-truck-field-label">
                                    Pickup
                                </div>

                                <div class="mb-truck-field-value">
                                    <?php echo htmlspecialchars($trip['pickup_location']); ?>
                                </div>

                            </div>

                            <!-- DESTINATION -->
                            <div class="mb-truck-field">

                                <div class="mb-truck-field-label">
                                    Destination
                                </div>

                                <div class="mb-truck-field-value">
                                    <?php echo htmlspecialchars($trip['destination']); ?>
                                </div>

                            </div>

                            <!-- PRICE -->
                            <div class="mb-truck-price">
                                KES <?php echo number_format($trip['price']); ?>
                            </div>

                            <!-- DRIVER — shown ONLY while the trip is actively assigned
                                 (accepted / arrived_at_pickup / in_transit). Nothing here
                                 before acceptance, nothing here after completion/cancellation. -->
                            <?php $driverInfo = $activeDriverInfoMap[(int) $trip['id']] ?? null; ?>
                            <?php if ($driverInfo !== null): ?>

                                <div class="mb-driver-box">

                                    <div class="mb-driver-label">
                                        Assigned Driver
                                    </div>

                                    <div class="mb-driver-name">
                                        <?php echo htmlspecialchars($driverInfo['driver_name']); ?>
                                        <?php if ($verifiedDriverMap[(int) $trip['driver_id']] ?? false): ?>
                                            <span class="lux-verified-badge" title="Verified Driver"><i class="fa-solid fa-circle-check"></i></span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="mb-driver-phone">
                                        <?php echo htmlspecialchars($driverInfo['driver_phone']); ?>
                                    </div>

                                    <div class="mb-driver-vehicle" style="margin-top:10px; padding-top:10px; border-top:1px solid rgba(255,255,255,0.08); color:var(--gray); font-size:0.9rem; line-height:1.7;">
                                        <?php if ($driverInfo['vehicle_plate']): ?>
                                            <div><i class="fa-solid fa-trailer" style="color:var(--gold); margin-right:6px;"></i><?php echo htmlspecialchars($driverInfo['vehicle_plate']); ?></div>
                                        <?php endif; ?>
                                        <?php if ($driverInfo['vehicle_type']): ?>
                                            <div><i class="fa-solid fa-truck" style="color:var(--gold); margin-right:6px;"></i><?php echo htmlspecialchars($driverInfo['vehicle_type']); ?></div>
                                        <?php endif; ?>
                                        <div><i class="fa-solid fa-route" style="color:var(--gold); margin-right:6px;"></i><?php echo (int) $driverInfo['completed_trips']; ?> completed trip<?php echo $driverInfo['completed_trips'] === 1 ? '' : 's'; ?> on LUX EMPIRE</div>
                                    </div>

                                </div>

                            <?php endif; ?>

                            <!-- ACTIONS -->
                            <div class="tenant-actions mb-actions">

                                <!-- MESSAGE DRIVER — moved out of the identity box, sits here
                                     alongside the other real actions where it reads as one. -->
                                <?php if ($driverInfo !== null && in_array($truckStatus, ['accepted', 'arrived_at_pickup', 'in_transit'], true)): ?>

                                    <button type="button"
                                            class="lux-btn chat-starter-btn mb-message-driver-btn"
                                            data-other-user-id="<?php echo (int) $trip['driver_id']; ?>"
                                            data-other-role="driver"
                                            data-truck-request-id="<?php echo (int) $trip['id']; ?>"
                                            data-other-name="<?php echo htmlspecialchars($driverInfo['driver_name']); ?>">
                                        <i class="fa-solid fa-comment-dots"></i> Message
                                    </button>

                                <?php endif; ?>

                                <!-- TRACK DRIVER (real navigation — unrelated to this AJAX refactor) -->
                                <?php if ($truckStatus === 'accepted' || $truckStatus === 'in_transit'): ?>

                                    <a href="<?php echo BASE_URL; ?>/tenant/track-driver?trip_id=<?php echo (int) $trip['id']; ?>"
                                       class="mb-btn-track">
                                        Track Driver
                                    </a>

                                <?php endif; ?>

                                <!-- EDIT — opens the reusable modal, no navigation.
                                     Only ever shown while status is pending, per spec:
                                     an accepted trip can no longer be edited at all. -->
                                <?php if ($truckStatus === 'pending'): ?>

                                    <button type="button"
                                            class="mb-btn-edit edit-truck-trigger-btn"
                                            data-request-id="<?php echo (int) $trip['id']; ?>"
                                            data-trip-type="<?php echo htmlspecialchars($trip['trip_type'] ?? 'instant'); ?>"
                                            data-scheduled-at="<?php echo htmlspecialchars($trip['scheduled_at'] ?? ''); ?>"
                                            data-items="<?php echo htmlspecialchars($trip['items_description'] ?? ''); ?>">
                                        Edit
                                    </button>

                                <?php endif; ?>

                                <!--
                                    CANCEL TRIP — class "truck-ajax-form" is what
                                    bindTenantTruckAjaxForms() in bookings.js listens for.
                                    Pending only, per spec: an accepted trip can no
                                    longer be cancelled by the tenant.
                                -->
                                <?php if ($truckStatus === 'pending'): ?>

                                    <form class="truck-ajax-form"
                                          action="<?php echo BASE_URL; ?>/api/trucks/cancel_trip.php"
                                          method="POST">

                                        <input type="hidden" name="trip_id" value="<?php echo (int) $trip['id']; ?>">

                                        <button type="submit" class="mb-btn-cancel-trip">
                                            Cancel
                                        </button>

                                    </form>

                                <?php endif; ?>

                                <!-- DELETE TRIP -->
                                <form class="truck-ajax-form"
                                      action="<?php echo BASE_URL; ?>/api/trucks/delete_trip_tenant.php"
                                      method="POST"
                                      data-confirm="Delete this truck request permanently?">

                                    <input type="hidden" name="trip_id" value="<?php echo (int) $trip['id']; ?>">

                                    <button type="submit" class="mb-btn-delete-trip">
                                        Delete
                                    </button>

                                </form>

                            </div>

                        </div>

                    <?php endforeach; ?>

                <?php else: ?>

                    <div class="lux-card tenant-card-padding mb-empty-card">

                        <h2 class="mb-empty-title">
                            No Truck Requests Yet
                        </h2>

                        <p class="mb-empty-text">
                            Your logistics requests will appear here.
                        </p>

                        <a href="<?php echo BASE_URL; ?>/tenant/request-truck"
                           class="lux-btn mb-empty-link">
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

<script>
    /*
     * Consumed by assets/js/bookings.js — same config object every
     * booking-related page uses, so one shared handler file covers
     * tenant booking/truck actions and landlord accept/reject alike.
     */
    window.LUX_BOOKING_CONFIG = {
        baseUrl: "<?php echo BASE_URL; ?>",
        csrfToken: "<?php echo htmlspecialchars($csrfToken); ?>"
    };
</script>

<script src="<?php echo BASE_URL; ?>/assets/js/property-media.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/reusable-modal.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/truck-edit-modal.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/bookings.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/bookings-filter.js"></script>

<?php require_once '../../includes/chat_starter_modal.php'; ?>

<?php require_once '../../includes/footer.php'; ?>