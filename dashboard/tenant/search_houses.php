<?php

require_once '../../includes/init.php';
require_once '../../includes/auth_check.php';
requireRoleAccess('tenant');

require_once '../../classes/House.php';

$houseModel = new House();

/**
 * Search handling
 */
$search = trim($_GET['search'] ?? '');

if (!empty($search)) {
    $houses = $houseModel->searchHouses($search);
} else {
    $houses = $houseModel->getAllHouses();
}

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
require_once '../../includes/sidebar.php';
?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/property-media.css">

<style>
    .booking-lanlord-details{
        background:rgba(255,255,255,0.05);
        padding:15px;
        border-radius:14px;
        margin-bottom:18px;
        color:var(--gray);
        font-size:0.95rem;
        line-height:1.8;
        word-break:break-word;
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
    ">

        <!-- HERO -->
        <div style="
            margin-bottom:40px;
        ">

            <h1 class="tenant-title" style="
                font-family:'Cinzel', serif;
                font-size:3rem;
                color:var(--gold);
                margin-bottom:10px;
            ">
                Discover Luxury Living
            </h1>

            <p style="
                color:var(--gray);
                max-width:700px;
                line-height:1.8;
            ">
                Explore elite homes, premium apartments, and prestigious spaces across the Empire.
            </p>

        </div>

        <!-- SEARCH BAR -->
        <div class="lux-card tenant-card tenant-card-padding" style="
            padding:25px;
            margin-bottom:40px;
            border-radius:24px;
        ">

            <form method="GET"
                  action=""
                  class="tenant-search-form"
                  style="
                    display:flex;
                    gap:15px;
                    flex-wrap:wrap;
                  ">

                <input type="text"
                       name="search"
                       value="<?php echo htmlspecialchars($search); ?>"
                       placeholder="Search by title, location, or luxury features..."
                       class="tenant-search-input"
                       style="
                            flex:1;
                            min-width:250px;
                            padding:16px;
                            border:none;
                            border-radius:16px;
                       ">

                <button type="submit"
                        class="lux-btn tenant-search-btn"
                        style="
                            padding:16px 28px;
                            border-radius:16px;
                        ">
                    Search Empire
                </button>

            </form>

        </div>

        <!-- HOUSES GRID -->
        <div class="tenant-grid" style="
            display:grid;
            grid-template-columns:repeat(auto-fit,minmax(340px,1fr));
            gap:30px;
        ">

            <?php if (count($houses) > 0): ?>

                <?php foreach ($houses as $house): ?>

                    <?php
                        /*
                         * Pull ALL media for this house via the existing
                         * House::getHouseMedia() method (no LIMIT 1),
                         * then split image vs video using the same
                         * ".mp4" rule House::hasVideo()/hasImages()
                         * already use internally.
                         */
                        $mediaItems = $houseModel->getHouseMedia((int) $house['id']);

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

                    <div class="lux-card tenant-card" style="
                        overflow:hidden;
                        border-radius:24px;
                        transition:0.4s;
                    ">

                        <!-- MEDIA -->
                        <div class="tenant-image" style="position:relative;">

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
                            <div style="
                                position:absolute;
                                top:15px;
                                right:15px;
                                background:rgba(0,0,0,0.75);
                                padding:10px 15px;
                                border-radius:14px;
                                color:var(--gold);
                                font-weight:bold;
                                backdrop-filter:blur(10px);
                                pointer-events:none;
                                z-index:3;
                            ">
                                KES <?php echo number_format($house['price']); ?>
                            </div>

                        </div>

                        <!-- CONTENT -->
                        <div class="tenant-card-padding" style="padding:25px;">

                            <h2 style="
                                color:white;
                                font-size:1.6rem;
                                margin-bottom:10px;
                            ">
                                <?php echo htmlspecialchars($house['title']); ?>
                            </h2>
                                <br>
                            <!-- RATING STARS -->

                            <div style="
                                color:var(--gold);
                                font-size:1.1rem;
                                margin-bottom:14px;
                            ">
                             
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

                            <p style="
                                color:rgba(255,255,255,0.72);
                                line-height:1.8;
                                margin-bottom:18px;
                                font-size:0.95rem;
                                letter-spacing:0.2px;
                                font-weight:400;
                            ">
                                <?php echo htmlspecialchars(substr($house['description'], 0, 120)); ?>...
                            </p>

                            <!-- DETAILS -->
                            <div class="tenant-meta" style="
                                display:flex;
                                justify-content:space-between;
                                flex-wrap:wrap;
                                gap:12px;
                                margin-bottom:18px;
                            ">

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
                            <div class="tenant-meta" style="
                                display:flex;
                                justify-content:space-between;
                                margin-bottom:25px;
                                color:var(--gray);
                            ">

                                <span>
                                    <?php echo $house['bedrooms']; ?> Bedrooms
                                </span>

                                <span>
                                    <?php echo $house['bathrooms']; ?> Bathrooms
                                </span>

                            </div>

                            <!-- BUTTONS -->
                            <div class="tenant-actions" style="
                                display:flex;
                                gap:12px;
                                flex-wrap:wrap;
                            ">

                                <a href="<?php echo BASE_URL; ?>/dashboard/tenant/view_house.php?id=<?php echo $house['id']; ?>"
                                   class="lux-btn"
                                   style="
                                        flex:1;
                                        text-align:center;
                                        text-decoration:none;
                                   ">
                                    View Details
                                </a>

                                <a href="<?php echo BASE_URL; ?>/api/houses/book_house.php?id=<?php echo $house['id']; ?>"
                                   style="
                                        flex:1;
                                        text-align:center;
                                        text-decoration:none;
                                        padding:14px;
                                        border-radius:14px;
                                        background:linear-gradient(135deg,#d4af37,#f5d76e);
                                        color:black;
                                        font-weight:bold;
                                   ">
                                    Book Now
                                </a>

                                <button type="button"
                                        class="lux-btn chat-starter-btn"
                                        data-other-user-id="<?php echo (int) $house['landlord_id']; ?>"
                                        data-other-role="landlord"
                                        data-house-id="<?php echo (int) $house['id']; ?>"
                                        data-other-name="<?php echo htmlspecialchars($house['landlord_name']); ?>"
                                        style="
                                            flex:1;
                                            text-align:center;
                                            padding:14px;
                                            border-radius:14px;
                                            background:rgba(255,255,255,0.08);
                                            color:var(--gold);
                                            border:1px solid var(--gold);
                                            font-weight:bold;
                                            cursor:pointer;
                                        ">
                                    <i class="fa-solid fa-comment-dots"></i> Message Landlord
                                </button>

                            </div>

                        </div>

                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <div class="lux-card tenant-card tenant-card-padding" style="
                    padding:50px;
                    text-align:center;
                    grid-column:1/-1;
                    border-radius:28px;
                ">

                    <h2 style="
                        color:var(--gold);
                        margin-bottom:15px;
                    ">
                        No Luxury Properties Found
                    </h2>

                    <p style="
                        color:var(--gray);
                        line-height:1.7;
                    ">
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

<script src="<?php echo BASE_URL; ?>/assets/js/property-media.js"></script>

<?php require_once '../../includes/chat_starter_modal.php'; ?>

<?php require_once '../../includes/footer.php'; ?>