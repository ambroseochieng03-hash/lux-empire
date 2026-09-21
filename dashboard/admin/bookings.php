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

$page = max(1, (int) ($_GET['page'] ?? 1));

$statusFilter = $_GET['status'] ?? '';
$allowedStatuses = ['pending', 'approved', 'rejected', 'cancelled'];
if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = null;
}

$perPage = 50;
$offset = ($page - 1) * $perPage;

$bookingService = new AdminBookingService();
$listResult = $bookingService->listBookings($statusFilter, $perPage, $offset);
$bookings = $listResult['bookings'];
$totalBookings = $listResult['total'];
$totalPages = max(1, (int) ceil($totalBookings / $perPage));

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
            <option value="" <?php echo $statusFilter === null ? 'selected' : ''; ?>>All Statuses</option>
            <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
            <option value="approved" <?php echo $statusFilter === 'approved' ? 'selected' : ''; ?>>Approved</option>
            <option value="rejected" <?php echo $statusFilter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
            <option value="cancelled" <?php echo $statusFilter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
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

                <?php
                    $isPaid = ($booking['payment_status'] ?? 'unpaid') === 'paid';
                    $bookingStatus = $booking['status'];
                    $refundStatus = $booking['refund_status'] ?? null;
                ?>

                <div class="lux-entity-meta">
                    Tenant: <?php echo htmlspecialchars($booking['tenant_name']); ?> (<?php echo htmlspecialchars($booking['tenant_email']); ?>)<br>
                    Landlord: <?php echo htmlspecialchars($booking['landlord_name']); ?><br>
                    <?php echo date('d M Y H:i', strtotime($booking['booking_date'])); ?><br>
                    Payment:
                    <?php if ($isPaid): ?>
                            <?php echo !empty($booking['waiver_id'])
                            ? 'Free booking voucher (nothing paid)'
                            : 'KES ' . number_format((float) ($booking['paid_amount'] ?? 0)) . ' paid'; ?>
                        <?php if ($refundStatus): ?> · Refund: <strong><?php echo htmlspecialchars(ucfirst($refundStatus)); ?></strong><?php endif; ?>
                    <?php else: ?>
                        Unpaid
                    <?php endif; ?>
                </div>

                <div class="lux-entity-actions">

                    <?php if ($bookingStatus === 'pending' && $isPaid): ?>
                        <button class="lux-btn lux-btn-danger" data-action="cancel-refund" data-booking-id="<?php echo (int) $booking['id']; ?>">
                            Cancel &amp; Refund
                        </button>
                    <?php endif; ?>

                    <?php if (in_array($bookingStatus, ['rejected', 'cancelled'], true)): ?>
                        <button class="lux-btn lux-btn-ghost" data-action="archive" data-booking-id="<?php echo (int) $booking['id']; ?>">
                            Archive
                        </button>
                    <?php endif; ?>

                    <?php if (!$isPaid && $bookingStatus !== 'approved'): ?>
                        <button class="lux-btn lux-btn-outline-danger" data-action="delete" data-booking-id="<?php echo (int) $booking['id']; ?>">
                            Delete
                        </button>
                    <?php endif; ?>

                    <?php if ($bookingStatus === 'approved'): ?>
                        <span class="lux-entity-meta">Approved — the booking fee has been earned, so there is no refund action.</span>
                    <?php endif; ?>

                </div>

            </div>

        <?php endforeach; ?>

    </div>

    <?php if ($totalPages > 1): ?>
    <div style="display:flex; align-items:center; justify-content:center; gap:16px; margin-top:30px;">
        <?php if ($page > 1): ?>
            <a class="lux-btn lux-btn-ghost" href="?page=<?php echo $page - 1; ?><?php echo $statusFilter ? '&status=' . urlencode($statusFilter) : ''; ?>">&laquo; Previous</a>
        <?php endif; ?>

        <span style="color:var(--gray);">Page <?php echo $page; ?> of <?php echo $totalPages; ?> (<?php echo $totalBookings; ?> bookings)</span>

        <?php if ($page < $totalPages): ?>
            <a class="lux-btn lux-btn-ghost" href="?page=<?php echo $page + 1; ?><?php echo $statusFilter ? '&status=' . urlencode($statusFilter) : ''; ?>">Next &raquo;</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

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
