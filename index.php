<?php


require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<!-- HERO SECTION -->
<section style="
    min-height:100vh;
    display:flex;
    align-items:center;
    justify-content:center;
    position:relative;
    overflow:hidden;
    padding:120px 20px 80px;
">

    <!-- BACKGROUND GLOW -->
    <div style="
        position:absolute;
        width:700px;
        height:700px;
        background:radial-gradient(circle, rgba(212,175,55,0.18), transparent 70%);
        top:-250px;
        right:-250px;
        z-index:0;
        filter:blur(40px);
    "></div>

    <div style="
        position:absolute;
        width:600px;
        height:600px;
        background:radial-gradient(circle, rgba(255,255,255,0.04), transparent 70%);
        bottom:-200px;
        left:-200px;
        z-index:0;
        filter:blur(40px);
    "></div>

    <!-- CONTENT -->
    <div style="
        max-width:1300px;
        width:100%;
        display:grid;
        grid-template-columns:repeat(auto-fit,minmax(320px,1fr));
        gap:60px;
        align-items:center;
        position:relative;
        z-index:2;
    ">

        <!-- LEFT -->
        <div>

            <span style="
                display:inline-block;
                padding:10px 18px;
                border-radius:50px;
                background:rgba(255,255,255,0.06);
                border:1px solid rgba(255,255,255,0.08);
                color:var(--gold);
                margin-bottom:25px;
                font-size:0.95rem;
                backdrop-filter:blur(12px);
            ">
                👑 Welcome to LUX EMPIRE
            </span>

            <h1 style="
                font-family:'Cinzel', serif;
                font-size:clamp(3rem,7vw,6rem);
                line-height:1.1;
                color:white;
                margin-bottom:25px;
                font-weight:800;
            ">
                Where <span style="
                    color:var(--gold);
                    text-shadow:0 0 20px rgba(212,175,55,0.4);
                ">Luxury</span><br>
                Finds Home.
            </h1>

            <p style="
                color:var(--gray);
                font-size:1.1rem;
                line-height:1.9;
                max-width:650px;
                margin-bottom:35px;
            ">
                Experience elite property living, premium rentals,
                and intelligent logistics powered by modern luxury technology.
                LUX EMPIRE connects tenants, landlords, and moving services
                in one powerful ecosystem.
            </p>

            <!-- BUTTONS -->
            <div style="
                display:flex;
                gap:18px;
                flex-wrap:wrap;
            ">

                <a href="auth/register.php"
                   class="lux-btn"
                   style="
                        text-decoration:none;
                        padding:18px 34px;
                        border-radius:18px;
                   ">
                    Join The Empire
                </a>

                <a href="auth/login.php"
                   style="
                        text-decoration:none;
                        padding:18px 34px;
                        border-radius:18px;
                        border:1px solid rgba(255,255,255,0.12);
                        background:rgba(255,255,255,0.05);
                        color:white;
                        font-weight:600;
                        backdrop-filter:blur(12px);
                        transition:0.3s;
                   ">
                    🔑 Login
                </a>

            </div>

        </div>

        <!-- RIGHT -->
        <div style="
            position:relative;
            display:flex;
            justify-content:center;
            align-items:center;
        ">

            <!-- MAIN CARD -->
            <div class="lux-card" style="
                width:100%;
                max-width:520px;
                border-radius:35px;
                overflow:hidden;
                position:relative;
            ">

                <img src="assets/images/houses/luxury-house.jpg"
                     alt="Luxury House"
                     style="
                        width:100%;
                        height:500px;
                        object-fit:cover;
                     ">

                <!-- OVERLAY -->
                <div style="
                    position:absolute;
                    inset:0;
                    background:linear-gradient(
                        to top,
                        rgba(0,0,0,0.85),
                        rgba(0,0,0,0.15)
                    );
                "></div>

                <!-- CONTENT -->
                <div style="
                    position:absolute;
                    bottom:0;
                    left:0;
                    width:100%;
                    padding:30px;
                ">

                    <div style="
                        display:flex;
                        justify-content:space-between;
                        align-items:center;
                        margin-bottom:15px;
                    ">

                        <span style="
                            background:rgba(212,175,55,0.15);
                            color:var(--gold);
                            padding:8px 14px;
                            border-radius:12px;
                            font-size:0.9rem;
                            border:1px solid rgba(212,175,55,0.25);
                        ">
                            Premium Villa
                        </span>

                        <span style="
                            color:white;
                            font-weight:bold;
                        ">
                            Nairobi
                        </span>

                    </div>

                    <h2 style="
                        color:white;
                        font-size:2rem;
                        margin-bottom:10px;
                    ">
                        Modern Elite Residence
                    </h2>

                    <p style="
                        color:#ddd;
                        line-height:1.7;
                        margin-bottom:20px;
                    ">
                        Sophisticated architecture designed for luxury living and elite comfort.
                    </p>

                    <div style="
                        display:flex;
                        justify-content:space-between;
                        align-items:center;
                    ">

                        <div>
                            <small style="color:var(--gray);">
                                Starting From
                            </small>

                            <h3 style="
                                color:var(--gold);
                                margin-top:5px;
                                font-size:1.7rem;
                            ">
                                KES 120,000
                            </h3>
                        </div>

                        <a href="auth/register.php"
                           class="lux-btn"
                           style="
                                text-decoration:none;
                                padding:14px 22px;
                                border-radius:14px;
                           ">
                            View
                        </a>

                    </div>

                </div>

            </div>

        </div>

    </div>

</section>

<!-- STATS -->
<section style="
    padding:60px 20px;
">

    <div style="
        max-width:1300px;
        margin:auto;
        display:grid;
        grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
        gap:25px;
    ">

        <?php
        $stats = [
            ['15K+', 'Luxury Properties'],
            ['2300+', 'Verified Landlords'],
            ['98%', 'Client Satisfaction'],
            ['24/7', 'Premium Support']
        ];

        foreach ($stats as $stat):
        ?>

        <div class="lux-card" style="
            padding:35px;
            text-align:center;
            border-radius:24px;
        ">

            <h2 style="
                color:var(--gold);
                font-size:2.5rem;
                margin-bottom:12px;
            ">
                <?php echo $stat[0]; ?>
            </h2>

            <p style="
                color:var(--gray);
                font-size:1rem;
            ">
                <?php echo $stat[1]; ?>
            </p>

        </div>

        <?php endforeach; ?>

    </div>

</section>

<!-- WHY CHOOSE US -->
<section style="
    padding:90px 20px;
">

    <div style="
        max-width:1300px;
        margin:auto;
    ">

        <div style="
            text-align:center;
            margin-bottom:70px;
        ">

            <h2 style="
                font-family:'Cinzel', serif;
                color:white;
                font-size:3rem;
                margin-bottom:20px;
            ">
                Why Choose <span style="color:var(--gold);">LUX EMPIRE</span>
            </h2>

            <p style="
                color:var(--gray);
                max-width:700px;
                margin:auto;
                line-height:1.9;
            ">
                Built for modern luxury living with intelligent technology,
                elegant experiences, and premium logistics.
            </p>

        </div>

        <div style="
            display:grid;
            grid-template-columns:repeat(auto-fit,minmax(280px,1fr));
            gap:30px;
        ">

            <?php
            $features = [
                ['🏛', 'Luxury Properties', 'Premium homes and elite residences curated for excellence.'],
                ['🚚', 'Smart Logistics', 'Uber-style moving truck system with live GPS tracking.'],
                ['🔒', 'Secure Platform', 'Protected authentication and verified landlords.'],
                ['⚡', 'Modern Experience', 'Fast, elegant, and mobile-first luxury platform.']
            ];

            foreach ($features as $feature):
            ?>

            <div class="lux-card" style="
                padding:40px 30px;
                border-radius:28px;
            ">

                <div style="
                    font-size:3rem;
                    margin-bottom:20px;
                ">
                    <?php echo $feature[0]; ?>
                </div>

                <h3 style="
                    color:white;
                    margin-bottom:15px;
                    font-size:1.5rem;
                ">
                    <?php echo $feature[1]; ?>
                </h3>

                <p style="
                    color:var(--gray);
                    line-height:1.8;
                ">
                    <?php echo $feature[2]; ?>
                </p>

            </div>

            <?php endforeach; ?>

        </div>

    </div>

</section>

<!-- CTA -->
<section style="
    padding:100px 20px 120px;
">

    <div class="lux-card" style="
        max-width:1200px;
        margin:auto;
        padding:70px 40px;
        border-radius:40px;
        text-align:center;
        position:relative;
        overflow:hidden;
    ">

        <div style="
            position:absolute;
            width:500px;
            height:500px;
            background:radial-gradient(circle, rgba(212,175,55,0.12), transparent 70%);
            top:-200px;
            right:-200px;
        "></div>

        <div style="position:relative; z-index:2;">

            <h2 style="
                font-family:'Cinzel', serif;
                font-size:clamp(2.5rem,5vw,4.5rem);
                color:white;
                margin-bottom:25px;
            ">
                Begin Your Luxury Journey
            </h2>

            <p style="
                color:var(--gray);
                max-width:750px;
                margin:auto;
                line-height:1.9;
                margin-bottom:40px;
                font-size:1.1rem;
            ">
                Join the future of luxury property living,
                premium logistics, and elite real estate experiences.
            </p>

            <div style="
                display:flex;
                justify-content:center;
                gap:20px;
                flex-wrap:wrap;
            ">

                <a href="auth/register.php"
                   class="lux-btn"
                   style="
                        text-decoration:none;
                        padding:18px 34px;
                        border-radius:18px;
                   ">
                    👑 Create Account
                </a>

                <a href="auth/login.php"
                   style="
                        text-decoration:none;
                        padding:18px 34px;
                        border-radius:18px;
                        border:1px solid rgba(255,255,255,0.12);
                        background:rgba(255,255,255,0.05);
                        color:white;
                        font-weight:600;
                   ">
                    🔑 Access Platform
                </a>

            </div>

        </div>

    </div>

</section>

<?php require_once 'includes/footer.php'; ?>