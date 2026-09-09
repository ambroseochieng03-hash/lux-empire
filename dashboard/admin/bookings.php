<?php

declare(strict_types=1);

require_once '../../includes/init.php';
require_once '../../includes/auth_check.php';
requireRoleAccess('admin');

require_once '../../config/csrf.php';
require_once '../../classes/AdminBookingService.php';

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
require_once '../../includes/sidebar.php';

$bookingService = new AdminBookingService();
$bookings = $bookingService->listBookings();

$csrfToken = Csrf::token();
?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/admin.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/admin-cards.css">

<script>
    window.LUX_ADMIN = {
        csrfToken: "<?php echo htmlspecialchars($csrfToken, ENT_QUOTES); ?>",
        baseUrl: "<?php echo BASE_URL; ?>"
    };
</script>

<div class="lux-dashboard-layout">

<main class="lux-dashboard-main">

    <div class="lux-page-header">
        <h1 class="lux-page-title">Booking Oversight</h1>
        <p class="lux-page-subtitle">
            Review bookings across the platform and remove any that
            require administrative action, with a recorded reason.
        </p>
    </div>

    <div class="lux-admin-toolbar">
        <select class="lux-admin-filter-select" id="luxBookingStatusFilter">
            <option value="">All Statuses</option>
            <option value="pending">Pending</option>
            <option value="approved">Approved</option>
            <option value="rejected">Rejected</option>
            <option value="cancelled">Cancelled</option>
        </select>
    </div>

    <div class="lux-card-grid" id="luxBookingGrid">

        <?php foreach ($bookings as $booking): ?>
            <?php
                $statusBadgeClass = match ($booking['status']) {
                    'approved' => 'lux-badge-active',
                    'rejected', 'cancelled' => 'lux-badge-suspended',
                    default => 'lux-badge-pending',
                };
            ?>

            <div class="lux-entity-card" data-status="<?php echo htmlspecialchars($booking['status']); ?>" data-booking-card="<?php echo (int) $booking['id']; ?>">

                <div class="lux-entity-card-header">
                    <div>
                        <div class="lux-entity-name"><?php echo htmlspecialchars($booking['house_title']); ?></div>
                        <div class="lux-entity-meta">Booking #<?php echo (int) $booking['id']; ?></div>
                    </div>
                    <span class="lux-badge <?php echo $statusBadgeClass; ?>"><?php echo ucfirst($booking['status']); ?></span>
                </div>

                <div class="lux-entity-meta">
                    Tenant: <?php echo htmlspecialchars($booking['tenant_name']); ?> (<?php echo htmlspecialchars($booking['tenant_email']); ?>)<br>
                    Landlord: <?php echo htmlspecialchars($booking['landlord_name']); ?><br>
                    <?php echo date('d M Y H:i', strtotime($booking['booking_date'])); ?>
                </div>

                <div class="lux-entity-actions">
                    <button class="lux-btn lux-btn-outline-danger" data-action="delete" data-booking-id="<?php echo (int) $booking['id']; ?>">
                        Delete
                    </button>
                </div>

            </div>

        <?php endforeach; ?>

    </div>

</main>

</div>

<div class="lux-modal-overlay" id="luxConfirmModal" aria-hidden="true">
    <div class="lux-modal-box">
        <h3 class="lux-confirm-title">Are you sure?</h3>
        <p class="lux-confirm-message"></p>
        <div class="lux-confirm-reason-wrap" hidden>
            <textarea class="lux-confirm-reason-input" placeholder="Reason (required)"></textarea>
        </div>
        <div class="lux-modal-actions">
            <button class="lux-btn lux-btn-ghost" data-confirm-close>Cancel</button>
            <button class="lux-btn lux-btn-danger lux-confirm-accept">Confirm</button>
        </div>
    </div>
</div>

<script src="<?php echo BASE_URL; ?>/assets/js/admin/admin-core.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/admin/bookings.js"></script>

<?php require_once '../../includes/footer.php'; ?>
