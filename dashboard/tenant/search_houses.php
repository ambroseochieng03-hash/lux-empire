<?php

require_once '../../includes/init.php';
require_once '../../includes/auth_check.php';
requireRoleAccess('tenant');

require_once '../../classes/House.php';
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

if (!empty($search)) {
    $houses = $houseModel->searchHouses($search);
} else {
    $houses = $houseModel->getAllHouses();
}

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

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
require_once '../../includes/sidebar.php';
?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/property-media.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/bookings.css">

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
                       name="search"
                       value="<?php echo htmlspecialchars($search); ?>"
                       placeholder="Search by title, location, or luxury features..."
                       class="tenant-search-input lux-explore-search-input">

                <button type="submit"
                        class="lux-btn tenant-search-btn lux-explore-search-btn">
                    Search Empire
                </button>

            </form>

        </div>

        <!-- HOUSES GRID -->
        <div class="tenant-grid lux-explore-grid" id="exploreHousesGrid">

            <?php if (count($houses) > 0): ?>

                <?php foreach ($houses as $house): ?>

                    <?php
                        $houseId = (int) $house['id'];

                        $mediaItems = $houseModel->getHouseMedia($houseId);

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
                        $isHouseBooked = ($house['status'] === 'booked');
                        $tenantStatus = $tenantBookingStatusByHouse[$houseId] ?? null;
                        $tenantHasPending = ($tenantStatus === 'pending');
                        $tenantHasApproved = ($tenantStatus === 'approved');
                    ?>

                    <div class="lux-card tenant-card lux-explore-card<?php echo $isHouseBooked ? ' lux-explore-card-unavailable' : ''; ?>"
                         data-house-id="<?php echo $houseId; ?>"
                         data-house-status="<?php echo htmlspecialchars($house['status']); ?>">

                        <!-- MEDIA -->
                        <div class="tenant-image lux-explore-media">

                            <?php if ($isHouseBooked): ?>

                                <div class="lux-explore-unavailable-badge">
                                    No Longer Available
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

                                    <br>

                                <span style="color:var(--gray);">
                                    <div class="booking-lanlord-details">
                                        <?php echo htmlspecialchars($house['landlord_name']); ?>
                                        <br>

                                        <?php echo htmlspecialchars($house['landlord_phone'] ?? 'N/A'); ?>

                                        <br>

                                        <?php echo htmlspecialchars($house['landlord_email'] ?? 'N/A'); ?>

                                    </div>
                                </span>

                            </div>

                            <!-- META -->
                            <div class="tenant-meta lux-explore-meta-row2">

                                <span>
                                    <?php echo $house['bedrooms']; ?> Bedrooms
                                </span>

                                <span>
                                    <?php echo $house['bathrooms']; ?> Bathrooms
                                </span>

                            </div>

                            <!-- BUTTONS -->
                            <div class="tenant-actions lux-explore-actions">

                                <a href="<?php echo BASE_URL; ?>/dashboard/tenant/view_house.php?id=<?php echo $houseId; ?>"
                                   class="lux-btn lux-explore-btn-view">
                                    View Details
                                </a>

                                <?php if ($isOwnHouse): ?>

                                    <!-- Landlord viewing their own listing: no booking action -->

                                <?php elseif ($isHouseBooked): ?>

                                    <button type="button" class="lux-explore-btn-book lux-explore-btn-unavailable" disabled>
                                        Unavailable
                                    </button>

                                <?php elseif ($tenantHasPending): ?>

                                    <button type="button" class="lux-explore-btn-book lux-explore-btn-pending" disabled>
                                        Request Pending
                                    </button>

                                <?php elseif ($tenantHasApproved): ?>

                                    <button type="button" class="lux-explore-btn-book lux-explore-btn-pending" disabled>
                                        Booked by You
                                    </button>

                                <?php else: ?>

                                    <button type="button"
                                            class="lux-explore-btn-book book-now-btn"
                                            data-house-id="<?php echo $houseId; ?>">
                                        Book Now
                                    </button>

                                <?php endif; ?>

                                <button type="button"
                                        class="lux-btn chat-starter-btn lux-explore-btn-chat"
                                        data-other-user-id="<?php echo (int) $house['landlord_id']; ?>"
                                        data-other-role="landlord"
                                        data-house-id="<?php echo $houseId; ?>"
                                        data-other-name="<?php echo htmlspecialchars($house['landlord_name']); ?>">
                                    <i class="fa-solid fa-comment-dots"></i> Message Landlord
                                </button>

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
        baseUrl: "<?php echo BASE_URL; ?>",
        csrfToken: "<?php echo htmlspecialchars($csrfToken); ?>"
    };
</script>

<script src="<?php echo BASE_URL; ?>/assets/js/property-media.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/bookings.js"></script>

<?php require_once '../../includes/chat_starter_modal.php'; ?>

<?php require_once '../../includes/footer.php'; ?>