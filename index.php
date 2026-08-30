<?php


require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
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

                <img src="assets/images/houses/luxury-house.jpg"
                     alt="Luxury House"
                     class="home-hero-img">

                <!-- OVERLAY -->
                <div class="home-hero-card-overlay"></div>

                <!-- CONTENT -->
                <div class="home-hero-card-content">

                    <div class="home-hero-card-top">

                        <span class="home-hero-card-tag">
                            Premium Villa
                        </span>

                        <span class="home-hero-card-location">
                            Nairobi
                        </span>

                    </div>

                    <h2 class="home-hero-card-title">
                        Modern Elite Residence
                    </h2>

                    <p class="home-hero-card-desc">
                        Sophisticated architecture designed for luxury living and elite comfort.
                    </p>

                    <div class="home-hero-card-bottom">

                        <div>
                            <small class="home-hero-card-price-label">
                                Starting From
                            </small>

                            <h3 class="home-hero-card-price">
                                KES 120,000
                            </h3>
                        </div>

                        <!--
                            This stays a plain link (not the nav menu) —
                            it's a content CTA for this one property
                            preview, not primary site navigation.
                        -->
                        <a href="<?php echo BASE_URL; ?>/browse"
                           class="lux-btn home-hero-card-view-btn">
                            View
                        </a>

                    </div>

                </div>

            </div>

        </div>

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

<?php require_once __DIR__ . '/includes/footer.php'; ?>