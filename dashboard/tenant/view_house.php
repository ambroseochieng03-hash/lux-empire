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

/**
 * A hidden listing is treated exactly like "not found" for tenants
 * — this page is tenant-only (requireRoleAccess('tenant')), so
 * there's no "owning landlord viewing their own page" case here to
 * carve out.
 */
if (!empty($house['is_hidden'])) {
    header("Location: search_houses.php?error=" . urlencode('This property is no longer available.'));
    exit();
}

require_once '../../classes/VerificationLookup.php';
$isLandlordVerified = (new VerificationLookup())->isVerified((int) $house['landlord_id']);

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
$isHouseBooked = ($house['status'] !== 'available');
$isHouseReserved = ($house['status'] === 'reserved');

$tenantBooking = $bookingModel->getTenantBookingForHouse($tenantId, $houseId);
$tenantStatus = $tenantBooking['status'] ?? null;
$tenantHasPending = ($tenantStatus === 'pending');
$tenantHasApproved = ($tenantStatus === 'approved');
$hasContactAccess = $bookingModel->hasContactAccessForHouse($tenantId, $houseId);

require_once '../../classes/PaymentWaiver.php';
$bookingVoucher = PaymentWaiver::activeTenantVoucher($tenantId);
$revealsOnPayment = in_array('pending', ListingState::contactRevealStatuses(), true);

if (
    $house['status'] === 'booked'
    && !empty($house['booked_at'])
    && !$tenantHasApproved
    && (time() - (int) strtotime((string) $house['booked_at'])) > TENANT_BOOKED_VISIBLE_HOURS * 3600
) {
    header("Location: search-houses?error=" . urlencode('This property is no longer available.'));
    exit();
}

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
require_once '../../includes/sidebar.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/property-media.css">

<style>
.lux-parking-badge{
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:6px 14px;
    border-radius:999px;
    background:linear-gradient(135deg, rgba(212,175,55,0.18), rgba(212,175,55,0.08));
    border:1px solid rgba(212,175,55,0.4);
    color:var(--gold);
    font-size:0.8rem;
    font-weight:600;
    letter-spacing:0.2px;
    white-space:nowrap;
    animation: luxParkingPulse 2.6s ease-in-out infinite;
}

.lux-parking-badge i{
    font-size:0.78rem;
}

@keyframes luxParkingPulse{
    0%, 100% { box-shadow: 0 0 0 0 rgba(212,175,55,0.25); }
    50%      { box-shadow: 0 0 0 5px rgba(212,175,55,0); }
}
</style>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/bookings.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/verification-badges.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/listing-state.css">

<div class="house-container">

    <div class="house-card<?php echo $isHouseBooked ? ' vh-card-unavailable' : ''; ?>"
         data-house-id="<?php echo $houseId; ?>"
         data-house-status="<?php echo htmlspecialchars($house['status']); ?>">

        <!-- MEDIA -->
        <div class="house-hero-media<?php echo $isHouseBooked ? ' lux-unavailable-media' : ''; ?>">

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
                <?php if (!empty($house['verified_at'])): ?>
                    <span class="lux-verified-badge" title="Verified"><i class="fa-solid fa-circle-check"></i></span>
                <?php endif; ?>
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
            <?php
                $bedroomsCount = (int) ($house['bedrooms'] ?? 0);
                $bathroomsCount = (int) ($house['bathrooms'] ?? 0);
            ?>
            <div class="house-grid">

                <?php if ($bedroomsCount > 0): ?>
                <div class="house-box">
                    <i class="fa-solid fa-bed vh-gold-icon"></i> Bedrooms: <?php echo $bedroomsCount; ?>
                </div>
                <?php endif; ?>

                <?php if ($bathroomsCount > 0): ?>
                <div class="house-box">
                    <i class="fa-solid fa-bath vh-gold-icon"></i> Bathrooms: <?php echo $bathroomsCount; ?>
                </div>
                <?php endif; ?>

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

                <?php if (!empty($house['has_parking'])): ?>
                <div class="house-box">
                    <span class="lux-parking-badge">
                        <i class="fa-solid fa-square-parking"></i> Parking Available
                    </span>
                </div>
                <?php endif; ?>

            </div>

            <!-- LANDLORD INFO — contact details unlock according to ListingState::contactRevealStatuses() -->
            <?php if ($hasContactAccess): ?>

            <div class="landlord-box">

                <h3 class="vh-landlord-title">
                    <i class="fa-solid fa-crown"></i> Contact Landlord
                </h3>

                <p class="vh-landlord-name">
                    <i class="fa-solid fa-user vh-gold-icon-tight"></i> <?php echo htmlspecialchars($house['landlord_name']); ?>
                    <?php if ($isLandlordVerified): ?>
                        <span class="lux-verified-badge" title="Verified"><i class="fa-solid fa-circle-check"></i></span>
                    <?php endif; ?>
                </p>

                <div class="vh-contact-row">

                    <?php if (!empty($house['landlord_phone'])): ?>
                        <a href="tel:<?php echo htmlspecialchars($house['landlord_phone']); ?>"
                           class="vh-call-btn">
                            <i class="fa-solid fa-phone"></i> Call
                        </a>
                    <?php endif; ?>

                    <?php if (!empty($house['landlord_email'])): ?>
                        <a href="mailto:<?php echo htmlspecialchars($house['landlord_email']); ?>"
                           class="vh-email-btn">
                            Email
                        </a>
                    <?php endif; ?>

                </div>

            </div>

            <?php else: ?>

            <div class="landlord-box lux-contact-locked">

                <h3 class="vh-landlord-title">
                    <i class="fa-solid fa-lock"></i> Landlord Contact
                </h3>

                <p class="vh-desc">
                    <?php if (!empty($tenantBooking['waiver_id']) && $tenantHasPending): ?>
                        You booked this property with your free voucher — nothing to pay. The landlord's phone and email
                        will appear here the moment they accept, and you can message them in the app while you wait.
                    <?php elseif ($revealsOnPayment): ?>
                        The landlord's phone and email are shared once you've paid the booking fee for this property.
                        If the landlord declines, your fee is refunded automatically.
                    <?php else: ?>
                        Pay the booking fee to secure this property and message the landlord in the app.
                        Their phone and email are shared once they accept your booking.
                        If the landlord declines, your fee is refunded automatically.
                    <?php endif; ?>
                </p>

                <?php if ($isLandlordVerified): ?>
                    <p class="vh-landlord-name">
                        <span class="lux-verified-badge" title="Verified"><i class="fa-solid fa-circle-check"></i></span> Verified landlord
                    </p>
                <?php endif; ?>

            </div>

            <?php endif; ?>

            <!-- ACTIONS -->
            <div class="actions">

                <?php if ($isOwnHouse): ?>

                    <!-- Landlord viewing their own listing: no booking action -->

                <?php elseif ($tenantHasPending): ?>

                    <button type="button" class="lux-btn vh-action-btn lux-explore-btn-pending" disabled>
                        Request Pending
                    </button>

                <?php elseif ($tenantHasApproved): ?>

                    <button type="button" class="lux-btn vh-action-btn lux-explore-btn-pending" disabled>
                        Booked by You
                    </button>

                <?php elseif ($isHouseBooked): ?>

                    <button type="button" class="lux-btn vh-action-btn lux-explore-btn-unavailable" disabled>
                        <?php echo htmlspecialchars(ListingState::label($house['status'])); ?>
                    </button>

                <?php else: ?>

                    <button type="button"
                            class="lux-btn vh-action-btn book-now-btn"
                            data-house-id="<?php echo $houseId; ?>">
                        Book Now
                    </button>

                <?php endif; ?>

                <a href="<?php echo BASE_URL; ?>/tenant/search-houses"
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
        bookingFee: <?php echo (int) BOOKING_FEE_AMOUNT; ?>,
        hasBookingVoucher: <?php echo $bookingVoucher ? 'true' : 'false'; ?>,
        voucherExpiresLabel: <?php echo json_encode($bookingVoucher ? date('d M Y, H:i', strtotime($bookingVoucher['expires_at'])) : ''); ?>,
        reservationHours: <?php echo (int) RESERVATION_RESPONSE_HOURS; ?>,
        baseUrl: "<?php echo BASE_URL; ?>",
        csrfToken: "<?php echo htmlspecialchars($csrfToken); ?>"
    };
</script>

<script src="<?php echo BASE_URL; ?>/assets/js/property-media.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/bookings.js"></script>

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