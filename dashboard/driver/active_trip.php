<?php
require_once '../../includes/init.php';
require_once '../../includes/auth_check.php';
requireRoleAccess('driver');

require_once '../../config/db.php';

$db = new Database();
$pdo = $db->connect();

$driver_id = (int) Session::user()['id'];

$stmt = $pdo->prepare("
    SELECT
        truck_requests.*,
        users.full_name,
        users.phone
    FROM truck_requests
    JOIN users
    ON truck_requests.tenant_id = users.id
    WHERE truck_requests.driver_id = ?
    AND truck_requests.status IN (
        'accepted',
        'arrived_at_pickup',
        'in_transit',
        'cancelled'
    )
    LIMIT 1
");

$stmt->execute([$driver_id]);
$trip = $stmt->fetch();

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
require_once '../../includes/sidebar.php';
?>

<style>
body { overflow-x: hidden; }

@media (max-width: 992px) {
    main { margin-left: 0 !important; width: 100% !important; box-sizing: border-box; }
}

@media (max-width: 768px) {
    main { padding: 18px !important; }
    main h1 { font-size: 2rem !important; line-height: 1.3; }
    main div[style*="grid-template-columns"] { grid-template-columns: 1fr !important; }
    main div, main p, main span { word-break: break-word; }
    main div[style*="justify-content:space-between"] { flex-wrap: wrap !important; }
    main button { width: 100%; }
    .lux-card { border-radius: 18px !important; padding: 22px !important; }
}

@media (max-width: 480px) {
    main { padding: 14px !important; }
    main h1 { font-size: 1.7rem !important; }
}

#tripMap {
    width: 100%;
    height: 50vh;
    min-height: 320px;
    border-radius: 20px;
    margin-bottom: 25px;
}

.trip-nav-stats {
    display: flex;
    gap: 20px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.trip-nav-stat {
    background: rgba(255,255,255,0.05);
    padding: 14px 20px;
    border-radius: 14px;
    color: var(--gold);
    font-weight: bold;
}
</style>

<div style="display:flex; min-height:100vh;">
<main style="flex:1; padding:40px; margin-left:280px;">

    <div style="margin-bottom:45px;">
        <h1 style="font-family:'Cinzel', serif; color:var(--gold); font-size:3rem; margin-bottom:15px;">
            Active Logistics Trip
        </h1>
        <p style="color:var(--gray); line-height:1.9; max-width:700px;">
            Monitor and manage your current transport assignment inside the LUX EMPIRE logistics network.
        </p>
    </div>

    <?php if ($trip): ?>

        <?php
            $statusColors = [
                'accepted'          => ['bg' => 'rgba(255,165,0,0.15)', 'text' => 'orange'],
                'arrived_at_pickup' => ['bg' => 'rgba(66,165,245,0.15)', 'text' => '#42a5f5'],
                'in_transit'        => ['bg' => 'rgba(0,255,120,0.15)', 'text' => 'lightgreen'],
                'cancelled'         => ['bg' => 'rgba(255,59,59,0.15)', 'text' => '#ff5252'],
            ];
            $color = $statusColors[$trip['status']] ?? ['bg' => 'rgba(255,255,255,0.08)', 'text' => 'white'];
        ?>

        <div class="lux-card" style="padding:40px; border-radius:30px;">

            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:20px; margin-bottom:35px;">
                <div>
                    <h2 style="color:white; margin-bottom:10px;">Trip #<?php echo $trip['id']; ?></h2>
                    <div style="color:var(--gray);">Premium transport assignment</div>
                </div>

                <div style="background:<?php echo $color['bg']; ?>; color:<?php echo $color['text']; ?>; padding:14px 20px; border-radius:16px; font-weight:bold;">
                    <?php echo strtoupper(str_replace('_', ' ', $trip['status'])); ?>
                </div>
            </div>

            <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:30px; margin-bottom:35px;">
                <div>
                    <div style="color:var(--gray); margin-bottom:8px;">Tenant</div>
                    <div style="color:white; font-size:1.1rem;"><?php echo htmlspecialchars($trip['full_name']); ?></div>
                </div>
                <div>
                    <div style="color:var(--gray); margin-bottom:8px;">Contact</div>
                    <div style="color:white; font-size:1.1rem;"><?php echo htmlspecialchars($trip['phone']); ?></div>
                </div>
                <div>
                    <div style="color:var(--gray); margin-bottom:8px;">Trip Value</div>
                    <div style="color:var(--gold); font-size:1.3rem; font-weight:bold;">KES <?php echo number_format($trip['price']); ?></div>
                </div>
            </div>

            <div style="margin-bottom:35px;">
                <div style="margin-bottom:25px;">
                    <div style="color:var(--gray); margin-bottom:8px;">Pickup Location</div>
                    <div style="color:white;"><?php echo htmlspecialchars($trip['pickup_location']); ?></div>
                </div>
                <div>
                    <div style="color:var(--gray); margin-bottom:8px;">Destination</div>
                    <div style="color:white;"><?php echo htmlspecialchars($trip['destination']); ?></div>
                </div>
            </div>

            <?php if (in_array($trip['status'], ['accepted', 'in_transit'], true)): ?>

                <!-- LIVE NAVIGATION MAP -->
                <div class="trip-nav-stats">
                    <div class="trip-nav-stat">
                        <i class="fa-solid fa-clock"></i> ETA: <span id="tripEta">Calculating...</span>
                    </div>
                    <div class="trip-nav-stat">
                        <i class="fa-solid fa-road"></i> Distance: <span id="tripDistance">Calculating...</span>
                    </div>
                </div>

                <div id="tripMap"></div>

            <?php elseif ($trip['status'] === 'arrived_at_pickup'): ?>

                <div class="lux-card" style="padding:24px; border-radius:20px; margin-bottom:25px; background:rgba(66,165,245,0.08);">
                    <i class="fa-solid fa-circle-check" style="color:#42a5f5; margin-right:8px;"></i>
                    You've marked arrival at the pickup point. Tap Start Trip once the load is ready.
                </div>

            <?php endif; ?>

            <div style="display:flex; gap:20px; flex-wrap:wrap;">

                <?php if ($trip['status'] === 'accepted'): ?>

                    <form action="<?php echo BASE_URL; ?>/api/trucks/update_trip_status.php" method="POST">
                        <input type="hidden" name="trip_id" value="<?php echo $trip['id']; ?>">
                        <input type="hidden" name="status" value="arrived_at_pickup">
                        <button class="lux-btn" style="border:none; padding:16px 28px; border-radius:18px; cursor:pointer;">
                            <i class="fa-solid fa-location-dot"></i> Arrived at Pickup
                        </button>
                    </form>

                <?php elseif ($trip['status'] === 'arrived_at_pickup'): ?>

                    <form action="<?php echo BASE_URL; ?>/api/trucks/update_trip_status.php" method="POST">
                        <input type="hidden" name="trip_id" value="<?php echo $trip['id']; ?>">
                        <input type="hidden" name="status" value="in_transit">
                        <button class="lux-btn" style="border:none; padding:16px 28px; border-radius:18px; cursor:pointer;">
                            <i class="fa-solid fa-play"></i> Start Trip
                        </button>
                    </form>

                <?php elseif ($trip['status'] === 'in_transit'): ?>

                    <form action="<?php echo BASE_URL; ?>/api/trucks/update_trip_status.php" method="POST">
                        <input type="hidden" name="trip_id" value="<?php echo $trip['id']; ?>">
                        <input type="hidden" name="status" value="completed">
                        <button style="background:lightgreen; color:black; border:none; padding:16px 28px; border-radius:18px; cursor:pointer; font-weight:bold;">
                            <i class="fa-solid fa-flag-checkered"></i> Complete Trip
                        </button>
                    </form>

                <?php elseif ($trip['status'] === 'cancelled'): ?>

                    <div style="display:flex; gap:15px; align-items:center; flex-wrap:wrap;">
                        <div style="background:rgba(255,0,0,0.12); color:#ff6b6b; padding:18px 24px; border-radius:18px; font-weight:bold;">
                            Tenant cancelled this trip
                        </div>

                        <form action="<?php echo BASE_URL; ?>/api/trucks/delete_trip.php" method="POST">
                            <input type="hidden" name="trip_id" value="<?php echo $trip['id']; ?>">
                            <button type="submit" style="background:linear-gradient(135deg,#ff3b3b,#ff1744); color:white; border:none; padding:16px 24px; border-radius:18px; cursor:pointer; font-weight:bold;">
                                <i class="fa-solid fa-trash"></i> Delete Trip
                            </button>
                        </form>
                    </div>

                <?php endif; ?>

            </div>

        </div>

        <?php if (in_array($trip['status'], ['accepted', 'in_transit'], true)): ?>

            <script>
                window.LUX_TRIP_CONFIG = {
                    baseUrl: "<?php echo BASE_URL; ?>",
                    tripId: <?php echo (int) $trip['id']; ?>,
                    target: {
                        lat: <?php echo $trip['status'] === 'in_transit'
                            ? ($trip['destination_lat'] ?: '-1.286389')
                            : ($trip['pickup_lat'] ?: '-1.286389'); ?>,
                        lng: <?php echo $trip['status'] === 'in_transit'
                            ? ($trip['destination_lng'] ?: '36.817223')
                            : ($trip['pickup_lng'] ?: '36.817223'); ?>
                    }
                };
            </script>

            <script src="<?php echo BASE_URL; ?>/assets/js/active-trip-navigator.js"></script>

            <script
                async
                defer
                src="https://maps.googleapis.com/maps/api/js?key=<?php echo GOOGLE_MAPS_API_KEY; ?>&callback=initActiveTripMap">
            </script>

        <?php endif; ?>

    <?php else: ?>

        <div class="lux-card" style="padding:60px; border-radius:30px; text-align:center;">
            <h2 style="color:white; margin-bottom:15px;">No Active Trip</h2>
            <p style="color:var(--gray); line-height:1.9; max-width:550px; margin:auto;">
                You currently have no active transport assignment.
            </p>
        </div>

    <?php endif; ?>

</main>
</div>

<?php require_once '../../includes/footer.php'; ?>