<?php
require_once '../../includes/auth_check.php';
requireRoleAccess('landlord');

require_once '../../classes/House.php';
require_once '../../classes/Booking.php';

$landlord_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'Landlord';

$houseModel = new House();
$bookingModel = new Booking();

// Fetch data
$houses = $houseModel->getHousesByLandlord($landlord_id);
$bookings = $bookingModel->getBookingsByLandlord($landlord_id);

// Stats
$totalProperties = count($houses);
$totalBookings = count($bookings);

$pendingBookings = 0;
$approvedBookings = 0;

foreach ($bookings as $booking) {

    if ($booking['status'] === 'pending') {
        $pendingBookings++;
    }

    if ($booking['status'] === 'approved') {
        $approvedBookings++;
    }
}

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
require_once '../../includes/sidebar.php';
?>

<div style="
    display:flex;
    min-height:100vh;
">

<!-- MAIN -->
<main class="tenant-main" style="
    flex:1;
    padding:40px;
    margin-left:280px;
">

    <!-- HEADER -->
    <div style="margin-bottom:45px;">

        <h1 class="tenant-title" style="
            font-family:'Cinzel', serif;
            color:var(--gold);
            font-size:3rem;
            margin-bottom:15px;
        ">
            👑 Welcome, <?php echo htmlspecialchars($user_name); ?>
        </h1>

        <p style="
            color:var(--gray);
            max-width:750px;
            line-height:1.9;
        ">
            Manage your luxury properties, monitor tenant requests,
            and grow your real estate empire with LUX EMPIRE.
        </p>

    </div>

    <!-- STATS -->
    <div class="tenant-grid" style="
        display:grid;
        grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
        gap:25px;
        margin-bottom:45px;
    ">

        <!-- TOTAL PROPERTIES -->
        <div class="lux-card tenant-card tenant-card-padding" style="
            padding:30px;
            border-radius:24px;
            text-align:center;
        ">

            <div style="font-size:2.5rem; margin-bottom:10px;">
            </div>

            <h2 style="
                color:var(--gold);
                font-size:2.2rem;
            ">
                <?php echo $totalProperties; ?>
            </h2>

            <p style="color:var(--gray);">
                Total Properties
            </p>

        </div>

        <!-- BOOKINGS -->
        <div class="lux-card tenant-card tenant-card-padding" style="
            padding:30px;
            border-radius:24px;
            text-align:center;
        ">

            <div style="font-size:2.5rem; margin-bottom:10px;">
            </div>

            <h2 style="
                color:var(--gold);
                font-size:2.2rem;
            ">
                <?php echo $totalBookings; ?>
            </h2>

            <p style="color:var(--gray);">
                Total Bookings
            </p>

        </div>

        <!-- PENDING -->
        <div class="lux-card tenant-card tenant-card-padding" style="
            padding:30px;
            border-radius:24px;
            text-align:center;
        ">

            <div style="font-size:2.5rem; margin-bottom:10px;">
            </div>

            <h2 style="
                color:orange;
                font-size:2.2rem;
            ">
                <?php echo $pendingBookings; ?>
            </h2>

            <p style="color:var(--gray);">
                Pending Requests
            </p>

        </div>

        <!-- APPROVED -->
        <div class="lux-card tenant-card tenant-card-padding" style="
            padding:30px;
            border-radius:24px;
            text-align:center;
        ">

            <div style="font-size:2.5rem; margin-bottom:10px;">
            </div>

            <h2 style="
                color:lightgreen;
                font-size:2.2rem;
            ">
                <?php echo $approvedBookings; ?>
            </h2>

            <p style="color:var(--gray);">
                Approved Bookings
            </p>

        </div>

    </div>

    <!-- QUICK ACTIONS -->
    <div class="tenant-grid" style="
        display:grid;
        grid-template-columns:repeat(auto-fit,minmax(260px,1fr));
        gap:25px;
        margin-bottom:45px;
    ">

        <!-- ADD HOUSE -->
        <a href="add_house.php"
           class="lux-card tenant-card tenant-card-padding"
           style="
                padding:30px;
                border-radius:24px;
                text-decoration:none;
                display:block;
           ">

            <h3 style="
                color:white;
                margin-bottom:10px;
            ">
                Add Property
            </h3>

            <p style="color:var(--gray);">
                Create a new luxury listing.
            </p>

        </a>

        <!-- MANAGE -->
        <a href="manage_houses.php"
           class="lux-card tenant-card tenant-card-padding"
           style="
                padding:30px;
                border-radius:24px;
                text-decoration:none;
                display:block;
           ">

            <h3 style="
                color:white;
                margin-bottom:10px;
            ">
                Manage Properties
            </h3>

            <p style="color:var(--gray);">
                Edit and manage your listings.
            </p>

        </a>

        <!-- BOOKINGS -->
        <a href="booking_requests.php"
           class="lux-card tenant-card tenant-card-padding"
           style="
                padding:30px;
                border-radius:24px;
                text-decoration:none;
                display:block;
           ">

            <h3 style="
                color:white;
                margin-bottom:10px;
            ">
                Booking Requests
            </h3>

            <p style="color:var(--gray);">
                Review tenant booking activity.
            </p>

        </a>

    </div>

    <!-- RECENT BOOKINGS -->
    <div class="lux-card tenant-card tenant-card-padding" style="
        padding:35px;
        border-radius:28px;
    ">

        <div class="tenant-flex" style="
            display:flex;
            justify-content:space-between;
            align-items:center;
            margin-bottom:25px;
            flex-wrap:wrap;
            gap:15px;
        ">

            <h2 style="
                color:white;
                font-size:1.8rem;
            ">
                Recent Booking Activity
            </h2>

            <a href="booking_requests.php"
               style="
                    color:var(--gold);
                    text-decoration:none;
               ">
                View All →
            </a>

        </div>

        <?php if (count($bookings) > 0): ?>

            <?php foreach (array_slice($bookings, 0, 5) as $booking): ?>

                <div class="tenant-flex" style="
                    padding:18px;
                    border-bottom:1px solid rgba(255,255,255,0.05);
                    display:flex;
                    justify-content:space-between;
                    align-items:center;
                    flex-wrap:wrap;
                    gap:10px;
                ">

                    <div>

                        <div style="
                            color:white;
                            margin-bottom:5px;
                        ">
                            Booking #<?php echo $booking['id']; ?>
                        </div>

                        <small style="color:var(--gray);">
                            <?php echo date('F d, Y', strtotime($booking['booking_date'])); ?>
                        </small>

                    </div>

                    <div style="
                        color:
                        <?php
                            if ($booking['status'] == 'approved') {
                                echo 'lightgreen';
                            } elseif ($booking['status'] == 'rejected') {
                                echo 'red';
                            } else {
                                echo 'orange';
                            }
                        ?>;
                        font-weight:bold;
                    ">

                        <?php echo ucfirst($booking['status']); ?>

                    </div>

                </div>

            <?php endforeach; ?>

        <?php else: ?>

            <div style="
                color:var(--gray);
                padding:20px 0;
            ">
                No booking activity yet.
            </div>

        <?php endif; ?>

    </div>

</main>

</div>

<?php require_once '../../includes/footer.php'; ?>