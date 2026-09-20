<?php
require_once '../../includes/init.php';
require_once '../../includes/auth_check.php';
requireRoleAccess('landlord');

require_once '../../classes/Booking.php';
require_once '../../classes/ListingState.php';

$landlordId = (int) Session::user()['id'];

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$bookingModel = new Booking();
$result = $bookingModel->getBookingHistoryForLandlord($landlordId, $perPage, $offset, null);

$history = $result['bookings'];
$total = (int) $result['total'];
$totalPages = max(1, (int) ceil($total / $perPage));

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
require_once '../../includes/sidebar.php';
?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/bookings.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/listing-state.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/verification-badges.css">

<div class="landlord-layout">

    <main class="landlord-main">

        <div class="landlord-header">

            <h1 class="landlord-title">
                Booking History
            </h1>

            <p class="landlord-subtitle">
                Every request you have answered, plus requests the tenant cancelled or that expired.
            </p>

        </div>

        <div style="display:flex; gap:12px; margin-bottom:24px; flex-wrap:wrap;">
            <a class="lux-btn" style="background:rgba(255,255,255,0.06); color:#fff;" href="<?php echo BASE_URL; ?>/booking-requests">Pending Requests</a>
            <a class="lux-btn" href="<?php echo BASE_URL; ?>/dashboard/landlord/booking_history.php">History</a>
        </div>

        <div class="bookings-grid">

            <?php if (count($history) > 0): ?>

                <?php foreach ($history as $booking): ?>

                    <?php
                        $status = $booking['status'];
                        $refundReason = $booking['refund_reason'] ?? null;

                        if ($status === 'approved') {
                            $label = 'Approved';
                            $statusClass = 'approved';
                        } elseif ($status === 'cancelled') {
                            $label = 'Cancelled by tenant';
                            $statusClass = 'rejected';
                        } elseif ($refundReason === 'reservation_expired') {
                            $label = 'Expired — no response within ' . (int) RESERVATION_RESPONSE_HOURS . 'h';
                            $statusClass = 'rejected';
                        } else {
                            $label = 'Declined';
                            $statusClass = 'rejected';
                        }

                        $showTenantContact = in_array($status, ListingState::contactRevealStatuses(), true);
                    ?>

                    <div class="lux-card booking-card">

                        <div class="booking-content">

                            <h2 class="booking-property-title">
                                <?php echo htmlspecialchars($booking['title'] ?? 'Property'); ?>
                            </h2>

                            <?php if (!empty($booking['location'])): ?>
                                <p class="booking-location">
                                    <?php echo htmlspecialchars($booking['location']); ?>
                                </p>
                            <?php endif; ?>

                            <?php if (isset($booking['price'])): ?>
                                <p class="booking-price">
                                    KES <?php echo number_format((float) $booking['price']); ?>
                                </p>
                            <?php endif; ?>

                            <div class="booking-tenant-box">

                                <?php echo htmlspecialchars($booking['tenant_name']); ?>

                                <?php if ($showTenantContact): ?>

                                    <?php if (!empty($booking['tenant_phone'])): ?>
                                        <br><i class="fa-solid fa-phone"></i>
                                        <a href="tel:<?php echo htmlspecialchars($booking['tenant_phone']); ?>"><?php echo htmlspecialchars($booking['tenant_phone']); ?></a>
                                    <?php endif; ?>

                                    <?php if (!empty($booking['tenant_email'])): ?>
                                        <br><i class="fa-solid fa-envelope"></i>
                                        <a href="mailto:<?php echo htmlspecialchars($booking['tenant_email']); ?>"><?php echo htmlspecialchars($booking['tenant_email']); ?></a>
                                    <?php endif; ?>

                                <?php endif; ?>

                            </div>

                            <div class="booking-status status-<?php echo $statusClass; ?>">
                                <?php echo htmlspecialchars($label); ?>
                            </div>

                            <div style="color:var(--gray); font-size:0.85rem; margin-top:10px;">
                                Requested <?php echo date('d M Y, H:i', strtotime((string) $booking['booking_date'])); ?>
                            </div>

                            <?php if ($status === 'approved'): ?>
                                <div style="margin-top:12px;">
                                    <button type="button"
                                            class="lux-btn chat-starter-btn"
                                            data-tenant-id="<?php echo (int) $booking['tenant_id']; ?>"
                                            <?php if (!empty($booking['house_id'])): ?>data-house-id="<?php echo (int) $booking['house_id']; ?>"<?php endif; ?>
                                            data-other-name="<?php echo htmlspecialchars($booking['tenant_name']); ?>">
                                        <i class="fa-solid fa-comment-dots"></i> Message Tenant
                                    </button>
                                </div>
                            <?php endif; ?>

                        </div>

                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <div class="lux-card empty-card">

                    <h2 class="empty-title">
                        No History Yet
                    </h2>

                    <p class="empty-text">
                        Requests you approve or decline will appear here.
                    </p>

                </div>

            <?php endif; ?>

        </div>

        <?php if ($totalPages > 1): ?>
            <div style="display:flex; align-items:center; justify-content:center; gap:16px; margin-top:30px;">
                <?php if ($page > 1): ?>
                    <a class="lux-btn" href="?page=<?php echo $page - 1; ?>">&laquo; Previous</a>
                <?php endif; ?>

                <span style="color:var(--gray);">Page <?php echo $page; ?> of <?php echo $totalPages; ?> (<?php echo $total; ?> requests)</span>

                <?php if ($page < $totalPages): ?>
                    <a class="lux-btn" href="?page=<?php echo $page + 1; ?>">Next &raquo;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </main>

</div>

<?php require_once '../../includes/chat_starter_modal.php'; ?>

<?php require_once '../../includes/footer.php'; ?>
