<?php
require_once '../../includes/auth_check.php';
requireRoleAccess('tenant');

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
require_once '../../includes/sidebar.php';
?>

<style>

/* =========================================
   RESPONSIVE REQUEST TRUCK PAGE
========================================= */

.request-layout {
    display:flex;
    min-height:100vh;
}

.request-main {
    flex:1;
    padding:40px;
    margin-left:280px;
}

.request-grid {
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(340px,1fr));
    gap:35px;
    align-items:start;
}

.request-card {
    padding:35px;
    border-radius:28px;
}

.request-input {
    width:100%;
    padding:16px;
    border:none;
    border-radius:16px;
    background:rgba(255,255,255,0.05);
    color:white;
    outline:none;
    font-size:1rem;
}

.request-input::placeholder {
    color:#999;
}

.info-stack {
    display:flex;
    flex-direction:column;
    gap:25px;
}

/* =========================================
   MOBILE RESPONSIVE
========================================= */

@media (max-width: 768px) {

    .request-main {

        margin-left:0;
        padding:
            110px 18px 30px 18px;
    }

    .request-grid {

        grid-template-columns:1fr;
        gap:22px;
    }

    .request-card {

        padding:22px;
        border-radius:24px;
    }

    .request-main h1 {

        font-size:2rem !important;
        line-height:1.3;
    }

    .request-main p {

        font-size:0.95rem;
    }

    .request-input {

        font-size:16px;
    }
}

</style>

<div class="request-layout">

    <!-- MAIN -->
    <main class="request-main">

        <!-- HEADER -->
        <div style="margin-bottom:40px;">

            <h1 style="
                font-family:'Cinzel', serif;
                color:var(--gold);
                font-size:3rem;
                margin-bottom:15px;
            ">
                🚚 Luxury Moving Service
            </h1>

            <p style="
                color:var(--gray);
                max-width:700px;
                line-height:1.8;
            ">
                Request elite logistics and professional moving services
                powered by LUX EMPIRE intelligent transport system.
            </p>

        </div>

        <!-- GRID -->
        <div class="request-grid">

            <!-- FORM -->
            <div class="lux-card request-card">

                <h2 style="
                    color:white;
                    margin-bottom:25px;
                    font-size:1.8rem;
                ">
                    Request Truck
                </h2>

                <form
                    action="../../api/trucks/request_truck.php"
                    method="POST"
                >

                    <!-- PICKUP -->
                    <div style="margin-bottom:22px;">

                        <label style="
                            display:block;
                            margin-bottom:10px;
                            color:var(--gold);
                            font-weight:600;
                        ">
                            Pickup Location
                        </label>

                        <input
                            type="text"
                            name="pickup_location"
                            placeholder="Enter pickup location"
                            required
                            class="request-input"
                        >

                    </div>

                    <!-- DESTINATION -->
                    <div style="margin-bottom:22px;">

                        <label style="
                            display:block;
                            margin-bottom:10px;
                            color:var(--gold);
                            font-weight:600;
                        ">
                            Destination
                        </label>

                        <input
                            type="text"
                            name="destination"
                            placeholder="Enter destination"
                            required
                            class="request-input"
                        >

                    </div>

                    <!-- PRICE -->
                    <div style="margin-bottom:30px;">

                        <label style="
                            display:block;
                            margin-bottom:10px;
                            color:var(--gold);
                            font-weight:600;
                        ">
                            Estimated Price (KES)
                        </label>

                        <input
                            type="number"
                            name="price"
                            placeholder="Estimated transport cost"
                            required
                            class="request-input"
                        >

                    </div>

                    <!-- HIDDEN GPS -->
                    <input type="hidden" name="pickup_lat">
                    <input type="hidden" name="pickup_lng">
                    <input type="hidden" name="destination_lat">
                    <input type="hidden" name="destination_lng">

                    <!-- BUTTON -->
                    <button
                        type="submit"
                        class="lux-btn"
                        style="
                            width:100%;
                            padding:18px;
                            border:none;
                            border-radius:18px;
                            cursor:pointer;
                            font-size:1rem;
                        "
                    >
                        🚚 Request Luxury Truck
                    </button>

                </form>

            </div>

            <!-- INFO PANEL -->
            <div class="info-stack">

                <!-- SERVICE CARD -->
                <div class="lux-card request-card">

                    <h2 style="
                        color:var(--gold);
                        margin-bottom:20px;
                    ">
                        Why Use Our Logistics?
                    </h2>

                    <div style="
                        display:flex;
                        flex-direction:column;
                        gap:18px;
                    ">

                        <?php
                        $benefits = [

                            "Professional verified drivers",
                            "Real-time GPS tracking",
                            "Fast property relocation",
                            "Secure and reliable transport",
                            "Mobile live updates"
                        ];

                        foreach ($benefits as $benefit):
                        ?>

                        <div style="
                            background:rgba(255,255,255,0.04);
                            padding:15px;
                            border-radius:14px;
                            color:#ddd;
                        ">
                            <?php echo $benefit; ?>
                        </div>

                        <?php endforeach; ?>

                    </div>

                </div>

                <!-- STATUS CARD -->
                <div class="lux-card request-card">

                    <h2 style="
                        color:white;
                        margin-bottom:20px;
                    ">
                        Smart Logistics
                    </h2>

                    <p style="
                        color:var(--gray);
                        line-height:1.9;
                    ">
                        LUX EMPIRE integrates modern logistics technology
                        allowing tenants to request moving services,
                        track truck movement in real-time,
                        and manage relocation seamlessly.
                    </p>

                </div>

            </div>

        </div>

    </main>

</div>

<?php require_once '../../includes/footer.php'; ?>