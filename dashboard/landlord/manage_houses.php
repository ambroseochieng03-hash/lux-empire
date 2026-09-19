<?php

require_once '../../includes/init.php';
require_once '../../includes/auth_check.php';
requireRoleAccess('landlord');

require_once '../../classes/House.php';

$houseModel = new House();

$houses = $houseModel->getHousesByLandlord((int) Session::user()['id']);

/**
 * Booked listings vanish from the landlord's own view 24h after
 * acceptance (they've already been unbookable/blurred since the
 * moment of acceptance — this just removes them from the grid
 * entirely once the grace period elapses). Permanent deletion from
 * the DB + disk happens separately, at 48h, via
 * scripts/cleanup_expired_listings.php (cron).
 */
$now = time();

$houses = array_values(array_filter($houses, static function ($house) use ($now) {
    if ($house['status'] !== 'booked' || empty($house['booked_at'])) {
        return true;
    }
    $bookedAt = strtotime($house['booked_at']);
    return $bookedAt !== false && ($now - $bookedAt) <= LANDLORD_BOOKED_VISIBLE_HOURS * 3600;
}));

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
require_once '../../includes/sidebar.php';
require_once '../../config/csrf.php';
$csrfToken = Csrf::token();

?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/property-media.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/booked-lock.css">

<style>

    .manage-main{
        flex:1;
        padding:40px;
        margin-left:280px;
        width:calc(100% - 280px);
        overflow-x:hidden;
    }

    .manage-header{
        display:flex;
        justify-content:space-between;
        align-items:center;
        margin-bottom:35px;
        flex-wrap:wrap;
        gap:20px;
    }

    .manage-grid{
        display:grid;
        grid-template-columns:repeat(auto-fit,minmax(320px,1fr));
        gap:30px;
    }

    .house-card{
        overflow:hidden;
        border-radius:24px;
        width:100%;
    }

    .house-content{
        padding:25px;
    }

    .house-title{
        color:white;
        margin-bottom:10px;
        font-size:1.5rem;
        line-height:1.4;
    }

    .house-description{
        color:var(--gray);
        margin-bottom:18px;
        line-height:1.7;
        word-break:break-word;
    }

    .house-details{
        display:flex;
        justify-content:space-between;
        margin-bottom:18px;
        flex-wrap:wrap;
        gap:10px;
    }

    .house-meta{
        display:flex;
        justify-content:space-between;
        margin-bottom:22px;
        color:var(--gray);
        font-size:0.95rem;
        gap:10px;
        flex-wrap:wrap;
    }

    .house-actions{
        display:flex;
        gap:12px;
        flex-wrap:wrap;
    }

    .action-btn{
        flex:1;
        min-width:140px;
        text-align:center;
        text-decoration:none;
        padding:14px;
        border-radius:14px;
        font-weight:bold;
        transition:0.3s;
        box-sizing:border-box;
    }

    .edit-btn{
        background:linear-gradient(135deg,#d4af37,#f5d76e);
        color:black;
    }

    .edit-btn:hover{
        transform:translateY(-2px);
    }

    .delete-btn{
        background:#ff3b3b;
        color:white;
    }

    .delete-btn:hover{
        opacity:0.9;
    }

    /* MOBILE RESPONSIVE */
    @media (max-width: 992px){

        .manage-main{
            margin-left:0 !important;
            width:100%;
            padding:25px;
        }

    }

    @media (max-width: 768px){

        .manage-main{
            padding:20px 16px;
        }

        .manage-header h1{
            font-size:2rem !important;
            line-height:1.3;
        }

        .manage-grid{
            grid-template-columns:1fr;
            gap:22px;
        }

        .house-content{
            padding:20px;
        }

        .house-title{
            font-size:1.25rem;
        }

        .house-details{
            flex-direction:column;
            align-items:flex-start;
        }

        .house-meta{
            flex-direction:column;
            align-items:flex-start;
        }

        .house-actions{
            flex-direction:column;
        }

        .action-btn{
            width:100%;
        }

    }

</style>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/verification-badges.css">

<div style="
    display:flex;
    min-height:100vh;
">

    <!-- MAIN -->
    <main class="manage-main">

        <!-- HEADER -->
        <div class="manage-header">

            <div>

                <h1 style="
                    font-family:'Cinzel', serif;
                    color:var(--gold);
                    font-size:3rem;
                    margin-bottom:10px;
                ">
                    My Luxury Properties
                </h1>

                <p style="
                    color:var(--gray);
                ">
                    Manage your premium Empire listings.
                </p>

            </div>

            <a href="<?php echo BASE_URL; ?>/dashboard/landlord/add_house.php"
               class="lux-btn"
               style="
                    text-decoration:none;
                    white-space:nowrap;
               ">
               + Add Property
            </a>

        </div>

        <!-- SUCCESS MESSAGE -->
        <?php if (isset($_GET['success'])): ?>

            <div style="
                background: rgba(0,255,100,0.08);
                border: 1px solid rgba(0,255,100,0.25);
                padding: 14px;
                border-radius: 14px;
                margin-bottom: 25px;
                color: #b8ffd2;
            ">
                <?php echo htmlspecialchars($_GET['success']); ?>
            </div>

        <?php endif; ?>

        <!-- ERROR MESSAGE -->
        <?php if (isset($_GET['error'])): ?>

            <div style="
                background: rgba(255,0,0,0.08);
                border: 1px solid rgba(255,0,0,0.25);
                padding: 14px;
                border-radius: 14px;
                margin-bottom: 25px;
                color: #ffb3b3;
            ">
                <?php echo htmlspecialchars($_GET['error']); ?>
            </div>

        <?php endif; ?>

        <!-- GRID -->
        <div class="manage-grid">

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

                        $isBookedLocked = ($house['status'] === 'booked' && !empty($house['booked_at']));
                        $isReserved = ($house['status'] === 'reserved');
                        $isMarkedUnavailable = ($house['status'] === 'unavailable');

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

                    <div class="lux-card house-card"
                         id="houseCard-<?php echo (int) $house['id']; ?>">

                        <!-- MEDIA -->
                        <div class="house-image<?php echo ($isBookedLocked || $isMarkedUnavailable) ? ' lux-media-blurred' : ''; ?>">

                            <?php if ($isBookedLocked): ?>
                                <div class="lux-booked-overlay">Booked</div>
                            <?php elseif ($isMarkedUnavailable): ?>
                                <div class="lux-booked-overlay">Unavailable</div>
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
                                                     alt="House Image <?php echo $index + 1; ?>">

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

                                <div class="house-placeholder">
                                    No Image
                                </div>

                            <?php endif; ?>

                        </div>

                        <!-- CONTENT -->
                        <div class="house-content">

                            <h2 class="house-title">
                                <?php echo htmlspecialchars($house['title']); ?>
                                <?php if (!empty($house['verified_at'])): ?>
                                    <span class="lux-verified-badge" title="Verified"><i class="fa-solid fa-circle-check"></i></span>
                                <?php endif; ?>
                            </h2>

                            <?php if (!empty($house['is_hidden'])): ?>
                                <div class="lux-listing-notice lux-listing-notice-hidden">
                                    This listing is hidden by admin and isn't visible to tenants. Editing and deletion are disabled while hidden.
                                </div>
                            <?php elseif (!empty($house['is_flagged'])): ?>
                                <div class="lux-listing-notice lux-listing-notice-flagged">
                                    This listing has been flagged for review by our team<?php echo !empty($house['flag_reason']) ? ': ' . htmlspecialchars($house['flag_reason']) : '.'; ?>
                                </div>
                            <?php endif; ?>

                            <!-- RATING -->
                            <div style="
                                margin-bottom:16px;
                                display:flex;
                                align-items:center;
                                gap:10px;
                                flex-wrap:wrap;
                            ">

                                <?php
                                    $rating = (int)($house['rating'] ?? 0);
                                ?>

                                <div style="
                                    display:flex;
                                    gap:3px;
                                    font-size:1.1rem;
                                ">

                                    <?php for($i = 1; $i <= 5; $i++): ?>

                                        <span style="
                                            color: <?php echo ($i <= $rating) ? '#d4af37' : 'rgba(255,255,255,0.2)'; ?>;
                                            text-shadow: <?php echo ($i <= $rating) ? '0 0 8px rgba(212,175,55,0.4)' : 'none'; ?>;
                                            transition:0.3s;
                                        ">
                                            ★
                                        </span>

                                    <?php endfor; ?>

                                </div>

                                <span style="
                                    color:var(--gray);
                                    font-size:0.9rem;
                                ">
                                    <?php echo $rating; ?>/5
                                </span>

                            </div>

                            <p class="house-description">
                                <?php
                                    echo htmlspecialchars(
                                        substr($house['description'], 0, 120)
                                    );
                                ?>...
                            </p>

                            <!-- DETAILS -->
                            <div class="house-details">

                                <span style="color:var(--gold);">
                                    KES <?php echo number_format($house['price']); ?>
                                </span>

                                <span style="color:var(--gray);">
                                    <?php echo htmlspecialchars($house['location']); ?>
                                </span>

                            </div>

                            <!-- META -->
                            <div class="house-meta">

                                <span>
                                    <?php echo $house['bedrooms']; ?> Bedrooms
                                </span>

                                <span>
                                    <?php echo $house['bathrooms']; ?> Bathrooms
                                </span>

                            </div>

                            <!-- ACTIONS -->
                            <div class="house-actions">

                                <?php if ($isBookedLocked || $isReserved): ?>

                                    <span class="action-btn edit-btn action-btn-disabled" title="<?php echo $isReserved ? 'This property has a pending paid booking' : 'This property is booked'; ?>">
                                        <?php echo $isReserved ? 'Reserved' : 'Booked'; ?>
                                    </span>

                                    <button type="button" class="action-btn delete-btn action-btn-disabled" disabled title="Resolve the pending booking first">
                                        Locked
                                    </button>

                                <?php else: ?>

                                    <a href="<?php echo BASE_URL; ?>/edit-property?id=<?php echo $house['id']; ?>"
                                    class="action-btn edit-btn">
                                        Edit
                                    </a>

                                    <?php if ($isMarkedUnavailable): ?>

                                        <button type="button"
                                                class="action-btn edit-btn external-toggle-btn"
                                                data-house-id="<?php echo (int) $house['id']; ?>"
                                                data-action="mark_available">
                                            Make Available Again
                                        </button>

                                    <?php else: ?>

                                        <button type="button"
                                                class="action-btn edit-btn external-toggle-btn"
                                                data-house-id="<?php echo (int) $house['id']; ?>"
                                                data-action="mark_unavailable">
                                            Booked Elsewhere?
                                        </button>

                                        <button type="button"
                                                class="action-btn delete-btn house-delete-btn"
                                                data-house-id="<?php echo (int) $house['id']; ?>">
                                            Delete
                                        </button>

                                    <?php endif; ?>

                                <?php endif; ?>

                            </div>

                        </div>

                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <div class="lux-card" style="
                    padding:40px;
                    text-align:center;
                    grid-column:1/-1;
                    border-radius:28px;
                ">

                    <h2 style="
                        color:var(--gold);
                        margin-bottom:15px;
                    ">
                        No Luxury Properties Yet
                    </h2>

                    <p style="
                        color:var(--gray);
                        margin-bottom:25px;
                        line-height:1.7;
                    ">
                        Start building your Empire portfolio now.
                    </p>

                    <a href="<?php echo BASE_URL; ?>/add-property"
                       class="lux-btn"
                       style="text-decoration:none;">
                       Add First Property
                    </a>

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
    window.LUX_MANAGE_HOUSES_CONFIG = {
        baseUrl: "<?php echo BASE_URL; ?>",
        csrfToken: "<?php echo htmlspecialchars($csrfToken); ?>"
    };
</script>
<script src="<?php echo BASE_URL; ?>/assets/js/property-media.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/manage-houses.js"></script>

<?php require_once '../../includes/footer.php'; ?>