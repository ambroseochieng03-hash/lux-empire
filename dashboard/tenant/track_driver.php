<?php

require_once '../../includes/init.php';
require_once '../../includes/auth_check.php';
requireRoleAccess('tenant');

require_once '../../config/db.php';

$db = new Database();
$pdo = $db->connect();

$tenant_id = $_SESSION['user_id'];

/*
|--------------------------------------------------------------------------
| FETCH ACTIVE TRIP
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT
        tr.*,
        u.full_name AS driver_name,
        u.phone AS driver_phone

    FROM truck_requests tr

    LEFT JOIN users u
    ON tr.driver_id = u.id

    WHERE tr.tenant_id = ?
    AND tr.status IN (
        'accepted',
        'in_transit'
    )

    ORDER BY tr.id DESC
    LIMIT 1
");

$stmt->execute([$tenant_id]);

$trip = $stmt->fetch();

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
require_once '../../includes/sidebar.php';
?>

<style>
/* =========================================
   MOBILE RESPONSIVENESS ONLY
========================================= */

.track-page {
    display: flex;
    min-height: 100vh;
    background: #0a0a0a;
}

.track-main {
    flex: 1;
    padding: 25px;
    margin-left: 280px;
    width: calc(100% - 280px);
    overflow-x: hidden;
}

.track-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px,1fr));
    gap: 20px;
    margin-bottom: 25px;
}

.route-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

#map {
    width: 100%;
    height: 75vh;
    border-radius: 25px;
    overflow: hidden;
}

/* =========================================
   TABLETS
========================================= */
@media (max-width: 992px) {

    .track-main {
        padding: 20px;
    }

    #map {
        height: 65vh;
    }
}

/* =========================================
   PHONES
========================================= */
@media (max-width: 768px) {

    .track-main {
        margin-left: 0;
        width: 100%;
        padding: 95px 15px 25px;
    }

    .track-grid {
        grid-template-columns: 1fr;
    }

    .route-grid {
        grid-template-columns: 1fr;
    }

    #map {
        height: 55vh;
        border-radius: 18px;
    }

    .track-title {
        font-size: 2rem !important;
        line-height: 1.3;
    }

    .lux-card {
        padding: 20px !important;
        border-radius: 22px !important;
    }

    .cancel-btn {
        width: 100%;
    }
}

/* =========================================
   SMALL PHONES
========================================= */
@media (max-width: 480px) {

    .track-main {
        padding: 90px 12px 20px;
    }

    .track-title {
        font-size: 1.7rem !important;
    }

    #map {
        height: 50vh;
    }
}
</style>

<div class="track-page">

<main class="track-main">

<?php if ($trip): ?>

<!-- HEADER -->
<div style="margin-bottom:30px;">

    <h1 class="track-title" style="
        color:var(--gold);
        font-size:2.8rem;
        margin-bottom:12px;
        font-family:'Cinzel', serif;
    ">
        Live Driver Tracking
    </h1>

    <p style="
        color:var(--gray);
        line-height:1.8;
        max-width:800px;
    ">
        Track your assigned driver in real time,
        monitor arrival progress,
        ETA and live route navigation.
    </p>

</div>

<!-- INFO GRID -->
<div class="track-grid">

    <!-- DRIVER -->
    <div class="lux-card" style="
        padding:25px;
        border-radius:22px;
    ">

        <div style="
            color:var(--gray);
            margin-bottom:10px;
        ">
            Driver
        </div>

        <div style="
            color:white;
            font-size:1.2rem;
            font-weight:bold;
            margin-bottom:8px;
            word-break:break-word;
        ">
            <?php echo htmlspecialchars($trip['driver_name'] ?? 'Not Assigned'); ?>
        </div>

        <div style="
            color:var(--gold);
            word-break:break-word;
        ">
            📞 <?php echo htmlspecialchars($trip['driver_phone'] ?? 'N/A'); ?>
        </div>

    </div>

    <!-- STATUS -->
    <div class="lux-card" style="
        padding:25px;
        border-radius:22px;
    ">

        <div style="
            color:var(--gray);
            margin-bottom:10px;
        ">
            Trip Status
        </div>

        <div id="tripStatus" style="
            color:lightgreen;
            font-size:1.3rem;
            font-weight:bold;
            word-break:break-word;
        ">
            <?php echo strtoupper($trip['status']); ?>
        </div>

    </div>

    <!-- ETA -->
    <div class="lux-card" style="
        padding:25px;
        border-radius:22px;
    ">

        <div style="
            color:var(--gray);
            margin-bottom:10px;
        ">
            Estimated Arrival
        </div>

        <div id="eta" style="
            color:var(--gold);
            font-size:1.5rem;
            font-weight:bold;
        ">
            Calculating...
        </div>

    </div>

    <!-- DISTANCE -->
    <div class="lux-card" style="
        padding:25px;
        border-radius:22px;
    ">

        <div style="
            color:var(--gray);
            margin-bottom:10px;
        ">
            Remaining Distance
        </div>

        <div id="distance" style="
            color:white;
            font-size:1.5rem;
            font-weight:bold;
        ">
            Calculating...
        </div>

    </div>

</div>

<!-- ROUTE -->
<div class="lux-card" style="
    padding:25px;
    border-radius:24px;
    margin-bottom:25px;
">

    <div class="route-grid">

        <!-- PICKUP -->
        <div>

            <div style="
                color:var(--gray);
                margin-bottom:10px;
            ">
                Pickup
            </div>

            <div style="
                color:white;
                font-size:1.05rem;
                line-height:1.7;
                word-break:break-word;
            ">
                <?php echo htmlspecialchars($trip['pickup_location']); ?>
            </div>

        </div>

        <!-- DESTINATION -->
        <div>

            <div style="
                color:var(--gray);
                margin-bottom:10px;
            ">
                Destination
            </div>

            <div style="
                color:white;
                font-size:1.05rem;
                line-height:1.7;
                word-break:break-word;
            ">
                <?php echo htmlspecialchars($trip['destination']); ?>
            </div>

        </div>

    </div>

</div>

<?php
if (
    $trip['status'] === 'pending'
    ||
    $trip['status'] === 'accepted'
):
?>

<form
    action="../../api/trucks/cancel_trip.php"
    method="POST"
    onsubmit="
        return confirm(
            'Cancel this trip request?'
        );
    "
    style="margin-bottom:25px;"
>

    <input
        type="hidden"
        name="trip_id"
        value="<?php echo $trip['id']; ?>"
    >

    <button
        class="cancel-btn"
        style="
            background:#ff3b3b;
            color:white;
            border:none;
            padding:16px 28px;
            border-radius:18px;
            cursor:pointer;
            font-weight:bold;
            font-size:1rem;
        "
    >
        Cancel Trip
    </button>

</form>

<?php endif; ?>

<!-- MAP -->
<div class="lux-card" style="
    padding:20px;
    border-radius:30px;
">

    <div id="map"></div>

</div>

<?php else: ?>

<div class="lux-card" style="
    padding:60px;
    border-radius:30px;
    text-align:center;
">

    <div style="
        font-size:5rem;
        margin-bottom:20px;
    ">
    </div>

    <h2 style="
        color:white;
        margin-bottom:15px;
    ">
        No Active Driver
    </h2>

    <p style="
        color:var(--gray);
        max-width:600px;
        margin:auto;
        line-height:1.8;
    ">
        Once a driver accepts your request,
        live tracking will appear here automatically.
    </p>

</div>

<?php endif; ?>

</main>

</div>

<?php if ($trip): ?>

<script>

const DRIVER_ID = <?php echo (int)$trip['driver_id']; ?>;

const PICKUP = {
    lat: <?php echo $trip['pickup_lat'] ?: '-1.286389'; ?>,
    lng: <?php echo $trip['pickup_lng'] ?: '36.817223'; ?>
};

const DESTINATION = {
    lat: <?php echo $trip['destination_lat'] ?: '-1.286389'; ?>,
    lng: <?php echo $trip['destination_lng'] ?: '36.817223'; ?>
};

const TRIP_STATUS = "<?php echo $trip['status']; ?>";

let map;
let driverMarker;
let directionsService;
let directionsRenderer;

function initMap()
{
    map = new google.maps.Map(
        document.getElementById("map"),
        {
            zoom: 14,
            center: PICKUP,
            mapTypeControl:false,
            streetViewControl:false,
            fullscreenControl:true
        }
    );

    directionsService = new google.maps.DirectionsService();

    directionsRenderer = new google.maps.DirectionsRenderer({
        suppressMarkers:false,
        polylineOptions:{
            strokeColor:"#4285F4",
            strokeOpacity:1,
            strokeWeight:6
        }
    });

    directionsRenderer.setMap(map);

    driverMarker = new google.maps.Marker({
        map: map,
        title: "Driver",
        icon: {
            url: "https://maps.google.com/mapfiles/ms/icons/blue-dot.png"
        }
    });

    fetchDriverLocation();

    setInterval(fetchDriverLocation, 1500);
}

/*
|--------------------------------------------------------------------------
| FETCH DRIVER LOCATION
|--------------------------------------------------------------------------
*/
function fetchDriverLocation()
{
    fetch(
        "../../api/maps/get_driver_location.php?driver_id="
        + DRIVER_ID
    )

    .then(response => response.json())

    .then(data => {

        if (!data.success) return;

        const lat = parseFloat(data.location.latitude);
        const lng = parseFloat(data.location.longitude);

        const driverPos = {
            lat: lat,
            lng: lng
        };

        driverMarker.setPosition(driverPos);

        updateRoute(driverPos);

    })

    .catch(error => {
        console.error(error);
    });
}

/*
|--------------------------------------------------------------------------
| UPDATE ROUTE
|--------------------------------------------------------------------------
*/
function updateRoute(driverPos)
{
    let destination;

    if (TRIP_STATUS === "accepted") {

        destination = PICKUP;

    } else {

        destination = DESTINATION;
    }

    directionsService.route({

        origin: driverPos,

        destination: destination,

        travelMode: google.maps.TravelMode.DRIVING

    },

    function(result, status) {

        if (status === "OK") {

            directionsRenderer.setDirections(result);

            const leg = result.routes[0].legs[0];

            document.getElementById("eta").innerHTML =
                leg.duration.text;

            document.getElementById("distance").innerHTML =
                leg.distance.text;

            autoZoom(driverPos, destination);
        }
    });
}

/*
|--------------------------------------------------------------------------
| AUTO ZOOM
|--------------------------------------------------------------------------
*/
function autoZoom(driverPos, destination)
{
    const bounds = new google.maps.LatLngBounds();

    bounds.extend(driverPos);
    bounds.extend(destination);

    map.fitBounds(bounds);
}

window.initMap = initMap;

</script>

<script
    async
    defer
    src="https://maps.googleapis.com/maps/api/js?key=<?php echo GOOGLE_MAPS_API_KEY; ?>&callback=initMap"
></script>

<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>