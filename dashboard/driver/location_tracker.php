<?php

require_once '../../includes/init.php';
require_once '../../includes/auth_check.php';
requireRoleAccess('driver');

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
require_once '../../includes/sidebar.php';
?>


<style>

/* ================================
   MOBILE + TABLET RESPONSIVE ONLY
   (DO NOT TOUCH DESKTOP)
================================ */

/* TABLET + BELOW */
@media (max-width: 992px) {

    main {
        margin-left: 0 !important;
        width: 100% !important;
        padding: 25px !important;
    }
}

/* MOBILE */
@media (max-width: 768px) {

    main {
        padding: 18px !important;
    }

    /* HERO TEXT */
    h1 {
        font-size: 2.2rem !important;
        line-height: 1.3 !important;
    }

    /* STATUS CARDS */
    .lux-card {
        padding: 22px !important;
    }

    .lux-card h2 {
        font-size: 1.2rem !important;
    }

    /* GRID STACK FIX */
    div[style*="grid-template-columns"] {
        grid-template-columns: 1fr !important;
        gap: 18px !important;
    }

    /* MAP */
    #map {
        height: 60vh !important;
        min-height: 320px !important;
        border-radius: 18px !important;
    }

    /* HEADER FLEX WRAP FIX */
    div[style*="justify-content:space-between"] {
        flex-direction: column !important;
        align-items: flex-start !important;
    }

}

/* SMALL MOBILE */
@media (max-width: 480px) {

    main {
        padding: 14px !important;
    }

    h1 {
        font-size: 1.8rem !important;
    }

    p {
        font-size: 0.95rem !important;
    }

    .lux-card {
        padding: 18px !important;
    }

    #map {
        height: 55vh !important;
        min-height: 280px !important;
    }
}

</style>

<div style="
    display:flex;
    min-height:100vh;
">

<!-- MAIN -->
<main style="
    flex:1;
    padding:40px;
    margin-left:280px;
    width:calc(100% - 280px);
">

    <!-- HERO -->
    <div style="margin-bottom:45px;">

        <h1 style="
            font-family:'Cinzel', serif;
            color:var(--gold);
            font-size:3rem;
            margin-bottom:15px;
        ">
            <i class="fa-solid fa-location-dot"></i> Live Driver Tracker
        </h1>

        <p style="
            color:var(--gray);
            line-height:1.9;
            max-width:760px;
        ">
            Your real-time GPS location is securely shared
            with the LUX EMPIRE logistics network to support
            premium transport tracking and live delivery monitoring.
        </p>

    </div>

    <!-- STATUS GRID -->
    <div style="
        display:grid;
        grid-template-columns:repeat(auto-fit,minmax(250px,1fr));
        gap:25px;
        margin-bottom:40px;
    ">

        <!-- TRACKING STATUS -->
        <div class="lux-card" style="
            padding:30px;
            border-radius:28px;
            text-align:center;
        ">

            <div style="
                font-size:3rem;
                margin-bottom:15px;
            ">
                <i class="fa-solid fa-satellite-dish"></i>
            </div>

            <h2 style="
                color:white;
                margin-bottom:10px;
            ">
                Tracking Status
            </h2>

            <div
                id="trackingStatus"
                style="
                    color:orange;
                    font-weight:bold;
                    font-size:1.1rem;
                "
            >
                Initializing...
            </div>

        </div>

        <!-- LATITUDE -->
        <div class="lux-card" style="
            padding:30px;
            border-radius:28px;
            text-align:center;
        ">

            <div style="
                font-size:3rem;
                margin-bottom:15px;
            ">
                <i class="fa-solid fa-earth-africa"></i>
            </div>

            <h2 style="
                color:white;
                margin-bottom:10px;
            ">
                Latitude
            </h2>

            <div
                id="latitude"
                style="
                    color:var(--gold);
                    font-weight:bold;
                    font-size:1.2rem;
                "
            >
                --
            </div>

        </div>

        <!-- LONGITUDE -->
        <div class="lux-card" style="
            padding:30px;
            border-radius:28px;
            text-align:center;
        ">

            <div style="
                font-size:3rem;
                margin-bottom:15px;
            ">
                <i class="fa-solid fa-tower-broadcast"></i>
            </div>

            <h2 style="
                color:white;
                margin-bottom:10px;
            ">
                Longitude
            </h2>

            <div
                id="longitude"
                style="
                    color:var(--gold);
                    font-weight:bold;
                    font-size:1.2rem;
                "
            >
                --
            </div>

        </div>

    </div>

    <!-- MAP CONTAINER -->
    <div class="lux-card" style="
        padding:30px;
        border-radius:30px;
    ">

        <div style="
            display:flex;
            justify-content:space-between;
            align-items:center;
            flex-wrap:wrap;
            gap:20px;
            margin-bottom:25px;
        ">

            <div>

                <h2 style="
                    color:white;
                    margin-bottom:10px;
                ">
                    Live GPS Monitoring
                </h2>

                <p style="
                    color:var(--gray);
                    line-height:1.8;
                ">
                    Your location updates automatically
                    while transport operations are active.
                </p>

            </div>

            <!-- LIVE BADGE -->
            <div style="
                background:rgba(0,255,120,0.15);
                color:lightgreen;
                padding:12px 18px;
                border-radius:14px;
                font-weight:bold;
            ">
                ● LIVE TRACKING
            </div>

        </div>

        <!-- MAP -->
        <div
            id="map"
            style="
                width:100%;

                overflow:hidden;
                border:1px solid rgba(255,215,0,0.15);

                height: 60vh;
                min-height: 400px;
                max-height: 700px;
                border-radius: 20px;
            
            "
        >

            <div style="
                height:100%;
                display:flex;
                align-items:center;
                justify-content:center;
                color:var(--gray);
                font-size:1.1rem;
            ">
                Waiting for GPS initialization...
            </div>

        </div>

    </div>

</main>

</div>

<!-- MAPS JS -->
<script src="../../assets/js/maps.js"></script>

<script
    async
    defer
    src="https://maps.googleapis.com/maps/api/js?key=<?php echo GOOGLE_MAPS_API_KEY; ?>&callback=initializeTracker">
</script>

<?php require_once '../../includes/footer.php'; ?>