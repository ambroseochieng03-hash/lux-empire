<?php

require_once '../../includes/init.php';
require_once '../../includes/auth_check.php';
requireRoleAccess('tenant');

require_once '../../classes/House.php';

$houseModel = new House();

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

/*
 * Pull ALL media for this house via the existing
 * House::getHouseMedia() method (no LIMIT 1), then split
 * image vs video using the same ".mp4" rule
 * House::hasVideo()/hasImages() already use internally.
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

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
require_once '../../includes/sidebar.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/property-media.css">

<style>
.house-container{
    margin-left:280px;
    padding:40px;
    color:white;
}

.house-card{
    background:rgba(255,255,255,0.05);
    border-radius:28px;
    overflow:hidden;
}

.house-content{
    padding:30px;
}

.house-title{
    font-size:2.5rem;
    color:var(--gold);
    margin-bottom:10px;
    font-family:'Cinzel', serif;
}

.house-price{
    font-size:1.4rem;
    color:var(--gold);
    margin-bottom:20px;
}

.house-grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:20px;
    margin-top:20px;
}

.house-box{
    background:rgba(255,255,255,0.06);
    padding:15px;
    border-radius:16px;
}

.landlord-box{
    margin-top:25px;
    padding:20px;
    border-radius:16px;
    background:rgba(255,255,255,0.06);
}

.actions{
    margin-top:30px;
    display:flex;
    gap:15px;
    flex-wrap:wrap;
}

@media(max-width:768px){
    .house-container{
        margin-left:0;
        padding:20px;
    }

    .house-grid{
        grid-template-columns:1fr;
    }
}
</style>

<div class="house-container">

    <div class="house-card">

        <!-- MEDIA -->
        <div class="house-hero-media">

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
                <i class="fa-solid fa-sack-dollar" style="color:var(--gold); margin-right:8px;"></i> KES <?php echo number_format($house['price']); ?> / month
            </div>

            <!-- DESCRIPTION -->
            <p style="color:rgba(255,255,255,0.75); line-height:1.8;">
                <?php echo nl2br(htmlspecialchars($house['description'])); ?>
            </p>

            <!-- DETAILS GRID -->
            <div class="house-grid">

                <div class="house-box">
                    <i class="fa-solid fa-bed" style="color:var(--gold); margin-right:8px;"></i> Bedrooms: <?php echo $house['bedrooms']; ?>
                </div>

                <div class="house-box">
                    <i class="fa-solid fa-bath" style="color:var(--gold); margin-right:8px;"></i> Bathrooms: <?php echo $house['bathrooms']; ?>
                </div>

                <div class="house-box">
                    <i class="fa-solid fa-location-dot" style="color:var(--gold); margin-right:8px;"></i> Location: <?php echo htmlspecialchars($house['location']); ?>
                </div>

                <div class="house-box">
                    <i class="fa-solid fa-star" style="color:var(--gold); margin-right:8px;"></i> Rating:
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

                <h3 style="color:var(--gold); margin-bottom:15px;">
                    <i class="fa-solid fa-crown"></i> Contact Landlord
                </h3>

                <p style="margin-bottom:15px;">
                    <i class="fa-solid fa-user" style="color:var(--gold); margin-right:6px;"></i> <?php echo htmlspecialchars($house['landlord_name']); ?>
                </p>

                <div style="display:flex; gap:12px; flex-wrap:wrap;">

                    <!-- CALL BUTTON -->
                    <?php if (!empty($house['landlord_phone'])): ?>
                        <a href="tel:<?php echo htmlspecialchars($house['landlord_phone']); ?>"
                        style="
                                flex:1;
                                text-align:center;
                                padding:14px;
                                border-radius:14px;
                                background:linear-gradient(135deg,#d4af37,#f5d76e);
                                color:black;
                                font-weight:bold;
                                text-decoration:none;
                        ">
                            <i class="fa-solid fa-phone"></i> Call
                        </a>
                    <?php endif; ?>

                    <!-- EMAIL BUTTON -->
                    <?php if (!empty($house['landlord_email'])): ?>
                        <a href="mailto:<?php echo htmlspecialchars($house['landlord_email']); ?>"
                        style="
                                flex:1;
                                text-align:center;
                                padding:14px;
                                border-radius:14px;
                                background:rgba(255,255,255,0.08);
                                border:1px solid rgba(212,175,55,0.5);
                                color:var(--gold);
                                text-decoration:none;
                        ">
                            Email
                        </a>
                    <?php endif; ?>

                </div>

            </div>

            <!-- ACTIONS -->
            <div class="actions">

                <a href="<?php echo BASE_URL; ?>/api/houses/book_house.php?id=<?php echo $house['id']; ?>"
                   class="lux-btn"
                   style="flex:1; text-align:center; text-decoration:none;">
                    Book Now
                </a>

                <a href="<?php echo BASE_URL; ?>/dashboard/tenant/search_houses.php"
                   class="lux-btn"
                   style="flex:1; text-align:center; text-decoration:none;">
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

<script src="<?php echo BASE_URL; ?>/assets/js/property-media.js"></script>

<?php require_once '../../includes/footer.php'; ?>