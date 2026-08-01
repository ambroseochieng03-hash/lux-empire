<?php
require_once '../../includes/auth_check.php';
requireRoleAccess('tenant');

require_once '../../config/db.php';

$user_name = $_SESSION['user_name'] ?? 'Tenant';
$tenant_id = $_SESSION['user_id'];

require_once '../../classes/Booking.php';
require_once '../../classes/TruckRequest.php';

$booking = new Booking();
$truck = new TruckRequest();

// Data
$myBookings = $booking->getTenantBookings($tenant_id);
$myTrucks = $truck->getTenantRequests($tenant_id);

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
require_once '../../includes/sidebar.php';
?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/tenant.css">

<div class="lux-tenant-layout">

    <!-- MAIN -->
    <main class="lux-tenant-main">

        <!-- HERO -->
        <div class="lux-tenant-hero">

            <h1 class="lux-tenant-title">
                👑 Welcome back,
                <?php echo htmlspecialchars($user_name); ?>
            </h1>

            <p class="lux-tenant-subtitle">
                Manage your luxury homes, bookings,
                and relocation requests from your
                LUX EMPIRE control center.
            </p>

        </div>

        <!-- STATS -->
        <div class="lux-tenant-stats">

            <!-- BOOKINGS -->
            <div class="lux-card lux-tenant-stat-card">

                <h2 class="lux-tenant-stat-number">
                    <?php echo count($myBookings); ?>
                </h2>

                <p class="lux-tenant-stat-label">
                    My Bookings
                </p>

            </div>

            <!-- TRUCK REQUESTS -->
            <div class="lux-card lux-tenant-stat-card">

                <h2 class="lux-tenant-stat-number">
                    <?php echo count($myTrucks); ?>
                </h2>

                <p class="lux-tenant-stat-label">
                    Truck Requests
                </p>

            </div>

            <!-- PENDING -->
            <div class="lux-card lux-tenant-stat-card">

                <h2 class="lux-tenant-stat-number">

                    <?php
                        $pending = 0;

                        foreach ($myBookings as $b) {
                            if ($b['status'] == 'pending') {
                                $pending++;
                            }
                        }

                        echo $pending;
                    ?>

                </h2>

                <p class="lux-tenant-stat-label">
                    Pending Approvals
                </p>

            </div>

        </div>

        <!-- QUICK ACTIONS -->
        <div class="lux-tenant-actions">

            <!-- SEARCH HOMES -->
            <a href="../tenant/search_houses.php"
               class="lux-card lux-tenant-action-card">

                <h3 class="lux-tenant-action-title">
                    Search Luxury Homes
                </h3>

                <p class="lux-tenant-action-text">
                    Find your next elite residence
                </p>

            </a>

            <!-- REQUEST TRUCK -->
            <a href="../tenant/request_truck.php"
               class="lux-card lux-tenant-action-card">

                <h3 class="lux-tenant-action-title">
                    Request Truck
                </h3>

                <p class="lux-tenant-action-text">
                    Move with premium logistics
                </p>

            </a>

            <!-- BOOKINGS -->
            <a href="../tenant/my_bookings.php"
               class="lux-card lux-tenant-action-card">

                <h3 class="lux-tenant-action-title">
                    My Bookings
                </h3>

                <p class="lux-tenant-action-text">
                    Track your rental history
                </p>

            </a>

            <!-- TRACK DRIVER -->
            <a href="<?php echo BASE_URL; ?>/dashboard/tenant/track_driver.php"
               class="lux-card lux-tenant-action-card">

                <h3 class="lux-tenant-action-title">
                    Track Driver
                </h3>

                <p class="lux-tenant-action-text">
                    Monitor your driver's location
                </p>

            </a>

        </div>

        <!-- RECENT ACTIVITY -->
        <div class="lux-card lux-activity-card">

            <h2 class="lux-activity-title">
                Recent Activity
            </h2>

            <?php if (count($myBookings) > 0): ?>

                <?php foreach (array_slice($myBookings, 0, 5) as $b): ?>

                    <div class="lux-activity-item">

                        Booking #<?php echo $b['id']; ?> -

                        <span style="color:var(--gold);">
                            <?php echo ucfirst($b['status']); ?>
                        </span>

                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <p style="color:var(--gray);">
                    No activity yet.
                    Start exploring luxury homes.
                </p>

            <?php endif; ?>

        </div>

    </main>

</div>

<?php require_once '../../includes/footer.php'; ?>