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

$activeWaivers = PaymentWaiver::listActive();
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
        <h1 class="lux-page-title">Payment Waivers</h1>
        <p class="lux-page-subtitle">
            Grant complimentary access to an individual by email, or to
            every user of a role, for a set duration.
        </p>
    </div>

    <div class="lux-card" style="padding:30px; border-radius:24px; max-width:700px; margin-bottom:40px;">

        <form id="luxWaiverForm">

            <div style="margin-bottom:20px;">
                <label style="color:var(--gray); display:block; margin-bottom:8px;">Grant to</label>
                <select class="lux-admin-filter-select" id="luxWaiverScope" style="width:100%;">
                    <option value="user">A specific person (by email)</option>
                    <option value="role:tenant">All Tenants</option>
                    <option value="role:landlord">All Landlords</option>
                    <option value="role:driver">All Drivers</option>
                    <option value="role:all">All Roles</option>
                </select>
            </div>

            <div style="margin-bottom:20px;" id="luxWaiverEmailWrap">
                <label style="color:var(--gray); display:block; margin-bottom:8px;">Email</label>
                <input type="email" id="luxWaiverEmail"
                       style="width:100%; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.15); color:white; padding:12px 14px; border-radius:12px;">
            </div>

            <div style="margin-bottom:20px;">
                <label style="color:var(--gray); display:block; margin-bottom:8px;">Duration (days)</label>
                <input type="number" id="luxWaiverDays" value="30" min="1" max="365"
                       style="width:100%; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.15); color:white; padding:12px 14px; border-radius:12px;">
            </div>

            <div style="margin-bottom:24px;">
                <label style="color:var(--gray); display:block; margin-bottom:8px;">Reason</label>
                <textarea id="luxWaiverReason" maxlength="500"
                          style="width:100%; min-height:80px; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.15); color:white; padding:12px 14px; border-radius:12px; resize:vertical;"></textarea>
            </div>

            <button type="submit" class="lux-btn lux-btn-success">Grant Waiver</button>

        </form>

    </div>

    <h2 style="color:white; margin-bottom:15px;">Active Waivers</h2>

    <div class="lux-card-grid" id="luxWaiverGrid">

        <?php if (empty($activeWaivers)): ?>
            <p style="color:var(--gray);">No active waivers.</p>
        <?php endif; ?>

        <?php foreach ($activeWaivers as $w): ?>
            <div class="lux-entity-card" data-waiver-card="<?php echo (int) $w['id']; ?>">
                <div class="lux-entity-card-header">
                    <div>
                        <div class="lux-entity-name">
                            <?php echo $w['scope'] === 'user'
                                ? htmlspecialchars($w['full_name'] ?? 'Unknown user')
                                : 'All ' . ucfirst($w['role']) . 's'; ?>
                        </div>
                        <div class="lux-entity-meta">
                            Expires <?php echo date('d M Y', strtotime($w['expires_at'])); ?>
                        </div>
                    </div>
                    <span class="lux-badge lux-badge-active">Active</span>
                </div>

                <div class="lux-entity-meta">
                    <?php echo htmlspecialchars($w['reason'] ?: 'No reason given'); ?><br>
                    Granted by <?php echo htmlspecialchars($w['granted_by_name']); ?>
                </div>

                <div class="lux-entity-actions">
                    <button class="lux-btn lux-btn-outline-danger" data-action="revoke" data-waiver-id="<?php echo (int) $w['id']; ?>">
                        Revoke
                    </button>
                </div>
            </div>
        <?php endforeach; ?>

    </div>

</main>

</div>

<script src="<?php echo BASE_URL; ?>/assets/js/admin/waivers.js"></script>

<?php require_once '../../includes/footer.php'; ?>
