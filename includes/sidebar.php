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

            <button type="button" class="lux-emergency-trigger-btn" data-open-emergency-modal>
                EMERGENCY
            </button>

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

            <button type="button" class="lux-emergency-trigger-btn" data-open-emergency-modal>
                EMERGENCY
            </button>
            

        <?php elseif ($userRole === 'admin'): ?>

            <a href="<?php echo BASE_URL; ?>/admin"><i class="fa-solid fa-gauge-high"></i> Empire HQ</a>
            <a href="<?php echo BASE_URL; ?>/admin/users"><i class="fa-solid fa-users"></i> Users</a>
            <a href="<?php echo BASE_URL; ?>/admin/houses"><i class="fa-solid fa-building"></i> Estates</a>
            <a href="<?php echo BASE_URL; ?>/admin/truck-requests"><i class="fa-solid fa-truck-fast"></i> Logistics</a>
            <a href="<?php echo BASE_URL; ?>/admin/reports"><i class="fa-solid fa-chart-column"></i> Reports</a>
            <a href="<?php echo BASE_URL; ?>/admin/emergency"><i class="fa-solid fa-triangle-exclamation"></i> Emergencies</a>
            <a href="<?php echo BASE_URL; ?>/admin/bookings"><i class="fa-solid fa-calendar-check"></i> Bookings</a>
            <a href="<?php echo BASE_URL; ?>/admin/messages"><i class="fa-solid fa-envelope"></i> Broadcast</a>

        <?php endif; ?>

        <a href="<?php echo BASE_URL; ?>/logout" class="logout-link" id="luxLogoutTrigger">
            <i class="fa-solid fa-right-from-bracket"></i> Exit Empire
        </a>

        <?php if (in_array($userRole, ['tenant', 'driver'], true)): ?>

            <?php
                require_once __DIR__ . '/../config/csrf.php';
                $emergencyCsrf = Csrf::token();
            ?>

            <script>
                window.LUX_EMERGENCY = {
                    csrfToken: "<?php echo htmlspecialchars($emergencyCsrf, ENT_QUOTES); ?>",
                    baseUrl: "<?php echo BASE_URL; ?>"
                };
            </script>

            <?php require __DIR__ . '/emergency_alert_modal.php'; ?>

            <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/admin-cards.css">
            <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/emergency-alert.css">
            <script src="<?php echo BASE_URL; ?>/assets/js/emergency-alert.js"></script>

        <?php endif; ?>

    </nav>

<style>
    /* Sidebar nav icons — was inheriting plain white from the link
       text; gold matches the icon treatment already used everywhere
       else in the app (navbar bell, chat icons, etc.), with the
       emergency button's icon kept red since that one is
       deliberately an alarm color, not a brand accent. */
    .sidebar-nav a i {
        color: var(--gold);
        width: 20px;
        text-align: center;
        margin-right: 4px;
    }

    .sidebar-nav a.logout-link i {
        color: #ff6b6b;
    }

    .lux-emergency-trigger-btn i {
        color: white;
    }
</style>

</aside>

<!-- MOBILE TOGGLE -->
<button class="lux-sidebar-toggle"
        onclick="document.getElementById('luxSidebar').classList.toggle('active')">
    <i class="fa-solid fa-bars"></i>
</button>

<!-- LOGOUT CONFIRMATION MODAL — inline styles deliberately, so this
     renders correctly regardless of what's already defined in
     dashboard.css/style.css, rather than assuming a class name that
     might not exist. -->
<div id="luxLogoutModal" style="
    display:none;
    position:fixed;
    inset:0;
    z-index:2000;
    align-items:center;
    justify-content:center;
    padding:20px;
">
    <div id="luxLogoutModalOverlay" style="
        position:absolute;
        inset:0;
        background:rgba(0,0,0,0.75);
        backdrop-filter:blur(4px);
    "></div>

    <div style="
        position:relative;
        max-width:440px;
        width:100%;
        background:rgba(15,15,20,0.97);
        border:1px solid rgba(212,175,55,0.3);
        border-radius:22px;
        padding:32px 28px;
        box-shadow:0 0 40px rgba(212,175,55,0.15);
        text-align:center;
    ">
        <div style="font-size:2.2rem; color:gold; margin-bottom:14px;">
            <i class="fa-solid fa-right-from-bracket"></i>
        </div>

        <h2 style="color:gold; font-family:'Cinzel', serif; font-size:1.5rem; margin-bottom:14px;">
            Sign Out of LUX EMPIRE?
        </h2>

        <p style="color:#ccc; line-height:1.7; margin-bottom:26px; font-size:0.95rem;">
            You're about to sign out of this device. Your account and all your
            data stay exactly as they are, nothing is deleted. However, this
            device will no longer be remembered, so the next time you sign in
            here you'll need to verify with a one-time code sent to your email,
            in addition to your password.
        </p>

        <div style="display:flex; flex-direction:column; gap:12px;">

            <button type="button" id="luxLogoutConfirmBtn" style="
                background:linear-gradient(135deg, gold, #8f6b00);
                color:black;
                border:none;
                border-radius:14px;
                padding:14px;
                font-weight:700;
                cursor:pointer;
                font-size:0.95rem;
            ">
                Yes, Sign Me Out
            </button>

            <button type="button" id="luxLogoutStayBtn" style="
                background:rgba(255,255,255,0.06);
                color:white;
                border:1px solid rgba(255,255,255,0.15);
                border-radius:14px;
                padding:14px;
                font-weight:600;
                cursor:pointer;
                font-size:0.95rem;
            ">
                Stay Signed In
            </button>

            <button type="button" id="luxLogoutHomeBtn" style="
                background:none;
                color:#999;
                border:none;
                padding:8px;
                cursor:pointer;
                font-size:0.85rem;
                text-decoration:underline;
            ">
                Leave Without Signing Out
            </button>

        </div>

    </div>
</div>

<script>
(function () {

    const trigger = document.getElementById('luxLogoutTrigger');
    const modal = document.getElementById('luxLogoutModal');

    if (!trigger || !modal) {
        return;
    }

    const overlay = document.getElementById('luxLogoutModalOverlay');
    const confirmBtn = document.getElementById('luxLogoutConfirmBtn');
    const stayBtn = document.getElementById('luxLogoutStayBtn');
    const homeBtn = document.getElementById('luxLogoutHomeBtn');

    function openModal() {
        modal.style.display = 'flex';
    }

    function closeModal() {
        modal.style.display = 'none';
    }

    trigger.addEventListener('click', function (event) {
        event.preventDefault();
        openModal();
    });

    overlay.addEventListener('click', closeModal);
    stayBtn.addEventListener('click', closeModal);

    confirmBtn.addEventListener('click', function () {
        window.location.href = trigger.getAttribute('href');
    });

    homeBtn.addEventListener('click', function () {
        window.location.href = <?php echo json_encode(BASE_URL . '/'); ?>;
    });

})();
</script>