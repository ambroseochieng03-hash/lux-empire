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
// USERS STATS
// =====================================
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

$rolesStmt = $pdo->query("
    SELECT role, COUNT(*) as total
    FROM users
    GROUP BY role
");
$roles = $rolesStmt->fetchAll();

// =====================================
// HOUSING STATS
// =====================================
$totalHouses = $pdo->query("SELECT COUNT(*) FROM houses")->fetchColumn();

$houseStatusStmt = $pdo->query("
    SELECT status, COUNT(*) as total
    FROM houses
    GROUP BY status
");
$houseStatuses = $houseStatusStmt->fetchAll();

// =====================================
// LOGISTICS STATS
// =====================================
$totalRequests = $pdo->query("SELECT COUNT(*) FROM truck_requests")->fetchColumn();

$tripStatusStmt = $pdo->query("
    SELECT status, COUNT(*) as total
    FROM truck_requests
    GROUP BY status
");
$tripStatuses = $tripStatusStmt->fetchAll();

$completedTrips = $pdo->query("
    SELECT COUNT(*) FROM truck_requests WHERE status = 'completed'
")->fetchColumn();

$pendingTrips = $pdo->query("
    SELECT COUNT(*) FROM truck_requests WHERE status = 'pending'
")->fetchColumn();

$activeTrips = $pdo->query("
    SELECT COUNT(*) FROM truck_requests WHERE status = 'in_transit'
")->fetchColumn();

?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/admin.css">

<div class="lux-dashboard-layout">

<main class="lux-dashboard-main">

    <!-- HEADER -->
    <div class="lux-page-header">

        <h1 class="lux-page-title">
            Empire Analytics
        </h1>

        <p class="lux-page-subtitle">
            Real-time overview of LUX EMPIRE operations.
            Track growth, activity, and system performance
            across users, housing, and logistics.
        </p>

    </div>

    <!-- SUMMARY CARDS -->
    <div class="lux-stats-grid">

        <div class="lux-card" style="padding:25px; border-radius:25px;">
            <h3 style="color:var(--gray);">Total Users</h3>
            <div style="color:white; font-size:2.3rem; font-weight:bold;">
                <?= $totalUsers ?>
            </div>
        </div>

        <div class="lux-card" style="padding:25px; border-radius:25px;">
            <h3 style="color:var(--gray);">Total Houses</h3>
            <div style="color:white; font-size:2.3rem; font-weight:bold;">
                <?= $totalHouses ?>
            </div>
        </div>

        <div class="lux-card" style="padding:25px; border-radius:25px;">
            <h3 style="color:var(--gray);">Truck Requests</h3>
            <div style="color:white; font-size:2.3rem; font-weight:bold;">
                <?= $totalRequests ?>
            </div>
        </div>

        <div class="lux-card" style="padding:25px; border-radius:25px;">
            <h3 style="color:var(--gray);">Completed Trips</h3>
            <div style="color:gold; font-size:2.3rem; font-weight:bold;">
                <?= $completedTrips ?>
            </div>
        </div>

    </div>

    <!-- BREAKDOWN SECTION -->
    <div class="lux-analytics-grid">

        <!-- USER ROLES -->
        <div class="lux-card" style="padding:30px; border-radius:30px;">
            <h2 style="color:white; margin-bottom:20px;">Users by Role</h2>

            <?php foreach($roles as $r): ?>
                <div style="margin-bottom:15px;">
                    <div style="color:white; font-weight:bold;">
                        <?= ucfirst($r['role']) ?>
                    </div>
                    <div style="color:var(--gray);">
                        <?= $r['total'] ?> users
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- HOUSE STATUS -->
        <div class="lux-card" style="padding:30px; border-radius:30px;">
            <h2 style="color:white; margin-bottom:20px;">House Status</h2>

            <?php foreach($houseStatuses as $h): ?>
                <div style="margin-bottom:15px;">
                    <div style="color:white; font-weight:bold;">
                        <?= ucfirst($h['status']) ?>
                    </div>
                    <div style="color:var(--gray);">
                        <?= $h['total'] ?> listings
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    </div>

    <!-- LOGISTICS BREAKDOWN -->
    <div class="lux-card lux-logistics-card">

        <h2 style="color:white; margin-bottom:25px;">
            Logistics Overview
        </h2>

        <div class="lux-logistics-grid">

            <div>
                <div style="color:var(--gray);">Pending</div>
                <div style="color:#ffae42; font-size:2rem;">
                    <?= $pendingTrips ?>
                </div>
            </div>

            <div>
                <div style="color:var(--gray);">Active</div>
                <div style="color:#4da6ff; font-size:2rem;">
                    <?= $activeTrips ?>
                </div>
            </div>

            <div>
                <div style="color:var(--gray);">Completed</div>
                <div style="color:#00cc66; font-size:2rem;">
                    <?= $completedTrips ?>
                </div>
            </div>

        </div>

    </div>

</main>

</div>

<?php require_once '../../includes/footer.php'; ?>