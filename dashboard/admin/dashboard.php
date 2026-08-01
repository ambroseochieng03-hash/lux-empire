<?php

require_once '../../includes/auth_check.php';
requireRoleAccess('admin');

require_once '../../config/db.php';

$db = new Database();
$pdo = $db->connect();

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
require_once '../../includes/sidebar.php';

// =====================================
// COUNTS
// =====================================

// Total users
$totalUsers = $pdo->query("
    SELECT COUNT(*) FROM users
")->fetchColumn();

// Total houses
$totalHouses = $pdo->query("
    SELECT COUNT(*) FROM houses
")->fetchColumn();

// Total truck requests
$totalTruckRequests = $pdo->query("
    SELECT COUNT(*) FROM truck_requests
")->fetchColumn();

// Completed trips
$completedTrips = $pdo->query("
    SELECT COUNT(*)
    FROM truck_requests
    WHERE status = 'completed'
")->fetchColumn();

// Drivers
$totalDrivers = $pdo->query("
    SELECT COUNT(*)
    FROM users
    WHERE role = 'driver'
")->fetchColumn();

// Tenants
$totalTenants = $pdo->query("
    SELECT COUNT(*)
    FROM users
    WHERE role = 'tenant'
")->fetchColumn();

// Landlords
$totalLandlords = $pdo->query("
    SELECT COUNT(*)
    FROM users
    WHERE role = 'landlord'
")->fetchColumn();

// =====================================
// RECENT USERS
// =====================================

$recentUsersStmt = $pdo->query("
    SELECT full_name, email, role, created_at
    FROM users
    ORDER BY created_at DESC
    LIMIT 5
");

$recentUsers = $recentUsersStmt->fetchAll();

// =====================================
// RECENT TRIPS
// =====================================

$recentTripsStmt = $pdo->query("
    SELECT
        truck_requests.id,
        truck_requests.pickup_location,
        truck_requests.destination,
        truck_requests.status,
        truck_requests.requested_at,

        users.full_name

    FROM truck_requests

    JOIN users
    ON truck_requests.tenant_id = users.id

    ORDER BY truck_requests.requested_at DESC
    LIMIT 5
");

$recentTrips = $recentTripsStmt->fetchAll();

?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/auth.css">

<div class="lux-dashboard-layout">

<main class="lux-dashboard-main">

    <!-- HERO -->
    <div class="lux-hero">

        <h1 class="lux-title">
            👑 LUX EMPIRE Control Center
        </h1>

        <p class="lux-subtitle">
            Welcome to the executive administration
            environment of LUX EMPIRE.
            Monitor users, logistics operations,
            properties, bookings, and platform activity
            in real time.
        </p>

    </div>

    <!-- STATS -->
    <div style="
        display:grid;
        grid-template-columns:repeat(auto-fit,minmax(240px,1fr));
        gap:25px;
        margin-bottom:45px;
    ">

        <!-- USERS -->
        <div class="lux-card" style="
            padding:30px;
            border-radius:28px;
        ">
            <h3 style="color:var(--gray);">
                Total Users
            </h3>

            <div style="
                color:white;
                font-size:2.5rem;
                margin-top:15px;
                font-weight:bold;
            ">
                <?= $totalUsers ?>
            </div>
        </div>

        <!-- HOUSES -->
        <div class="lux-card" style="
            padding:30px;
            border-radius:28px;
        ">
            <h3 style="color:var(--gray);">
                Properties
            </h3>

            <div style="
                color:white;
                font-size:2.5rem;
                margin-top:15px;
                font-weight:bold;
            ">
                <?= $totalHouses ?>
            </div>
        </div>

        <!-- REQUESTS -->
        <div class="lux-card" style="
            padding:30px;
            border-radius:28px;
        ">
            <h3 style="color:var(--gray);">
                Truck Requests
            </h3>

            <div style="
                color:white;
                font-size:2.5rem;
                margin-top:15px;
                font-weight:bold;
            ">
                <?= $totalTruckRequests ?>
            </div>
        </div>

        <!-- COMPLETED -->
        <div class="lux-card" style="
            padding:30px;
            border-radius:28px;
        ">
            <h3 style="color:var(--gray);">
                Completed Trips
            </h3>

            <div style="
                color:white;
                font-size:2.5rem;
                margin-top:15px;
                font-weight:bold;
            ">
                <?= $completedTrips ?>
            </div>
        </div>

    </div>

    <!-- SECOND GRID -->
    <div style="
        display:grid;
        grid-template-columns:repeat(auto-fit,minmax(250px,1fr));
        gap:25px;
        margin-bottom:45px;
    ">

        <!-- TENANTS -->
        <div class="lux-card" style="
            padding:25px;
            border-radius:24px;
        ">
            <h3 style="color:var(--gray);">
                Tenants
            </h3>

            <div style="
                color:gold;
                font-size:2rem;
                margin-top:12px;
                font-weight:bold;
            ">
                <?= $totalTenants ?>
            </div>
        </div>

        <!-- LANDLORDS -->
        <div class="lux-card" style="
            padding:25px;
            border-radius:24px;
        ">
            <h3 style="color:var(--gray);">
                Landlords
            </h3>

            <div style="
                color:gold;
                font-size:2rem;
                margin-top:12px;
                font-weight:bold;
            ">
                <?= $totalLandlords ?>
            </div>
        </div>

        <!-- DRIVERS -->
        <div class="lux-card" style="
            padding:25px;
            border-radius:24px;
        ">
            <h3 style="color:var(--gray);">
                Drivers
            </h3>

            <div style="
                color:gold;
                font-size:2rem;
                margin-top:12px;
                font-weight:bold;
            ">
                <?= $totalDrivers ?>
            </div>
        </div>

    </div>

    <!-- RECENT ACTIVITY -->
    <div class="lux-recent-grid">

        <!-- USERS -->
        <div class="lux-card" style="
            padding:30px;
            border-radius:30px;
        ">

            <h2 style="
                color:white;
                margin-bottom:25px;
            ">
                Recent Users
            </h2>

            <?php foreach($recentUsers as $user): ?>

                <div style="
                    padding:18px 0;
                    border-bottom:1px solid rgba(255,255,255,0.08);
                ">

                    <div style="
                        color:white;
                        font-weight:bold;
                    ">
                        <?= htmlspecialchars($user['full_name']) ?>
                    </div>

                    <div style="
                        color:var(--gray);
                        margin-top:6px;
                    ">
                        <?= htmlspecialchars($user['email']) ?>
                    </div>

                    <div style="
                        color:gold;
                        margin-top:6px;
                        font-size:0.9rem;
                    ">
                        <?= ucfirst($user['role']) ?>
                    </div>

                </div>

            <?php endforeach; ?>

        </div>

        <!-- TRIPS -->
        <div class="lux-card" style="
            padding:30px;
            border-radius:30px;
        ">

            <h2 style="
                color:white;
                margin-bottom:25px;
            ">
                Recent Logistics Activity
            </h2>

            <?php foreach($recentTrips as $trip): ?>

                <div style="
                    padding:18px 0;
                    border-bottom:1px solid rgba(255,255,255,0.08);
                ">

                    <div style="
                        color:white;
                        font-weight:bold;
                    ">
                        <?= htmlspecialchars($trip['pickup_location']) ?>
                    </div>

                    <div style="
                        color:var(--gray);
                        margin-top:6px;
                    ">
                        → <?= htmlspecialchars($trip['destination']) ?>
                    </div>

                    <div style="
                        color:gold;
                        margin-top:6px;
                    ">
                        <?= ucfirst($trip['status']) ?>
                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    </div>

</main>

</div>

<?php require_once '../../includes/footer.php'; ?>