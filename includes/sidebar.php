<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$userRole = $_SESSION['role'] ?? 'guest';
$userName = $_SESSION['full_name'] ?? 'Empire Member';
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<aside class="lux-sidebar" id="luxSidebar">

    <!-- BRAND HEADER -->
    <div class="sidebar-brand">
        <div class="brand-icon">👑</div>
        <h2>LUX EMPIRE</h2>
        <p><?php echo htmlspecialchars(strtoupper($userRole)); ?> PORTAL</p>
    </div>

    <!-- USER BLOCK -->
    <div class="sidebar-user">
        <div class="user-avatar">👑</div>
        <div>
            <h3><?php echo htmlspecialchars($userName); ?></h3>
            <small>Welcome back</small>
        </div>
    </div>

    <!-- NAVIGATION -->
    <nav class="sidebar-nav">

        <?php if ($userRole === 'tenant'): ?>

            <a href="<?php echo BASE_URL; ?>/dashboard/tenant/dashboard.php"
               class="<?= $currentPage == 'dashboard.php' ? 'active' : '' ?>">
                Dashboard
            </a>

            <a href="<?php echo BASE_URL; ?>/dashboard/tenant/search_houses.php"
               class="<?= $currentPage == 'search_houses.php' ? 'active' : '' ?>">
                Find Homes
            </a>

            <a href="<?php echo BASE_URL; ?>/dashboard/tenant/my_bookings.php"
               class="<?= $currentPage == 'my_bookings.php' ? 'active' : '' ?>">
                My Bookings
            </a>

            <a href="<?php echo BASE_URL; ?>/dashboard/tenant/request_truck.php"
               class="<?= $currentPage == 'request_truck.php' ? 'active' : '' ?>">
                Request Move
            </a>

            <a href="<?php echo BASE_URL; ?>/dashboard/tenant/track_driver.php"
               class="<?= $currentPage == 'track_driver.php' ? 'active' : '' ?>">
                Track Driver
            </a>

            <form method="POST" action="../../api/emergency/trigger_alert.php">

                <input type="hidden" name="message" value="Tenant emergency alert">

                <button style="
                    background:red;
                    color:white;
                    padding:14px 20px;
                    border-radius:14px;
                    font-weight:bold;
                ">
                    EMERGENCY
                </button>

            </form>

        <?php elseif ($userRole === 'landlord'): ?>

            <a href="<?php echo BASE_URL; ?>/dashboard/landlord/dashboard.php"> Dashboard</a>
            <a href="<?php echo BASE_URL; ?>/dashboard/landlord/add_house.php">Add Property</a>
            <a href="<?php echo BASE_URL; ?>/dashboard/landlord/manage_houses.php"> Manage Estates</a>
            <a href="<?php echo BASE_URL; ?>/dashboard/landlord/booking_requests.php">Booking Requests</a>

        <?php elseif ($userRole === 'driver'): ?>

            <a href="<?php echo BASE_URL; ?>/dashboard/driver/dashboard.php">Dashboard</a>
            <a href="<?php echo BASE_URL; ?>/dashboard/driver/available_requests.php">Available Jobs</a>
            <a href="<?php echo BASE_URL; ?>/dashboard/driver/active_trip.php">Active Trip</a>
            <a href="<?php echo BASE_URL; ?>/dashboard/driver/location_tracker.php">Live Tracker</a>
            

        <?php elseif ($userRole === 'admin'): ?>

            <a href="<?php echo BASE_URL; ?>/dashboard/admin/dashboard.php">Empire HQ</a>
            <a href="<?php echo BASE_URL; ?>/dashboard/admin/users.php">Users</a>
            <a href="<?php echo BASE_URL; ?>/dashboard/admin/houses.php">Estates</a>
            <a href="<?php echo BASE_URL; ?>/dashboard/admin/truck_requests.php">Logistics</a>
            <a href="<?php echo BASE_URL; ?>/dashboard/admin/reports.php">Reports</a>
            <a href="<?php echo BASE_URL; ?>/dashboard/admin/emergency.php">Emergencies</a>

        <?php endif; ?>

        <a href="<?php echo BASE_URL; ?>/auth/logout.php" class="logout-link">
            Exit Empire
        </a>

    </nav>

</aside>

<!-- MOBILE TOGGLE -->
<button class="lux-sidebar-toggle"
        onclick="document.getElementById('luxSidebar').classList.toggle('active')">
    👑
</button>