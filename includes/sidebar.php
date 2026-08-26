<?php

declare(strict_types=1);

$user = Session::user();

if ($user === null) {
    $userRole = 'guest';
    $userName = 'Empire Member';
} else {
    $userRole = $user['role'] ?? 'guest';
    $userName = $user['full_name'] ?? 'Empire Member';
}

$currentPage = basename($_SERVER['PHP_SELF']);
?>

<aside class="lux-sidebar" id="luxSidebar">

    <!-- BRAND HEADER -->
    <div class="sidebar-brand">
        <div class="brand-icon"><i class="fa-solid fa-crown"></i></div>
        <h2>LUX EMPIRE</h2>
        <p><?php echo htmlspecialchars(strtoupper($userRole)); ?> PORTAL</p>
    </div>

    <!-- USER BLOCK -->
    <div class="sidebar-user">
        <div class="user-avatar"><i class="fa-solid fa-user"></i></div>
        <div>
            <h3><?php echo htmlspecialchars($userName); ?></h3>
            <small>Welcome back</small>
        </div>
    </div>

    <!-- NAVIGATION -->
    <nav class="sidebar-nav">

        <?php if ($userRole === 'tenant'): ?>

            <a href="<?php echo BASE_URL; ?>/tenant"
               class="<?= $currentPage == 'dashboard.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-gauge-high"></i> Dashboard
            </a>

            <a href="<?php echo BASE_URL; ?>/tenant/search-houses"
               class="<?= $currentPage == 'search_houses.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-house"></i> Find Homes
            </a>

            <a href="<?php echo BASE_URL; ?>/tenant/my-bookings"
               class="<?= $currentPage == 'my_bookings.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-calendar-check"></i> My Bookings
            </a>

            <a href="<?php echo BASE_URL; ?>/tenant/request-truck"
               class="<?= $currentPage == 'request_truck.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-truck"></i> Request Move
            </a>

            <a href="<?php echo BASE_URL; ?>/tenant/track-driver"
               class="<?= $currentPage == 'track_driver.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-location-dot"></i> Track Driver
            </a>

            <!-- inside the tenant block, after "Track Driver" -->
            <a href="<?php echo BASE_URL; ?>/tenant/messages"
            class="<?= $currentPage == 'messages.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-comments"></i> Chats
                <span class="sidebar-badge" id="sidebarChatBadge" style="display:none;"></span>
            </a>

            <a href="<?php echo BASE_URL; ?>/tenant/notifications">
                <i class="fa-solid fa-bell"></i> Notifications
                <span class="sidebar-badge" id="sidebarNotifBadge" style="display:none;"></span>
            </a>

            <form method="POST" action="<?php echo BASE_URL; ?>/api/emergency/trigger_alert.php">

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

            <a href="<?php echo BASE_URL; ?>/landlord"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
            <a href="<?php echo BASE_URL; ?>/add-property"><i class="fa-solid fa-house"></i> Add Property</a>
            <a href="<?php echo BASE_URL; ?>/manage-houses"><i class="fa-solid fa-building"></i> Manage Estates</a>
            <a href="<?php echo BASE_URL; ?>/booking-requests"><i class="fa-solid fa-calendar-check"></i> Booking Requests</a>
            <!-- inside the landlord block -->
            <a href="<?php echo BASE_URL; ?>/landlord/messages">
                <i class="fa-solid fa-comments"></i> Chats
                <span class="sidebar-badge" id="sidebarChatBadge" style="display:none;"></span>
            </a>

            <a href="<?php echo BASE_URL; ?>/landlord/notifications">
                <i class="fa-solid fa-bell"></i> Notifications
                <span class="sidebar-badge" id="sidebarNotifBadge" style="display:none;"></span>
            </a>

        <?php elseif ($userRole === 'driver'): ?>

            <a href="<?php echo BASE_URL; ?>/driver"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
            <a href="<?php echo BASE_URL; ?>/driver/available-requests"><i class="fa-solid fa-truck"></i> Available Jobs</a>
            <!-- inside the driver block -->
            <a href="<?php echo BASE_URL; ?>/driver/messages">
                <i class="fa-solid fa-comments"></i> Chats
                <span class="sidebar-badge" id="sidebarChatBadge" style="display:none;"></span>
            </a>

            <a href="<?php echo BASE_URL; ?>/driver/notifications">
                <i class="fa-solid fa-bell"></i> Notifications
                <span class="sidebar-badge" id="sidebarNotifBadge" style="display:none;"></span>
            </a>

            <a href="<?php echo BASE_URL; ?>/driver/active-trip"><i class="fa-solid fa-truck-fast"></i> Active Trip</a>
            <a href="<?php echo BASE_URL; ?>/driver/location-tracker"><i class="fa-solid fa-location-dot"></i> Live Tracker</a>
            

        <?php elseif ($userRole === 'admin'): ?>

            <a href="<?php echo BASE_URL; ?>/admin"><i class="fa-solid fa-gauge-high"></i> Empire HQ</a>
            <a href="<?php echo BASE_URL; ?>/admin/users"><i class="fa-solid fa-users"></i> Users</a>
            <a href="<?php echo BASE_URL; ?>/admin/houses"><i class="fa-solid fa-building"></i> Estates</a>
            <a href="<?php echo BASE_URL; ?>/admin/truck-requests"><i class="fa-solid fa-truck-fast"></i> Logistics</a>
            <a href="<?php echo BASE_URL; ?>/admin/reports"><i class="fa-solid fa-chart-column"></i> Reports</a>
            <a href="<?php echo BASE_URL; ?>/admin/emergency"><i class="fa-solid fa-triangle-exclamation"></i> Emergencies</a>

        <?php endif; ?>

        <a href="<?php echo BASE_URL; ?>/logout" class="logout-link">
            <i class="fa-solid fa-right-from-bracket"></i> Exit Empire
        </a>

    </nav>

</aside>

<!-- MOBILE TOGGLE -->
<button class="lux-sidebar-toggle"
        onclick="document.getElementById('luxSidebar').classList.toggle('active')">
    <i class="fa-solid fa-bars"></i>
</button>