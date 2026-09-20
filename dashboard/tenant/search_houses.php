<?php

require_once '../../includes/init.php';
require_once '../../includes/auth_check.php';
requireRoleAccess('tenant');

require_once '../../classes/House.php';
require_once '../../classes/ListingState.php';
require_once '../../classes/Booking.php';
require_once '../../config/csrf.php';

$houseModel = new House();
$bookingModel = new Booking();

$tenantId = (int) Session::user()['id'];
$csrfToken = Csrf::token();

/**
 * Search handling
 */
$search = trim($_GET['search'] ?? '');

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 24;
$offset = ($page - 1) * $perPage;

$filterResult = $houseModel->filterHouses(
    ['keyword' => $search, 'sort' => 'newest'],
    $perPage,
    $offset
);

$houses = $filterResult['houses'];
$totalHouses = $filterResult['total'];
$totalPages = (int) ceil($totalHouses / $perPage);
// is_hidden is already excluded inside filterHouses()'s runFilterQuery() WHERE clause —
// no need to filter again here.

require_once '../../classes/VerificationLookup.php';

$landlordIdsOnPage = array_map(static fn ($h) => (int) $h['landlord_id'], $houses);
$verifiedLandlordMap = (new VerificationLookup())->getVerifiedMap($landlordIdsOnPage);

/**
 * Build a house_id => status map of this tenant's own active
 * booking for each house, so the button/card can reflect "Request
 * Pending" / already-acted-on state without another round trip.
 * getBookingsByTenant() orders by booking_date DESC, so the first
 * row seen per house_id is this tenant's most recent booking for it.
 */
$tenantBookingStatusByHouse = [];

foreach ($bookingModel->getBookingsByTenant($tenantId) as $tenantBooking) {

    $houseIdKey = (int) $tenantBooking['house_id'];

    if (!isset($tenantBookingStatusByHouse[$houseIdKey])) {
        $tenantBookingStatusByHouse[$houseIdKey] = $tenantBooking['status'];
    }
}

$pageHouseIds = array_map(static fn ($h) => (int) $h['id'], $houses);

// Paid + live booking (pending or approved): may use in-app chat with that landlord.
$paidHouseMap = $bookingModel->getPaidHouseIdsForTenant($tenantId, $pageHouseIds);

// May see the landlord's phone/email (see ListingState::contactRevealStatuses()).
$contactHouseMap = $bookingModel->getContactHouseIdsForTenant($tenantId, $pageHouseIds);

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
require_once '../../includes/sidebar.php';
?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/property-media.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/bookings.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/house-filters.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/verification-badges.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/listing-state.css">

<style>
.lux-explore-parking-row{
    margin-top:6px;
}

.lux-parking-badge{
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:4px 12px;
    border-radius:999px;
    background:linear-gradient(135deg, rgba(212,175,55,0.18), rgba(212,175,55,0.08));
    border:1px solid rgba(212,175,55,0.4);
    color:var(--gold);
    font-size:0.72rem;
    font-weight:600;
    letter-spacing:0.2px;
    white-space:nowrap;
    animation: luxParkingPulse 2.6s ease-in-out infinite;
}

.lux-parking-badge i{
    font-size:0.7rem;
}

@keyframes luxParkingPulse{
    0%, 100% { box-shadow: 0 0 0 0 rgba(212,175,55,0.25); }
    50%      { box-shadow: 0 0 0 5px rgba(212,175,55,0); }
}
</style>

<div class="lux-explore-page">

    <!-- MAIN -->
    <main class="tenant-main lux-explore-main">

        <!-- HERO -->
        <div class="lux-explore-hero">

            <h1 class="tenant-title lux-explore-title">
                Discover Luxury Living
            </h1>

            <p class="lux-explore-subtitle">
                Explore elite homes, premium apartments, and prestigious spaces across the Empire.
            </p>

        </div>

        <!-- SEARCH BAR -->
        <div class="lux-card tenant-card tenant-card-padding lux-explore-search-card">

            <form method="GET"
                action=""
                class="tenant-search-form lux-explore-search-form">

                <input type="text"
                    id="houseKeywordInput"
                    name="search"
                    value="<?php echo htmlspecialchars($search); ?>"
                    placeholder="Search by title, location, or luxury features..."
                    class="tenant-search-input lux-explore-search-input">

                <button type="submit"
                        class="lux-btn tenant-search-btn lux-explore-search-btn">
                    Search Empire
                </button>

                <button type="button"
                        class="lux-btn hf-trigger-btn"
                        data-open-house-filters>
                    <i class="fa-solid fa-sliders"></i> Filters
                </button>

            </form>

        </div>

        <?php require_once '../../includes/house_filter_modal.php'; ?>

        <!-- HOUSES GRID -->
        <div class="tenant-grid lux-explore-grid" id="housesResultsGrid">

            <?php if (count($houses) > 0): ?>

                <?php
                $mediaByHouse = $houseModel->getMediaForHouseIds(array_map(static fn ($h) => (int) $h['id'], $houses));
                ?>

                <?php foreach ($houses as $house): ?>

                    <?php
                        $houseId = (int) $house['id'];

                        $mediaItems = $mediaByHouse[$houseId] ?? [];

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

                        $isOwnHouse = ((int) $house['landlord_id'] === $tenantId);
                        $isHouseBooked = ($house['status'] !== 'available');
                        $isHouseReserved = ($house['status'] === 'reserved');   // NEW
                        $tenantStatus = $tenantBookingStatusByHouse[$houseId] ?? null;
                        $tenantHasPending = ($tenantStatus === 'pending');
                        $tenantHasApproved = ($tenantStatus === 'approved');
                    ?>

                    <div class="lux-card tenant-card lux-explore-card<?php echo $isHouseBooked ? ' lux-explore-card-unavailable' : ''; ?>"
                         data-house-id="<?php echo $houseId; ?>"
                         data-house-status="<?php echo htmlspecialchars($house['status']); ?>">

                        <!-- MEDIA -->
                        <div class="tenant-image lux-explore-media<?php echo $isHouseBooked ? ' lux-unavailable-media' : ''; ?>">

                            <?php if ($isHouseBooked): ?>

                                <div class="lux-explore-unavailable-badge">
                                    <?php echo htmlspecialchars(ListingState::label($house['status'])); ?>
                                </div>

                            <?php endif; ?>

                            <?php if ($videoUrl !== null): ?>

                                <div class="media-frame"
                                     data-video="<?php echo htmlspecialchars($videoUrl); ?>"
                                     data-caption="<?php echo htmlspecialchars($house['title']); ?>">

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
                                     data-caption="<?php echo htmlspecialchars($house['title']); ?>"
                                     data-current-index="0">

                                    <div class="media-carousel">

                                        <div class="media-carousel-track">

                                            <?php foreach ($imageUrls as $index => $url): ?>

                                                <img class="media-slide<?php echo $index === 0 ? ' is-active' : ''; ?>"
                                                     src="<?php echo htmlspecialchars($url); ?>"
                                                     data-index="<?php echo $index; ?>"
                                                     alt="Luxury House <?php echo $index + 1; ?>">

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

                                <div class="tenant-image-placeholder">
                                    No Image
                                </div>

                            <?php endif; ?>

                            <!-- PRICE BADGE -->
                            <div class="lux-explore-price-badge">
                                KES <?php echo number_format($house['price']); ?>
                            </div>

                        </div>

                        <!-- CONTENT -->
                        <div class="tenant-card-padding lux-explore-content">

                            <h2 class="lux-explore-card-title">
                                <?php echo htmlspecialchars($house['title']); ?>
                                <?php if (!empty($house['verified_at'])): ?>
                                   <!-- <span class="lux-verified-badge" title="Verified"><i class="fa-solid fa-circle-check"></i></span> -->
                                <?php endif; ?>
                            </h2>

                            <br>

                            <!-- RATING STARS -->
                            <div class="lux-explore-rating">

                                <?php
                                    $rating = (int)($house['rating'] ?? 0);

                                    if ($rating > 0) {
                                        for ($i = 1; $i <= 5; $i++) {
                                            echo ($i <= $rating)
                                                ? '★ '
                                                : '☆ ';
                                        }
                                    }
                                ?>
                            </div>
                                <br>

                            <!-- DESCRIPTION -->
                            <p class="lux-explore-desc">
                                <?php echo htmlspecialchars(substr($house['description'], 0, 120)); ?>...
                            </p>

                            <!-- DETAILS -->
                            <div class="tenant-meta lux-explore-meta-row">

                                <span style="color:var(--gray);">
                                    <?php echo htmlspecialchars($house['location']); ?>
                                </span>

                            </div>

                            <?php
                                $landlordIsVerified = (bool) ($verifiedLandlordMap[(int) $house['landlord_id']] ?? false);
                                $hasContactAccess = isset($contactHouseMap[$houseId]);
                            ?>

                            <?php if ($hasContactAccess): ?>

                                <div class="lux-landlord-contact">
                                    <span class="lux-landlord-contact-label">Your landlord</span>
                                    <?php echo htmlspecialchars($house['landlord_name']); ?>
                                    <?php if ($landlordIsVerified): ?>
                                        <span class="lux-verified-badge" title="Verified"><i class="fa-solid fa-circle-check"></i></span>
                                    <?php endif; ?>
                                    <?php if (!empty($house['landlord_phone'])): ?>
                                        <br><i class="fa-solid fa-phone"></i>
                                        <a href="tel:<?php echo htmlspecialchars($house['landlord_phone']); ?>"><?php echo htmlspecialchars($house['landlord_phone']); ?></a>
                                    <?php endif; ?>
                                    <?php if (!empty($house['landlord_email'])): ?>
                                        <br><i class="fa-solid fa-envelope"></i>
                                        <a href="mailto:<?php echo htmlspecialchars($house['landlord_email']); ?>"><?php echo htmlspecialchars($house['landlord_email']); ?></a>
                                    <?php endif; ?>
                                </div>

                            <?php endif; ?>

                            <?php if (!empty($house['has_parking']) || (!$hasContactAccess && $landlordIsVerified)): ?>

                                <div class="lux-explore-perks-row">
                                    <?php if (!empty($house['has_parking'])): ?>
                                        <span class="lux-parking-badge">
                                            <i class="fa-solid fa-square-parking"></i> Parking Available
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!$hasContactAccess && $landlordIsVerified): ?>
                                        <span class="lux-perk-chip">
                                            <i class="fa-solid fa-circle-check" style="color:var(--gold);"></i> Verified landlord
                                        </span>
                                    <?php endif; ?>
                                </div>

                            <?php endif; ?>

                            <!-- META -->
                            <?php
                                $bedroomsCount = (int) ($house['bedrooms'] ?? 0);
                                $bathroomsCount = (int) ($house['bathrooms'] ?? 0);
                            ?>

                            <?php if ($bedroomsCount > 0 || $bathroomsCount > 0): ?>
                            <div class="tenant-meta lux-explore-meta-row2">

                                <?php if ($bedroomsCount > 0): ?>
                                <span>
                                    <?php echo $bedroomsCount; ?> Bedroom<?php echo $bedroomsCount === 1 ? '' : 's'; ?>
                                </span>
                                <?php endif; ?>

                                <?php if ($bathroomsCount > 0): ?>
                                <span>
                                    <?php echo $bathroomsCount; ?> Bathroom<?php echo $bathroomsCount === 1 ? '' : 's'; ?>
                                </span>
                                <?php endif; ?>

                            </div>
                            <?php endif; ?>

                            <!-- BUTTONS -->
                            <div class="tenant-actions lux-explore-actions">

                                <a href="<?php echo BASE_URL; ?>/tenant/view-house?id=<?php echo $houseId; ?>"
                                   class="lux-btn lux-explore-btn-view">
                                    View Details
                                </a>

                                <?php if ($isOwnHouse): ?>

                                    <!-- Landlord viewing their own listing: no booking action -->

                                <?php elseif ($tenantHasPending): ?>

                                    <button type="button" class="lux-explore-btn-book lux-explore-btn-pending" disabled>
                                        Request Pending
                                    </button>

                                <?php elseif ($tenantHasApproved): ?>

                                    <button type="button" class="lux-explore-btn-book lux-explore-btn-pending" disabled>
                                        Booked by You
                                    </button>

                                <?php elseif ($isHouseBooked): ?>

                                    <button type="button" class="lux-explore-btn-book lux-explore-btn-unavailable" disabled>
                                        <?php echo htmlspecialchars(ListingState::label($house['status'])); ?>
                                    </button>

                                <?php else: ?>

                                    <button type="button"
                                            class="lux-explore-btn-book book-now-btn"
                                            data-house-id="<?php echo $houseId; ?>">
                                        Book Now
                                    </button>

                                <?php endif; ?>

                                <?php if (isset($paidHouseMap[$houseId]) && !$isOwnHouse): ?>

                                    <button type="button"
                                            class="lux-btn chat-starter-btn lux-explore-btn-chat"
                                            data-other-user-id="<?php echo (int) $house['landlord_id']; ?>"
                                            data-other-role="landlord"
                                            data-house-id="<?php echo $houseId; ?>"
                                            data-other-name="<?php echo htmlspecialchars($house['landlord_name']); ?>">
                                        <i class="fa-solid fa-comment-dots"></i> Message Landlord
                                    </button>

                                <?php endif; ?>

                            </div>

                        </div>

                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <div class="lux-card tenant-card tenant-card-padding lux-explore-empty-card">

                    <h2 class="lux-explore-empty-title">
                        No Luxury Properties Found
                    </h2>

                    <p class="lux-explore-empty-text">
                        Your search returned no Empire listings.
                        Try another keyword or location.
                    </p>

                </div>

            <?php endif; ?>

        </div>
        
        <?php if ($totalPages > 1): ?>
        <div style="display:flex; align-items:center; justify-content:center; gap:16px; margin-top:30px;">
            <?php if ($page > 1): ?>
                <a class="lux-btn lux-explore-btn-view" href="?page=<?php echo $page - 1; ?><?php echo $search !== '' ? '&search=' . urlencode($search) : ''; ?>">&laquo; Previous</a>
            <?php endif; ?>

            <span style="color:var(--gray);">Page <?php echo $page; ?> of <?php echo $totalPages; ?> (<?php echo $totalHouses; ?> properties)</span>

            <?php if ($page < $totalPages): ?>
                <a class="lux-btn lux-explore-btn-view" href="?page=<?php echo $page + 1; ?><?php echo $search !== '' ? '&search=' . urlencode($search) : ''; ?>">Next &raquo;</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

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
    window.LUX_BOOKING_CONFIG = {
        bookingFee: <?php echo (int) BOOKING_FEE_AMOUNT; ?>,
        reservationHours: <?php echo (int) RESERVATION_RESPONSE_HOURS; ?>,
        baseUrl: "<?php echo BASE_URL; ?>",
        csrfToken: "<?php echo htmlspecialchars($csrfToken); ?>"
    };
    window.LUX_TENANT_BOOKING_STATUS = <?php echo json_encode($tenantBookingStatusByHouse, JSON_FORCE_OBJECT); ?>;
    window.LUX_CURRENT_TENANT_ID = <?php echo (int) $tenantId; ?>;
    window.LUX_IS_GUEST = false;
    window.LUX_CARD_VARIANT = 'tenant';
</script>

<script src="<?php echo BASE_URL; ?>/assets/js/idempotency.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/offline-db.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/offline-drafts.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/property-media.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/bookings.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/house-filters.js"></script>

<?php require_once '../../includes/chat_starter_modal.php'; ?>

<script>
    window.LUX_PAYMENT_CONFIG = {
        baseUrl: "<?php echo BASE_URL; ?>",
        csrfToken: "<?php echo htmlspecialchars($csrfToken); ?>"
    };
</script>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/form-validation.css">
<script src="<?php echo BASE_URL; ?>/assets/js/form-validation.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/payment-modal.js"></script>

<?php require_once '../../includes/footer.php'; ?>