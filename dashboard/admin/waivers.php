<?php

declare(strict_types=1);

require_once '../../includes/init.php';
require_once '../../includes/auth_check.php';
requireRoleAccess('admin');

require_once '../../config/csrf.php';
require_once '../../classes/PaymentWaiver.php';

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
require_once '../../includes/sidebar.php';

$statusFilter = $_GET['status'] ?? '';
$allowedStatuses = ['active', 'in_use', 'used', 'expired', 'revoked'];

if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = null;
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 30;

$result = PaymentWaiver::listForAdmin($statusFilter, $perPage, ($page - 1) * $perPage);
$waivers = $result['waivers'];
$total = $result['total'];
$totalPages = max(1, (int) ceil($total / $perPage));

$statusLabels = [
    'active' => 'Active',
    'in_use' => 'In use',
    'used' => 'Used',
    'expired' => 'Expired',
    'revoked' => 'Revoked',
];

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
        <h1 class="lux-page-title">Waivers</h1>
        <p class="lux-page-subtitle">
            Single-use vouchers. A tenant voucher is one free booking. A landlord voucher is a free Pro plan
            until it expires. Nothing on this page is ever counted as a payment.
        </p>
    </div>

    <div class="lux-card" style="padding:30px; border-radius:24px; max-width:700px; margin-bottom:40px;">

        <form id="luxWaiverForm">

            <div style="margin-bottom:20px;">
                <label style="color:var(--gray); display:block; margin-bottom:8px;">Grant to</label>
                <select class="lux-admin-filter-select" id="luxWaiverTarget" style="width:100%;">
                    <option value="user">A specific person (by email)</option>
                    <option value="role:tenant">Every active tenant (one free booking each)</option>
                    <option value="role:landlord">Every active landlord (free Pro plan each)</option>
                </select>
            </div>

            <div style="margin-bottom:20px;" id="luxWaiverEmailWrap">
                <label style="color:var(--gray); display:block; margin-bottom:8px;">Email</label>
                <input type="email" id="luxWaiverEmail"
                       style="width:100%; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.15); color:white; padding:12px 14px; border-radius:12px;">
                <small style="color:var(--gray);">The voucher type follows the account: tenants get a free booking, landlords get a free Pro plan.</small>
            </div>

            <div style="margin-bottom:20px; display:flex; gap:12px;">
                <div style="flex:1;">
                    <label style="color:var(--gray); display:block; margin-bottom:8px;">Valid for</label>
                    <input type="number" id="luxWaiverAmount" value="30" min="1" max="720"
                           style="width:100%; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.15); color:white; padding:12px 14px; border-radius:12px;">
                </div>
                <div style="flex:1;">
                    <label style="color:var(--gray); display:block; margin-bottom:8px;">Unit</label>
                    <select class="lux-admin-filter-select" id="luxWaiverUnit" style="width:100%;">
                        <option value="hours">Hours (up to 720)</option>
                        <option value="days" selected>Days (up to 365)</option>
                        <option value="weeks">Weeks (up to 52)</option>
                    </select>
                </div>
            </div>

            <div style="margin-bottom:24px;">
                <label style="color:var(--gray); display:block; margin-bottom:8px;">Reason (required)</label>
                <textarea id="luxWaiverReason" maxlength="500"
                          style="width:100%; min-height:80px; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.15); color:white; padding:12px 14px; border-radius:12px; resize:vertical;"></textarea>
            </div>

            <button type="submit" class="lux-btn lux-btn-success">Grant Voucher</button>

        </form>

    </div>

    <div class="lux-admin-toolbar">
        <select class="lux-admin-filter-select" id="luxWaiverStatusFilter">
            <option value="" <?php echo $statusFilter === null ? 'selected' : ''; ?>>All vouchers</option>
            <?php foreach ($statusLabels as $value => $label): ?>
                <option value="<?php echo $value; ?>" <?php echo $statusFilter === $value ? 'selected' : ''; ?>><?php echo $label; ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="lux-card-grid" id="luxWaiverGrid">

        <?php if (empty($waivers)): ?>
            <p style="color:var(--gray);">No vouchers match this filter.</p>
        <?php endif; ?>

        <?php foreach ($waivers as $w): ?>
            <?php
                $effective = $w['effective_status'];

                $badgeClass = match ($effective) {
                    'active' => 'lux-badge-active',
                    'in_use' => 'lux-badge-pending',
                    'used' => 'lux-badge-verified',
                    default => 'lux-badge-suspended',
                };
            ?>

            <div class="lux-entity-card" data-waiver-card="<?php echo (int) $w['id']; ?>">

                <div class="lux-entity-card-header">
                    <div>
                        <div class="lux-entity-name"><?php echo htmlspecialchars($w['full_name']); ?></div>
                        <div class="lux-entity-meta"><?php echo htmlspecialchars($w['email']); ?></div>
                    </div>
                    <span class="lux-badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($statusLabels[$effective] ?? $effective); ?></span>
                </div>

                <div class="lux-entity-meta">
                    <strong><?php echo htmlspecialchars(PaymentWaiver::kindLabel($w['kind'])); ?></strong><br>
                    <?php if ($w['kind'] === PaymentWaiver::KIND_TENANT): ?>
                        Attempts used: <?php echo (int) $w['attempts_used']; ?> of <?php echo (int) $w['max_attempts']; ?><br>
                    <?php endif; ?>
                    Expires <?php echo date('d M Y, H:i', strtotime($w['expires_at'])); ?><br>
                    <?php echo htmlspecialchars($w['reason'] ?: 'No reason given'); ?><br>
                    Granted by <?php echo htmlspecialchars($w['granted_by_name'] ?? 'Unknown'); ?>
                    <?php if (!empty($w['batch_label'])): ?>
                        <br><small>Promotion: <?php echo htmlspecialchars($w['batch_label']); ?></small>
                    <?php endif; ?>
                </div>

                <div class="lux-entity-actions">
                    <button class="lux-btn lux-btn-ghost" data-action="history" data-waiver-id="<?php echo (int) $w['id']; ?>" data-waiver-name="<?php echo htmlspecialchars($w['full_name'], ENT_QUOTES); ?>">
                        History
                    </button>

                    <?php if ($effective === 'active'): ?>
                        <button class="lux-btn lux-btn-outline-danger" data-action="revoke" data-waiver-id="<?php echo (int) $w['id']; ?>">
                            Revoke
                        </button>
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

        <span style="color:var(--gray);">Page <?php echo $page; ?> of <?php echo $totalPages; ?> (<?php echo $total; ?> vouchers)</span>

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

<div class="lux-modal-overlay" id="luxWaiverHistoryModal" aria-hidden="true">
    <div class="lux-modal-box lux-modal-wide">
        <h3 class="lux-confirm-title" id="luxWaiverHistoryTitle">Voucher history</h3>
        <div id="luxWaiverHistoryBody"></div>
        <div class="lux-modal-actions">
            <button class="lux-btn lux-btn-ghost" data-history-close>Close</button>
        </div>
    </div>
</div>

<script src="<?php echo BASE_URL; ?>/assets/js/admin/admin-core.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/admin/waivers.js"></script>

<?php require_once '../../includes/footer.php'; ?>