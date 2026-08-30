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
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/csrf.php';

Session::start();

$csrfToken = Csrf::token();

$houseModel = new House();

$search = trim($_GET['search'] ?? '');

$houses = $search !== ''
    ? $houseModel->searchHouses($search)
    : $houseModel->getAllHouses();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/property-media.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/bookings.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/guest-browse.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/tenant-register-modal.css">

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
                       name="search"
                       value="<?php echo htmlspecialchars($search); ?>"
                       placeholder="Search by title, location, or luxury features..."
                       class="guest-browse-search-input">
                <button type="submit" class="lux-btn">Search</button>
            </form>

            <div class="tenant-grid lux-explore-grid">

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

                            $isHouseBooked = ($house['status'] === 'booked');
                        ?>

                        <div class="lux-card tenant-card lux-explore-card<?php echo $isHouseBooked ? ' lux-explore-card-unavailable' : ''; ?>"
                             data-house-id="<?php echo $houseId; ?>">

                            <div class="tenant-image lux-explore-media">

                                <?php if ($isHouseBooked): ?>
                                    <div class="lux-explore-unavailable-badge">No Longer Available</div>
                                <?php endif; ?>

                                <?php if ($videoUrl !== null): ?>

                                    <video class="media-video" src="<?php echo htmlspecialchars($videoUrl); ?>" muted preload="metadata"></video>

                                <?php elseif (!empty($imageUrls)): ?>

                                    <img src="<?php echo htmlspecialchars($imageUrls[0]); ?>" alt="<?php echo htmlspecialchars($house['title']); ?>" style="width:100%;height:100%;object-fit:cover;">

                                <?php else: ?>

                                    <div class="tenant-image-placeholder">No Image</div>

                                <?php endif; ?>

                                <div class="lux-explore-price-badge">
                                    KES <?php echo number_format($house['price']); ?>
                                </div>

                            </div>

                            <div class="tenant-card-padding lux-explore-content">

                                <h2 class="lux-explore-card-title"><?php echo htmlspecialchars($house['title']); ?></h2>

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
                                            Unavailable
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

            <div class="guest-browse-truck-grid">

                <div class="lux-card guest-browse-truck-card">

                    <h2 class="guest-browse-truck-heading">Request Truck</h2>

                    <form id="guestTruckForm">

                        <div class="guest-browse-field">
                            <label>Pickup Location</label>
                            <div class="guest-browse-field-row">
                                <input type="text" id="pickupLocationInput" placeholder="Enter pickup location" required class="request-input">
                                <button type="button" id="useMyLocationBtn" class="lux-btn" style="white-space:nowrap;padding:0 18px;">
                                    <i class="fa-solid fa-location-crosshairs"></i>
                                </button>
                            </div>
                        </div>

                        <div class="guest-browse-field">
                            <label>Destination</label>
                            <input type="text" id="destinationInput" placeholder="Enter destination" required class="request-input">
                        </div>

                        <div class="guest-browse-field">
                            <label>Estimated Price (KES)</label>
                            <input type="number" id="guestTruckPrice" placeholder="Estimated transport cost" required class="request-input">
                        </div>

                        <input type="hidden" id="pickupLatInput">
                        <input type="hidden" id="pickupLngInput">
                        <input type="hidden" id="destinationLatInput">
                        <input type="hidden" id="destinationLngInput">

                        <button type="submit" class="lux-btn guest-browse-truck-submit">
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

    </main>

</div>

<!-- Guest house detail quick-view (populated via api/houses/fetch_house.php) -->
<div class="guest-detail-modal" id="guestDetailModal" aria-hidden="true">
    <div class="guest-detail-modal-overlay" data-guest-detail-close></div>
    <div class="guest-detail-modal-box">
        <button type="button" class="guest-detail-modal-close" data-guest-detail-close aria-label="Close">×</button>
        <div id="guestDetailModalContent">
            <div class="guest-detail-loading">Loading...</div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/tenant_register_modal.php'; ?>

<script>
    window.LUX_BOOKING_CONFIG = {
        baseUrl: "<?php echo BASE_URL; ?>",
        csrfToken: "<?php echo htmlspecialchars($csrfToken); ?>"
    };
    window.LUX_TENANT_REGISTER_CONFIG = {
        baseUrl: "<?php echo BASE_URL; ?>",
        csrfToken: "<?php echo htmlspecialchars($csrfToken); ?>",
        googleClientId: "<?php echo htmlspecialchars(GOOGLE_OAUTH_CLIENT_ID); ?>"
    };
</script>

<script src="https://accounts.google.com/gsi/client" async defer></script>
<script src="<?php echo BASE_URL; ?>/assets/js/tenant-register-modal.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/guest-browse.js"></script>
<script
    async
    defer
    src="https://maps.googleapis.com/maps/api/js?key=<?php echo GOOGLE_MAPS_API_KEY; ?>&libraries=places&callback=initRequestTruckMap">
</script>
<script src="<?php echo BASE_URL; ?>/assets/js/request-truck-location.js"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
