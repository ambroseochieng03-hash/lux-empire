<?php


require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';

require_once __DIR__ . '/classes/House.php';

$heroHouseModel = new House();
$heroImageUrls  = [];
$heroVideoUrl   = null;
$heroTitle      = 'Modern Elite Residence';
$heroLocation   = 'Nairobi';
$heroPrice      = 120000;

foreach ($heroHouseModel->getAllHouses() as $candidateHouse) {

    if (($candidateHouse['status'] ?? '') === 'booked') {
        continue; // don't showcase something no longer available
    }

    $candidateMedia = $heroHouseModel->getHouseMedia((int) $candidateHouse['id']);

    if (empty($candidateMedia)) {
        continue;
    }

    $candidateImages = [];
    $candidateVideo  = null;

    foreach ($candidateMedia as $mediaItem) {
        $path = BASE_URL . '/assets/uploads/house_images/' . $mediaItem['image_path'];
        if (preg_match('/\.mp4$/i', $mediaItem['image_path'])) {
            $candidateVideo = $path;
        } else {
            $candidateImages[] = $path;
        }
    }

    $heroImageUrls = $candidateImages;
    $heroVideoUrl  = $candidateVideo;
    $heroTitle     = $candidateHouse['title'];
    $heroLocation  = $candidateHouse['location'];
    $heroPrice     = $candidateHouse['price'];
    break; // newest house with usable media
}

$heroHasRealMedia = ($heroVideoUrl !== null || !empty($heroImageUrls));

?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/nav-menu.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/home.css">

<!-- HERO SECTION -->
<section class="home-hero">

    <!-- BACKGROUND GLOW -->
    <div class="home-hero-glow-1"></div>
    <div class="home-hero-glow-2"></div>

    <!-- CONTENT -->
    <div class="home-hero-grid">

        <!-- LEFT -->
        <div>

            <span class="home-badge">
                Welcome to LUX EMPIRE
            </span>

            <h1 class="home-hero-title">
                <span id="homeTypewriter" class="home-hero-title-typed" aria-hidden="true"></span><span class="home-typewriter-cursor">|</span>
                <span class="sr-only" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0);">
                    Where Luxury Finds Home.
                </span>
            </h1>

            <p class="home-hero-desc">
                Experience elite property living, premium rentals,
                and intelligent logistics powered by modern luxury technology.
                LUX EMPIRE connects tenants, landlords, and moving services
                in one powerful ecosystem.
            </p>

            <!-- ACTIONS: single compact nav menu, not a button pair -->
            <div class="home-hero-actions">
                <?php
                    $navMenuTriggerLabel = 'Get Started';
                    require __DIR__ . '/includes/nav_menu.php';
                ?>
            </div>

        </div>

        <!-- RIGHT -->
        <div class="home-hero-visual">

            <!-- MAIN CARD -->
            <div class="lux-card home-hero-card">

                <div class="home-hero-media-wrap">

                    <?php if ($heroHasRealMedia && $heroVideoUrl !== null): ?>

                        <div class="media-frame"
                            data-video="<?php echo htmlspecialchars($heroVideoUrl); ?>"
                            data-caption="<?php echo htmlspecialchars($heroTitle); ?>">

                            <video class="media-video"
                                src="<?php echo htmlspecialchars($heroVideoUrl); ?>"
                                controls autoplay muted loop playsinline preload="metadata">
                            </video>

                            <button type="button" class="media-enlarge-btn" aria-label="Enlarge video">⤢</button>

                        </div>

                    <?php elseif ($heroHasRealMedia): ?>

                        <?php $heroImagesJson = json_encode($heroImageUrls); ?>

                        <div class="media-frame"
                            data-images='<?php echo htmlspecialchars($heroImagesJson, ENT_QUOTES); ?>'
                            data-caption="<?php echo htmlspecialchars($heroTitle); ?>"
                            data-current-index="0">

                            <div class="media-carousel">
                                <div class="media-carousel-track">
                                    <?php foreach ($heroImageUrls as $index => $url): ?>
                                        <img class="media-slide<?php echo $index === 0 ? ' is-active' : ''; ?>"
                                            src="<?php echo htmlspecialchars($url); ?>"
                                            data-index="<?php echo $index; ?>"
                                            alt="<?php echo htmlspecialchars($heroTitle); ?> <?php echo $index + 1; ?>">
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <?php if (count($heroImageUrls) > 1): ?>
                                <button type="button" class="media-carousel-btn media-carousel-prev" aria-label="Previous image">‹</button>
                                <button type="button" class="media-carousel-btn media-carousel-next" aria-label="Next image">›</button>
                                <div class="media-carousel-dots">
                                    <?php foreach ($heroImageUrls as $index => $url): ?>
                                        <span class="media-dot<?php echo $index === 0 ? ' is-active' : ''; ?>" data-index="<?php echo $index; ?>"></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <button type="button" class="media-enlarge-btn" aria-label="Enlarge image">⤢</button>

                        </div>

                    <?php else: ?>

                        <img src="<?php echo BASE_URL; ?>/assets/images/houses/luxury-house.jpg"
                            alt="Luxury House" class="home-hero-fallback-img">

                    <?php endif; ?>

                    <div class="home-hero-price-badge">
                        KES <?php echo number_format($heroPrice); ?>
                    </div>

                </div>

                <!-- CONTENT — below the media now, never overlapping it -->
                <div class="home-hero-card-body">

                    <div class="home-hero-card-top">
                        <span class="home-hero-card-tag">Premium Villa</span>
                        <span class="home-hero-card-location"><?php echo htmlspecialchars($heroLocation); ?></span>
                    </div>

                    <h2 class="home-hero-card-title"><?php echo htmlspecialchars($heroTitle); ?></h2>

                    <p class="home-hero-card-desc">
                        Sophisticated architecture designed for luxury living and elite comfort.
                    </p>

                    <a href="<?php echo BASE_URL; ?>/browse" class="lux-btn home-hero-card-view-btn">
                        View Property
                    </a>

                </div>

            </div>

        </div>

    </div>

    <!-- Decorative truck+house animation — desktop-only, fills the empty right margin -->
    <div class="home-hero-side-decor" aria-hidden="true">
        <div class="home-hero-decor-road"></div>
        <i class="fa-solid fa-house home-hero-decor-house"></i>
        <i class="fa-solid fa-truck-fast home-hero-decor-truck"></i>
    </div>

</section>

<!-- STATS -->
<section class="home-stats-section">

    <div class="home-stats-grid">

        <?php
        $stats = [
            ['15K+', 'Luxury Properties'],
            ['2300+', 'Verified Landlords'],
            ['98%', 'Client Satisfaction'],
            ['24/7', 'Premium Support']
        ];

        foreach ($stats as $stat):
        ?>

        <div class="lux-card home-stat-card">

            <h2 class="home-stat-number">
                <?php echo $stat[0]; ?>
            </h2>

            <p class="home-stat-label">
                <?php echo $stat[1]; ?>
            </p>

        </div>

        <?php endforeach; ?>

    </div>

</section>

<!-- WHY CHOOSE US -->
<section class="home-features-section">

    <div class="home-features-container">

        <div class="home-features-header">

            <h2 class="home-features-title">
                Why Choose <span class="home-gold-text">LUX EMPIRE</span>
            </h2>

            <p class="home-features-subtitle">
                Built for modern luxury living with intelligent technology,
                elegant experiences, and premium logistics.
            </p>

        </div>

        <div class="home-features-grid">

            <?php
            $features = [
                ['fa-building', 'Luxury Properties', 'Premium homes and elite residences curated for excellence.'],
                ['fa-truck-fast', 'Smart Logistics', 'Uber-style moving truck system with live GPS tracking.'],
                ['fa-shield-halved', 'Secure Platform', 'Protected authentication and verified landlords.'],
                ['fa-bolt', 'Modern Experience', 'Fast, elegant, and mobile-first luxury platform.']
            ];

            foreach ($features as $feature):
            ?>

            <div class="lux-card home-feature-card">

                <div class="home-feature-icon-wrap">
                    <i class="fa-solid <?php echo $feature[0]; ?> lux-gold-icon"></i>
                </div>

                <h3 class="home-feature-title">
                    <?php echo $feature[1]; ?>
                </h3>

                <p class="home-feature-desc">
                    <?php echo $feature[2]; ?>
                </p>

            </div>

            <?php endforeach; ?>

        </div>

    </div>

</section>

<!-- CTA -->
<section class="home-cta-section">

    <div class="lux-card home-cta-card">

        <div class="home-cta-glow"></div>

        <div class="home-cta-inner">

            <h2 class="home-cta-title">
                Begin Your Luxury Journey
            </h2>

            <p class="home-cta-desc">
                Join the future of luxury property living,
                premium logistics, and elite real estate experiences.
            </p>

            <div class="home-cta-actions">
                <?php
                    $navMenuTriggerLabel = 'Join Now';
                    require __DIR__ . '/includes/nav_menu.php';
                ?>
            </div>

        </div>

    </div>

</section>

<script src="<?php echo BASE_URL; ?>/assets/js/nav-menu.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/home-typewriter.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/property-media.js"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>