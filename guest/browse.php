<?php

/**
 * LUX EMPIRE — GUEST BROWSE
 *
 * Public page. No auth_check.php, no sidebar, no role requirement —
 * anyone can view house listings and fill in a truck request here.
 *
 * "Book Now" and the truck request form's submit are both gated:
 * clicking/submitting doesn't hit the real booking/truck endpoints
 * directly (those require an authenticated tenant session) — it
 * stores the intended action in sessionStorage and opens the tenant
 * registration modal instead (assets/js/guest-browse.js). Once
 * registration completes, tenant-register-modal.js checks for that
 * pending action and fires it automatically.
 */

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../classes/House.php';
require_once __DIR__ . '/../classes/ListingState.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/csrf.php';

Session::start();

$csrfToken = Csrf::token();

$currentUser = Session::isAuthenticated() ? Session::user() : null;
$currentRole = $currentUser['role'] ?? null;

$houseModel = new House();

$search = trim($_GET['search'] ?? '');

$houses = $search !== ''
    ? $houseModel->searchHouses($search)
    : $houseModel->getAllHouses();

$houses = array_values(array_filter($houses, static function ($house) {
    return (int) ($house['is_hidden'] ?? 0) === 0;
}));

require_once __DIR__ . '/../classes/VerificationLookup.php';

$landlordIdsOnPage = array_map(static fn ($h) => (int) $h['landlord_id'], $houses);
$verifiedLandlordMap = (new VerificationLookup())->getVerifiedMap($landlordIdsOnPage);    

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/property-media.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/bookings.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/guest-browse.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/tenant-register-modal.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/house-filters.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/verification-badges.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/listing-state.css">

<div class="guest-browse-page">

    <main class="guest-browse-main">

        <div class="guest-browse-hero">
            <h1 class="guest-browse-title">Explore The Empire</h1>
            <p class="guest-browse-subtitle">
                Browse luxury properties and request a move — sign up only when you're ready to book.
            </p>
        </div>

        <!-- TABS -->
        <div class="guest-browse-tabs">
            <button type="button" class="guest-browse-tab-btn is-active" data-guest-tab="houses">
                <i class="fa-solid fa-building"></i> House Listings
            </button>
            <button type="button" class="guest-browse-tab-btn" data-guest-tab="truck">
                <i class="fa-solid fa-truck-fast"></i> Request a Move
            </button>
        </div>

        <!-- ========================================= -->
        <!-- HOUSES -->
        <!-- ========================================= -->

        <section id="guestHousesSection" class="guest-browse-section">

            <form method="GET" action="" class="guest-browse-search-form">
                <input type="text"
                    id="houseKeywordInput"
                    name="search"
                    value="<?php echo htmlspecialchars($search); ?>"
                    placeholder="Search by title, location, or luxury features..."
                    class="guest-browse-search-input">
                <button type="submit" class="lux-btn">Search</button>
                <button type="button" class="lux-btn hf-trigger-btn" data-open-house-filters>
                    <i class="fa-solid fa-sliders"></i> Filters
                </button>
            </form>

            <?php require __DIR__ . '/../includes/house_filter_modal.php'; ?>

            <div class="tenant-grid lux-explore-grid" id="housesResultsGrid">

                <?php if (count($houses) > 0): ?>

                    <?php foreach ($houses as $house): ?>

                        <?php
                            $houseId = (int) $house['id'];
                            $mediaItems = $houseModel->getHouseMedia($houseId);

                            $imageUrls = [];
                            $videoUrl = null;

                            foreach ($mediaItems as $mediaItem) {
                                $path = BASE_URL . '/assets/uploads/house_images/' . $mediaItem['image_path'];
                                if (preg_match('/\.mp4$/i', $mediaItem['image_path'])) {
                                    $videoUrl = $path;
                                } else {
                                    $imageUrls[] = $path;
                                }
                            }

                            $isHouseBooked = ($house['status'] !== 'available');
                        ?>

                        <div class="lux-card tenant-card lux-explore-card<?php echo $isHouseBooked ? ' lux-explore-card-unavailable' : ''; ?>"
                             data-house-id="<?php echo $houseId; ?>"
                             data-house-status="<?php echo htmlspecialchars($house['status']); ?>"
                             data-house-label="<?php echo htmlspecialchars(ListingState::label($house['status'])); ?>">

                            <div class="tenant-image lux-explore-media<?php echo $isHouseBooked ? ' lux-unavailable-media' : ''; ?>">

                                <?php if ($isHouseBooked): ?>
                                    <div class="lux-explore-unavailable-badge"><?php echo htmlspecialchars(ListingState::label($house['status'])); ?></div>
                                <?php endif; ?>

                                <?php if ($videoUrl !== null): ?>

                                    <div class="media-frame"
                                         data-video="<?php echo htmlspecialchars($videoUrl); ?>"
                                         data-caption="<?php echo htmlspecialchars($house['title']); ?>">

                                        <video class="media-video"
                                               src="<?php echo htmlspecialchars($videoUrl); ?>"
                                               controls
                                               autoplay
                                               muted
                                               loop
                                               playsinline
                                               preload="metadata">
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
                                                         alt="<?php echo htmlspecialchars($house['title']); ?> <?php echo $index + 1; ?>">
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

                                    <div class="tenant-image-placeholder">No Image</div>

                                <?php endif; ?>

                                <div class="lux-explore-price-badge">
                                    KES <?php echo number_format($house['price']); ?>
                                </div>

                            </div>

                            <div class="tenant-card-padding lux-explore-content">

                                <h2 class="lux-explore-card-title">
                                    <?php echo htmlspecialchars($house['title']); ?>
                                    <?php if (!empty($house['verified_at'])): ?>
                                        <span class="lux-verified-badge" title="Verified"><i class="fa-solid fa-circle-check"></i></span>
                                    <?php endif; ?>
                                </h2>

                                <p class="lux-explore-desc">
                                    <?php echo htmlspecialchars(substr($house['description'], 0, 120)); ?>...
                                </p>

                                <div class="tenant-meta lux-explore-meta-row2">
                                    <span><?php echo htmlspecialchars($house['location']); ?></span>
                                    <span><?php echo $house['bedrooms']; ?> Beds · <?php echo $house['bathrooms']; ?> Baths</span>
                                </div>

                                <div class="tenant-actions lux-explore-actions">

                                    <button type="button"
                                            class="lux-btn lux-explore-btn-view guest-view-details-btn"
                                            data-house-id="<?php echo $houseId; ?>">
                                        View Details
                                    </button>

                                    <?php if ($isHouseBooked): ?>

                                        <button type="button" class="lux-explore-btn-book lux-explore-btn-unavailable" disabled>
                                            <?php echo htmlspecialchars(ListingState::label($house['status'])); ?>
                                        </button>

                                    <?php else: ?>

                                        <button type="button"
                                                class="lux-explore-btn-book guest-book-btn"
                                                data-house-id="<?php echo $houseId; ?>"
                                                data-house-title="<?php echo htmlspecialchars($house['title']); ?>">
                                            Book Now
                                        </button>

                                    <?php endif; ?>

                                </div>

                            </div>

                        </div>

                    <?php endforeach; ?>

                <?php else: ?>

                    <div class="lux-card tenant-card-padding lux-explore-empty-card">
                        <h2 class="lux-explore-empty-title">No Luxury Properties Found</h2>
                        <p class="lux-explore-empty-text">Try another keyword or location.</p>
                    </div>

                <?php endif; ?>

            </div>

        </section>

        <!-- ========================================= -->
        <!-- TRUCK REQUEST -->
        <!-- ========================================= -->

        <section id="guestTruckSection" class="guest-browse-section" hidden>

            <style>
                .request-card { padding:35px; border-radius:28px; }
                .request-input {
                    width:100%;
                    padding:16px;
                    border:1px solid var(--lux-card-border);
                    border-radius:16px;
                    background:var(--glass);
                    color:var(--white);
                    outline:none;
                    font-size:1rem;
                }
                .request-input::placeholder { color:var(--gray); }
                .trip-type-btn.is-active {
                    background: linear-gradient(135deg, var(--gold), var(--gold-secondary)) !important;
                    color: var(--black) !important;
                }
                @media (max-width: 768px) {
                    .request-card { padding:22px; border-radius:24px; }
                    .request-input { font-size:16px; }
                }
            </style>

            <div class="guest-browse-truck-grid">

                <div class="lux-card request-card">

                    <h2 style="color:var(--white); margin-bottom:25px; font-size:1.8rem;">
                        Request Truck
                    </h2>

                    <form id="guestTruckForm">

                        <input type="hidden" name="items_description" id="itemsDescriptionInput" value="">

                        <!-- TRIP TYPE TOGGLE -->
                        <div style="margin-bottom:26px;">
                            <label style="display:block; margin-bottom:10px; color:var(--gold); font-weight:600;">
                                When do you need this move?
                            </label>

                            <div style="display:flex; gap:12px;">
                                <button type="button" id="tripTypeInstantBtn" class="lux-btn trip-type-btn is-active" data-trip-type="instant" style="flex:1; padding:14px;">
                                    <i class="fa-solid fa-bolt"></i> Move Now
                                </button>
                                <button type="button" id="tripTypeScheduledBtn" class="lux-btn trip-type-btn" data-trip-type="scheduled" style="flex:1; padding:14px;">
                                    <i class="fa-solid fa-calendar-days"></i> Schedule for Later
                                </button>
                            </div>

                            <input type="hidden" name="trip_type" id="tripTypeInput" value="instant">
                        </div>

                        <!-- SCHEDULED DATE/TIME -->
                        <div style="margin-bottom:22px;" id="scheduledAtField" hidden>
                            <label style="display:block; margin-bottom:10px; color:var(--gold); font-weight:600;">
                                Move Date &amp; Time
                            </label>

                            <input type="datetime-local" name="scheduled_at" id="scheduledAtInput" class="request-input">

                            <div style="color:var(--gray); font-size:0.85rem; margin-top:8px;">
                                Must be at least <?php echo TRUCK_MIN_SCHEDULE_LEAD_MINUTES; ?> minutes from now, so drivers have a fair chance to accept.
                            </div>
                        </div>

                        <!-- PICKUP -->
                        <div style="margin-bottom:22px;">
                            <label style="display:block; margin-bottom:10px; color:var(--gold); font-weight:600;">
                                Pickup Location
                            </label>

                            <div style="display:flex; gap:10px;">
                                <input type="text" name="pickup_location" id="pickupLocationInput"
                                       placeholder="Enter pickup location" required class="request-input">

                                <button type="button" id="useMyLocationBtn" class="lux-btn" style="white-space:nowrap; padding:0 18px;">
                                    <i class="fa-solid fa-location-crosshairs"></i>
                                </button>
                            </div>
                        </div>

                        <!-- DESTINATION -->
                        <div style="margin-bottom:22px;">
                            <label style="display:block; margin-bottom:10px; color:var(--gold); font-weight:600;">
                                Destination
                            </label>

                            <input type="text" name="destination" id="destinationInput"
                                   placeholder="Enter destination" required class="request-input">
                        </div>

                        <!-- ITEMS -->
                        <div style="margin-bottom:22px;">
                            <button type="button" id="openItemsModalBtn" class="lux-btn" style="width:100%; padding:14px; background:var(--glass); color:var(--white);">
                                <i class="fa-solid fa-list-check"></i> List Your Items <span id="itemsCountBadge" style="color:var(--gold);"></span>
                            </button>
                        </div>

                        <!-- LIVE PRICE PREVIEW (informational only; the real price is computed server-side) -->
                        <div style="margin-bottom:30px; padding:18px; border-radius:16px; background:rgba(212,175,55,0.08); border:1px solid rgba(212,175,55,0.25);" id="pricePreviewBox">
                            <div style="color:var(--gray); font-size:0.85rem; margin-bottom:6px;">Estimated Price</div>
                            <div style="color:var(--gold); font-size:1.4rem; font-weight:bold;" id="pricePreviewAmount">
                                Enter pickup &amp; destination to see a price
                            </div>
                            <div style="color:var(--gray); font-size:0.8rem; margin-top:4px;" id="pricePreviewMeta"></div>
                        </div>

                        <input type="hidden" name="pickup_lat" id="pickupLatInput">
                        <input type="hidden" name="pickup_lng" id="pickupLngInput">
                        <input type="hidden" name="destination_lat" id="destinationLatInput">
                        <input type="hidden" name="destination_lng" id="destinationLngInput">

                        <button type="submit" class="lux-btn" id="requestTruckSubmitBtn"
                                style="width:100%; padding:18px; border:none; border-radius:18px; cursor:pointer; font-size:1rem;">
                            <i class="fa-solid fa-truck-fast"></i> Request Luxury Truck
                        </button>

                    </form>

                </div>

                <div class="lux-card guest-browse-truck-card">
                    <h2 class="guest-browse-truck-heading">Why Use Our Logistics?</h2>
                    <ul class="guest-browse-benefits-list">
                        <li>Professional verified drivers</li>
                        <li>Real-time GPS tracking</li>
                        <li>Fast property relocation</li>
                        <li>Secure and reliable transport</li>
                        <li>Mobile live updates</li>
                    </ul>
                </div>

            </div>

        </section>

        <!-- ITEMS MODAL (same as the tenant dashboard's) -->
        <div id="itemsModal" style="display:none; position:fixed; inset:0; z-index:2000; align-items:center; justify-content:center; padding:20px;">
            <div id="itemsModalOverlay" style="position:absolute; inset:0; background:rgba(0,0,0,0.75); backdrop-filter:blur(4px);"></div>

            <div style="position:relative; max-width:480px; width:100%; max-height:80vh; overflow-y:auto; background:var(--lux-modal-bg); border:1px solid var(--lux-modal-border); border-radius:22px; padding:28px;">

                <h2 style="color:var(--gold); font-family:'Cinzel', serif; font-size:1.3rem; margin-bottom:8px;">
                    What are you moving?
                </h2>
                <p style="color:var(--gray); font-size:0.9rem; margin-bottom:20px;">
                    List the items so your driver can prepare — optional, but helps them bring the right vehicle and manpower.
                </p>

                <div id="itemsRowsContainer" style="display:flex; flex-direction:column; gap:10px; margin-bottom:16px;"></div>

                <button type="button" id="addItemRowBtn" class="lux-btn" style="width:100%; background:var(--glass); color:var(--white); padding:12px; margin-bottom:20px;">
                    <i class="fa-solid fa-plus"></i> Add Another Item
                </button>

                <div style="display:flex; gap:12px;">
                    <button type="button" id="saveItemsBtn" class="lux-btn" style="flex:1; padding:14px;">Save</button>
                    <button type="button" id="closeItemsModalBtn" style="flex:1; padding:14px; background:var(--glass); color:var(--white); border:1px solid var(--lux-card-border); border-radius:14px; cursor:pointer;">Cancel</button>
                </div>

            </div>
        </div>

    </main>

</div>

<!-- Guest house detail quick-view (populated via api/houses/fetch_houses.php) -->
<div class="guest-detail-modal" id="guestDetailModal" aria-hidden="true">
    <div class="guest-detail-modal-overlay" data-guest-detail-close></div>
    <div class="guest-detail-modal-box">
        <button type="button" class="guest-detail-modal-close" data-guest-detail-close aria-label="Close">×</button>
        <div id="guestDetailModalContent">
            <div class="guest-detail-loading">Loading...</div>
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

<?php require __DIR__ . '/../includes/tenant_register_modal.php'; ?>

<script>
    window.LUX_BOOKING_CONFIG = {
        bookingFee: <?php echo (int) BOOKING_FEE_AMOUNT; ?>,
        reservationHours: <?php echo (int) RESERVATION_RESPONSE_HOURS; ?>,
        baseUrl: "<?php echo BASE_URL; ?>",
        csrfToken: "<?php echo htmlspecialchars($csrfToken); ?>"
    };
    window.LUX_TENANT_REGISTER_CONFIG = {
        baseUrl: "<?php echo BASE_URL; ?>",
        csrfToken: "<?php echo htmlspecialchars($csrfToken); ?>",
        googleClientId: "<?php echo htmlspecialchars(GOOGLE_OAUTH_CLIENT_ID); ?>"
    };
    window.LUX_TENANT_BOOKING_STATUS = {};
    window.LUX_CURRENT_TENANT_ID = <?php echo ($currentRole === 'tenant') ? (int) $currentUser['id'] : 'null'; ?>;
    window.LUX_IS_GUEST = <?php echo $currentUser === null ? 'true' : 'false'; ?>;
    window.LUX_CURRENT_USER_ROLE = <?php echo json_encode($currentRole); ?>;
    window.LUX_CARD_VARIANT = 'guest';
</script>

<script src="https://accounts.google.com/gsi/client" async defer></script>
<script src="<?php echo BASE_URL; ?>/assets/js/offline-db.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/offline-drafts.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/property-media.js"></script>
<script>
    window.LUX_PAYMENT_CONFIG = {
        baseUrl: "<?php echo BASE_URL; ?>",
        csrfToken: "<?php echo htmlspecialchars($csrfToken); ?>"
    };
</script>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/form-validation.css">
<script src="<?php echo BASE_URL; ?>/assets/js/form-validation.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/payment-modal.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/tenant-register-modal.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/guest-browse.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/house-filters.js"></script>
<script
    async
    defer
    src="https://maps.googleapis.com/maps/api/js?key=<?php echo GOOGLE_MAPS_API_KEY; ?>&libraries=places&callback=initRequestTruckMap">
</script>
<script src="<?php echo BASE_URL; ?>/assets/js/request-truck-location.js"></script>
<script>
    window.LUX_TRUCK_FORM_CONFIG = { baseUrl: "<?php echo BASE_URL; ?>" };
</script>
<script src="<?php echo BASE_URL; ?>/assets/js/truck-request-form.js"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>