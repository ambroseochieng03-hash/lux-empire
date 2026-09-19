<?php

declare(strict_types=1);

require_once '../../includes/init.php';
require_once '../../includes/auth_check.php';
requireRoleAccess('admin');

require_once '../../config/csrf.php';
require_once '../../classes/Payment.php';

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
require_once '../../includes/sidebar.php';

$payment = new Payment();
$needingReview = $payment->listNeedingReview();
$refundQueue = $payment->listRefundsForAdmin();
$recent = $payment->listRecent(50);

$csrfToken = Csrf::token();
?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/admin.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/admin-cards.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/form-validation.css">

<script>
    window.LUX_ADMIN = {
        csrfToken: "<?php echo htmlspecialchars($csrfToken, ENT_QUOTES); ?>",
        baseUrl: "<?php echo BASE_URL; ?>"
    };
</script>

<div class="lux-dashboard-layout">

<main class="lux-dashboard-main">

    <div class="lux-page-header">
        <h1 class="lux-page-title">Payment Oversight</h1>
        <p class="lux-page-subtitle">
            Manually verify payments tenants/landlords flagged, resolve
            pending refunds, and review recent payment activity.
        </p>
    </div>

    <h2 style="color:white; margin:30px 0 15px;">Needs Manual Review (<?php echo count($needingReview); ?>)</h2>

    <div class="lux-card-grid" id="luxReviewGrid">

        <?php if (empty($needingReview)): ?>
            <p style="color:var(--gray);">Nothing waiting on review.</p>
        <?php endif; ?>

        <?php foreach ($needingReview as $p): ?>
            <div class="lux-entity-card" data-payment-card="<?php echo (int) $p['id']; ?>">
                <div class="lux-entity-card-header">
                    <div>
                        <div class="lux-entity-name"><?php echo ucwords(str_replace('_', ' ', $p['purpose'])); ?> — KES <?php echo number_format((float) $p['amount']); ?></div>
                        <div class="lux-entity-meta">Payment #<?php echo (int) $p['id']; ?></div>
                    </div>
                    <span class="lux-badge lux-badge-pending">Pending</span>
                </div>

                <div class="lux-entity-meta">
                    <?php echo htmlspecialchars($p['full_name']); ?> (<?php echo htmlspecialchars($p['email']); ?>)<br>
                    Phone: <?php echo htmlspecialchars($p['phone']); ?><br>
                    User-submitted code: <strong><?php echo htmlspecialchars($p['user_submitted_receipt'] ?? '—'); ?></strong><br>
                    <?php echo date('d M Y H:i', strtotime($p['created_at'])); ?>
                </div>

                <?php if ($p['purpose'] === 'driver_wallet_topup'): ?>
                    <div style="margin:12px 0;">
                        <label style="color:var(--gray); display:block; margin-bottom:6px; font-size:0.85rem;">Amount to credit (KES)</label>
                        <input type="number" class="lux-payment-amount-override" value="<?php echo htmlspecialchars((string) $p['amount']); ?>"
                               style="width:140px; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.15); color:white; padding:8px 10px; border-radius:8px;">
                    </div>
                <?php endif; ?>

                <div class="lux-entity-actions">
                    <button class="lux-btn lux-btn-success" data-action="approve" data-payment-id="<?php echo (int) $p['id']; ?>">Approve</button>
                    <button class="lux-btn lux-btn-danger" data-action="reject" data-payment-id="<?php echo (int) $p['id']; ?>">Reject</button>
                </div>
            </div>
        <?php endforeach; ?>

    </div>

    <h2 style="color:white; margin:40px 0 15px;">Refunds Needing Attention (<?php echo count($refundQueue); ?>)</h2>

    <div class="lux-card-grid" id="luxRefundGrid">

        <?php if (empty($refundQueue)): ?>
            <p style="color:var(--gray);">No open refunds.</p>
        <?php endif; ?>

        <?php foreach ($refundQueue as $r): ?>
            <div class="lux-entity-card" data-refund-card="<?php echo (int) $r['id']; ?>">
                <div class="lux-entity-card-header">
                    <div>
                        <div class="lux-entity-name">KES <?php echo number_format((float) $r['amount']); ?> — <?php echo htmlspecialchars($r['reason']); ?></div>
                        <div class="lux-entity-meta"><?php echo htmlspecialchars($r['refund_reference']); ?></div>
                    </div>
                    <span class="lux-badge <?php echo $r['status'] === 'failed' ? 'lux-badge-suspended' : 'lux-badge-pending'; ?>">
                        <?php echo ucfirst($r['status']); ?>
                    </span>
                </div>

                <div class="lux-entity-meta">
                    <?php echo htmlspecialchars($r['full_name']); ?> (<?php echo htmlspecialchars($r['email']); ?>)<br>
                    Phone: <?php echo htmlspecialchars($r['phone']); ?> · Attempts: <?php echo (int) $r['attempts']; ?><br>
                    <?php if (!empty($r['last_error'])): ?>
                        Error: <?php echo htmlspecialchars($r['last_error']); ?><br>
                    <?php endif; ?>
                    <?php echo date('d M Y H:i', strtotime($r['created_at'])); ?>
                </div>

                <div class="lux-entity-actions">
                    <button class="lux-btn lux-btn-success" data-action="complete_refund" data-refund-id="<?php echo (int) $r['id']; ?>">Mark Refunded</button>
                </div>
            </div>
        <?php endforeach; ?>

    </div>

    <h2 style="color:white; margin:40px 0 15px;">Recent Payments</h2>

    <div class="lux-card-grid">
        <?php foreach ($recent as $p): ?>
            <?php
                $badgeClass = match ($p['status']) {
                    'completed' => 'lux-badge-active',
                    'failed' => 'lux-badge-suspended',
                    default => 'lux-badge-pending',
                };
            ?>
            <div class="lux-entity-card">
                <div class="lux-entity-card-header">
                    <div>
                        <div class="lux-entity-name"><?php echo ucwords(str_replace('_', ' ', $p['purpose'])); ?> — KES <?php echo number_format((float) $p['amount']); ?></div>
                        <div class="lux-entity-meta"><?php echo htmlspecialchars($p['full_name']); ?></div>
                    </div>
                    <span class="lux-badge <?php echo $badgeClass; ?>"><?php echo ucfirst($p['status']); ?></span>
                </div>
                <div class="lux-entity-meta">
                    <?php echo htmlspecialchars($p['mpesa_receipt'] ?? 'No receipt'); ?><br>
                    <?php echo date('d M Y H:i', strtotime($p['created_at'])); ?>
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
        <div class="lux-confirm-ref-wrap" hidden>
            <input type="text" class="lux-confirm-ref-input" data-validate="mpesa_ref"
                   placeholder="M-Pesa reference (10 characters)" maxlength="10" autocomplete="off" spellcheck="false"
                   style="width:100%; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.15); color:white; padding:10px 12px; border-radius:8px; margin-bottom:10px; text-transform:uppercase;">
        </div>
        <div class="lux-confirm-reason-wrap" hidden>
            <textarea class="lux-confirm-reason-input" placeholder="Notes (required for reject)"></textarea>
        </div>
        <div class="lux-modal-actions">
            <button class="lux-btn lux-btn-ghost" data-confirm-close>Cancel</button>
            <button class="lux-btn lux-btn-danger lux-confirm-accept">Confirm</button>
        </div>
    </div>
</div>

<script src="<?php echo BASE_URL; ?>/assets/js/form-validation.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/admin/payments.js"></script>

<?php require_once '../../includes/footer.php'; ?>