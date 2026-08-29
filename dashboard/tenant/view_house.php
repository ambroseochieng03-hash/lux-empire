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

$id = $_GET['id'] ?? null;

if (!$id) {
    header("Location: search_houses.php?error=House not found");
    exit();
}

$house = $houseModel->getHouseById($id);

if (!$house) {
    header("Location: search_houses.php?error=House not found");
    exit();
}

$houseId = (int) $house['id'];

/*
 * Pull ALL media for this house via the existing
 * House::getHouseMedia() method (no LIMIT 1), then split
 * image vs video using the same ".mp4" rule
 * House::hasVideo()/hasImages() already use internally.
 */
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

/*
 * Booking-button state — same rules as search_houses.php:
 *   - landlord viewing their own listing: no booking action
 *   - house already booked (by anyone): disabled "Unavailable"
 *   - this tenant already has a pending request: disabled "Request Pending"
 *   - this tenant's request was already approved: disabled "Booked by You"
 *   - otherwise: clickable "Book Now" (AJAX, handled by bookings.js)
 */
$isOwnHouse = ((int) $house['landlord_id'] === $tenantId);
$isHouseBooked = ($house['status'] === 'booked');

$tenantBooking = $bookingModel->getTenantBookingForHouse($tenantId, $houseId);
$tenantStatus = $tenantBooking['status'] ?? null;
$tenantHasPending = ($tenantStatus === 'pending');
$tenantHasApproved = ($tenantStatus === 'approved');

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
require_once '../../includes/sidebar.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/property-media.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/bookings.css">

<div class="house-container">

    <div class="house-card<?php echo $isHouseBooked ? ' vh-card-unavailable' : ''; ?>"
         data-house-id="<?php echo $houseId; ?>"
         data-house-status="<?php echo htmlspecialchars($house['status']); ?>">

        <!-- MEDIA -->
        <div class="house-hero-media">

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
                                     alt="<?php echo htmlspecialchars($house['title']); ?> image <?php echo $index + 1; ?>">

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

                <div class="house-hero-placeholder">
                    No Image Available
                </div>

            <?php endif; ?>

        </div>

        <div class="house-content">

            <!-- TITLE -->
            <h1 class="house-title">
                <?php echo htmlspecialchars($house['title']); ?>
            </h1>

            <!-- PRICE -->
            <div class="house-price">
                <i class="fa-solid fa-sack-dollar vh-gold-icon"></i> KES <?php echo number_format($house['price']); ?> / month
            </div>

            <!-- DESCRIPTION -->
            <p class="vh-desc">
                <?php echo nl2br(htmlspecialchars($house['description'])); ?>
            </p>

            <!-- DETAILS GRID -->
            <div class="house-grid">

                <div class="house-box">
                    <i class="fa-solid fa-bed vh-gold-icon"></i> Bedrooms: <?php echo $house['bedrooms']; ?>
                </div>

                <div class="house-box">
                    <i class="fa-solid fa-bath vh-gold-icon"></i> Bathrooms: <?php echo $house['bathrooms']; ?>
                </div>

                <div class="house-box">
                    <i class="fa-solid fa-location-dot vh-gold-icon"></i> Location: <?php echo htmlspecialchars($house['location']); ?>
                </div>

                <div class="house-box">
                    <i class="fa-solid fa-star vh-gold-icon"></i> Rating:
                    <?php
                        $rating = (int)($house['rating'] ?? 0);
                        for ($i = 1; $i <= 5; $i++) {
                            echo ($i <= $rating) ? '★ ' : '☆ ';
                        }
                    ?>
                </div>

            </div>

            <!-- LANDLORD INFO -->
            <div class="landlord-box">

                <h3 class="vh-landlord-title">
                    <i class="fa-solid fa-crown"></i> Contact Landlord
                </h3>

                <p class="vh-landlord-name">
                    <i class="fa-solid fa-user vh-gold-icon-tight"></i> <?php echo htmlspecialchars($house['landlord_name']); ?>
                </p>

                <div class="vh-contact-row">

                    <!-- CALL BUTTON -->
                    <?php if (!empty($house['landlord_phone'])): ?>
                        <a href="tel:<?php echo htmlspecialchars($house['landlord_phone']); ?>"
                           class="vh-call-btn">
                            <i class="fa-solid fa-phone"></i> Call
                        </a>
                    <?php endif; ?>

                    <!-- EMAIL BUTTON -->
                    <?php if (!empty($house['landlord_email'])): ?>
                        <a href="mailto:<?php echo htmlspecialchars($house['landlord_email']); ?>"
                           class="vh-email-btn">
                            Email
                        </a>
                    <?php endif; ?>

                </div>

            </div>

            <!-- ACTIONS -->
            <div class="actions">

                <?php if ($isOwnHouse): ?>

                    <!-- Landlord viewing their own listing: no booking action -->

                <?php elseif ($isHouseBooked): ?>

                    <button type="button" class="lux-btn vh-action-btn lux-explore-btn-unavailable" disabled>
                        Unavailable
                    </button>

                <?php elseif ($tenantHasPending): ?>

                    <button type="button" class="lux-btn vh-action-btn lux-explore-btn-pending" disabled>
                        Request Pending
                    </button>

                <?php elseif ($tenantHasApproved): ?>

                    <button type="button" class="lux-btn vh-action-btn lux-explore-btn-pending" disabled>
                        Booked by You
                    </button>

                <?php else: ?>

                    <button type="button"
                            class="lux-btn vh-action-btn book-now-btn"
                            data-house-id="<?php echo $houseId; ?>">
                        Book Now
                    </button>

                <?php endif; ?>

                <a href="<?php echo BASE_URL; ?>/dashboard/tenant/search_houses.php"
                   class="lux-btn vh-action-btn">
                    <i class="fa-solid fa-arrow-left"></i> Back to Listings
                </a>

            </div>

        </div>

    </div>

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

<?php require_once '../../includes/footer.php'; ?>