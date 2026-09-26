<?php
require_once '../../includes/init.php';
require_once '../../includes/auth_check.php';
requireRoleAccess('landlord');

require_once '../../classes/Booking.php';
require_once '../../classes/House.php';
require_once '../../config/csrf.php';

$bookingModel = new Booking();
$houseModel = new House();
$csrfToken = Csrf::token();

/**
 * Pending queue only — approved/rejected requests no longer belong
 * here (§9). History remains in the bookings table untouched.
 */
$bookings = $bookingModel->getPendingBookingsByLandlord((int) Session::user()['id']);

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
require_once '../../includes/sidebar.php';
?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/property-media.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/bookings.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/verification-badges.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/listing-state.css">

<div class="landlord-layout">

    <!-- MAIN -->
    <main class="landlord-main">

        <!-- HEADER -->
        <div class="landlord-header">

            <h1 class="landlord-title">
                Booking Requests
            </h1>

            <p class="landlord-subtitle">
                Manage tenant requests for your luxury properties.
            </p>

        </div>

        <div style="display:flex; gap:12px; margin-bottom:24px; flex-wrap:wrap;">
            <a class="lux-btn" href="<?php echo BASE_URL; ?>/booking-requests">Pending Requests</a>
            <a class="lux-btn" style="background:var(--glass); color:var(--white);" href="<?php echo BASE_URL; ?>/dashboard/landlord/booking_history.php">History</a>
        </div>

        <!-- BOOKINGS GRID -->
        <div class="bookings-grid" id="landlordBookingsGrid">

            <?php if (count($bookings) > 0): ?>

                <?php foreach ($bookings as $booking): ?>

                    <?php
                        $bookingHouseId = (int) $booking['house_id'];

                        $mediaItems = $houseModel->getHouseMedia($bookingHouseId);

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

                    <div class="lux-card booking-card" data-booking-id="<?php echo (int) $booking['id']; ?>">

                        <!-- MEDIA -->
                        <div class="booking-image">

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

                                <div class="booking-placeholder">
                                    🏛
                                </div>

                            <?php endif; ?>

                        </div>

                        <!-- CONTENT -->
                        <div class="booking-content">

                            <h2 class="booking-property-title">
                                <?php echo htmlspecialchars($booking['title']); ?>
                            </h2>

                            <?php if (!empty($booking['is_hidden'])): ?>
                                <div class="lux-listing-notice lux-listing-notice-hidden">
                                    This listing is currently hidden by admin from new tenants. This existing request is unaffected.
                                </div>
                            <?php elseif (!empty($booking['is_flagged'])): ?>
                                <div class="lux-listing-notice lux-listing-notice-flagged">
                                    This listing is flagged for admin review. This existing request is unaffected.
                                </div>
                            <?php endif; ?>

                            <br>

                            <!-- HOUSE RATING -->
                            <div class="lb-rating-row">

                                <?php
                                    $rating = (int)($booking['rating'] ?? 0);
                                ?>

                                <div class="lb-stars">

                                    <?php for($i = 1; $i <= 5; $i++): ?>

                                        <span class="lb-star<?php echo ($i <= $rating) ? ' is-filled' : ''; ?>">★</span>

                                    <?php endfor; ?>

                                </div>

                                <span class="lb-rating-text">
                                    <?php echo $rating; ?>/5 Property Rating
                                </span>

                            </div>

                            <p class="booking-location">
                                <?php echo htmlspecialchars($booking['location']); ?>
                            </p>

                            <p class="booking-price">
                                KES <?php echo number_format($booking['price']); ?>
                            </p>

                            <!-- TENANT INFO — phone and email are only shared once you approve the request -->
                            <div class="booking-tenant-box">

                                <?php echo htmlspecialchars($booking['tenant_name']); ?>

                                <br>
                                <small style="color:var(--gray);">
                                    Phone and email are shared once you approve this request.
                                    Use "Message Tenant" to talk in the meantime.
                                </small>

                            </div>

                            <!-- STATUS -->
                            <?php $status = $booking['status']; ?>

                            <div class="booking-status status-<?php echo htmlspecialchars($status); ?>">
                                Status:
                                <?php echo ucfirst($status); ?>
                            </div>

                            <?php
                                $responseDeadline = strtotime((string) $booking['booking_date']) + RESERVATION_RESPONSE_HOURS * 3600;
                                $secondsLeft = $responseDeadline - time();
                            ?>

                            <?php if ($status === 'pending' && $secondsLeft > 0): ?>
                                <div style="color:var(--gray); font-size:0.85rem; margin:10px 0;">
                                    Respond within <?php echo max(1, (int) ceil($secondsLeft / 3600)); ?>h — unanswered requests are declined automatically and the tenant is refunded.
                                </div>
                            <?php endif; ?>

                            <div style="margin:12px 0;">
                                <button type="button"
                                        class="lux-btn chat-starter-btn"
                                        data-tenant-id="<?php echo (int) $booking['tenant_id']; ?>"
                                        <?php if (!empty($booking['house_id'])): ?>data-house-id="<?php echo (int) $booking['house_id']; ?>"<?php endif; ?>
                                        data-other-name="<?php echo htmlspecialchars($booking['tenant_name']); ?>">
                                    <i class="fa-solid fa-comment-dots"></i> Message Tenant
                                </button>
                            </div>

                            <!-- ACTIONS (this query only ever returns pending, but keep the guard) -->
                            <?php if ($status === "pending"): ?>

                                <div class="booking-actions">

                                    <!-- APPROVE -->
                                    <button type="button"
                                            class="booking-btn booking-btn-approve booking-action-btn"
                                            data-booking-id="<?php echo (int) $booking['id']; ?>"
                                            data-action="accept">
                                        Approve
                                    </button>

                                    <!-- REJECT -->
                                    <button type="button"
                                            class="booking-btn booking-btn-reject booking-action-btn"
                                            data-booking-id="<?php echo (int) $booking['id']; ?>"
                                            data-action="reject">
                                        Reject
                                    </button>

                                </div>

                            <?php else: ?>

                                <div class="booking-finished">
                                    Decision completed
                                </div>

                            <?php endif; ?>

                        </div>

                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <div class="lux-card empty-card" id="landlordBookingsEmpty">

                    <h2 class="empty-title">
                        No Booking Requests
                    </h2>

                    <p class="empty-text">
                        Tenant requests will appear here.
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