<?php

declare(strict_types=1);

require_once '../../includes/init.php';
require_once '../../includes/auth_check.php';
requireRoleAccess('admin');

require_once '../../config/csrf.php';
require_once '../../classes/AdminUserService.php';

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
require_once '../../includes/sidebar.php';

$page = max(1, (int) ($_GET['page'] ?? 1));

$roleFilter = $_GET['role'] ?? '';
$allowedRoles = ['tenant', 'landlord', 'driver', 'admin'];
if (!in_array($roleFilter, $allowedRoles, true)) {
    $roleFilter = null;
}

$perPage = 50;
$offset = ($page - 1) * $perPage;

$adminUserService = new AdminUserService();
$listResult = $adminUserService->listUsers($roleFilter, $perPage, $offset);
$users = $listResult['users'];
$totalUsers = $listResult['total'];
$totalPages = max(1, (int) ceil($totalUsers / $perPage));

$csrfToken = Csrf::token();
?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/admin.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/admin-cards.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/property-media.css">

<script>
    window.LUX_ADMIN = {
        csrfToken: "<?php echo htmlspecialchars($csrfToken, ENT_QUOTES); ?>",
        baseUrl: "<?php echo BASE_URL; ?>"
    };
</script>

<div class="lux-dashboard-layout">

<main class="lux-dashboard-main">

    <div class="lux-page-header">
        <h1 class="lux-page-title">User Management</h1>
        <p class="lux-page-subtitle">
            Manage platform members, monitor roles, supervise account
            status, and oversee the LUX EMPIRE ecosystem.
        </p>
    </div>

    <div class="lux-admin-toolbar">
        <select class="lux-admin-filter-select" id="luxUserRoleFilter">
            <option value="" <?php echo $roleFilter === null ? 'selected' : ''; ?>>All Roles</option>
            <option value="tenant" <?php echo $roleFilter === 'tenant' ? 'selected' : ''; ?>>Tenants</option>
            <option value="landlord" <?php echo $roleFilter === 'landlord' ? 'selected' : ''; ?>>Landlords</option>
            <option value="driver" <?php echo $roleFilter === 'driver' ? 'selected' : ''; ?>>Drivers</option>
            <option value="admin" <?php echo $roleFilter === 'admin' ? 'selected' : ''; ?>>Admins</option>
        </select>
    </div>

    <div class="lux-card-grid" id="luxUserGrid">

        <?php foreach ($users as $user): ?>
            <?php
                $statusBadgeClass = match ($user['status']) {
                    'active' => 'lux-badge-active',
                    'suspended' => 'lux-badge-suspended',
                    default => 'lux-badge-pending',
                };

                $cardClasses = 'lux-entity-card';
                if (!empty($user['is_flagged'])) {
                    $cardClasses .= ' is-flagged';
                }
            ?>

            <div class="<?php echo $cardClasses; ?>"
                 data-role="<?php echo htmlspecialchars($user['role']); ?>"
                 data-user-card="<?php echo (int) $user['id']; ?>">

                <div class="lux-entity-card-header">
                    <div>
                        <div class="lux-entity-name"><?php echo htmlspecialchars($user['full_name']); ?></div>
                        <div class="lux-entity-meta">
                            <?php echo htmlspecialchars($user['email']); ?><br>
                            <?php echo htmlspecialchars($user['phone'] ?? 'No phone'); ?>
                        </div>
                    </div>
                    <span class="lux-badge lux-badge-role"><?php echo ucfirst($user['role']); ?></span>
                </div>

                <div>
                    <span class="lux-badge <?php echo $statusBadgeClass; ?>" data-status-badge>
                        <?php echo ucfirst($user['status']); ?>
                    </span>

                    <?php if (!empty($user['is_flagged'])): ?>
                        <span class="lux-badge lux-badge-flagged" data-flag-badge>Flagged</span>
                    <?php endif; ?>

                    <?php if (in_array($user['role'], ['landlord', 'driver'], true) && !empty($user['verified_at'])): ?>
                        <span class="lux-badge lux-badge-verified" data-verified-badge>Verified</span>
                    <?php endif; ?>
                </div>

                <div class="lux-entity-meta">
                    Joined <?php echo date('d M Y', strtotime($user['created_at'])); ?>
                </div>

                <?php if ($user['role'] !== 'admin'): ?>

                <div class="lux-entity-actions">

                    <?php if ($user['status'] === 'active'): ?>
                        <button class="lux-btn lux-btn-danger" data-action="suspend" data-user-id="<?php echo (int) $user['id']; ?>">
                            Suspend
                        </button>
                    <?php else: ?>
                        <button class="lux-btn lux-btn-success" data-action="activate" data-user-id="<?php echo (int) $user['id']; ?>">
                            Activate
                        </button>
                    <?php endif; ?>

                    <?php if (!empty($user['is_flagged'])): ?>
                        <button class="lux-btn lux-btn-ghost" data-action="unflag" data-user-id="<?php echo (int) $user['id']; ?>">
                            Unflag
                        </button>
                    <?php else: ?>
                        <button class="lux-btn lux-btn-warning" data-action="flag" data-user-id="<?php echo (int) $user['id']; ?>">
                            Flag
                        </button>
                    <?php endif; ?>

                    <?php if (in_array($user['role'], ['landlord', 'driver'], true) && empty($user['verified_at'])): ?>
                        <button class="lux-btn lux-btn-info" data-action="verify" data-user-id="<?php echo (int) $user['id']; ?>">
                            Verify
                        </button>
                    <?php endif; ?>

                    <?php if (in_array($user['role'], ['landlord', 'driver'], true)): ?>
                        <button class="lux-btn lux-btn-ghost" data-action="reveal-identity" data-user-id="<?php echo (int) $user['id']; ?>">
                            Reveal Identity
                        </button>
                    <?php endif; ?>

                    <?php if ($user['role'] === 'landlord'): ?>
                        <button class="lux-btn lux-btn-ghost" data-action="view-listings"
                                data-landlord-id="<?php echo (int) $user['id']; ?>"
                                data-landlord-name="<?php echo htmlspecialchars($user['full_name'], ENT_QUOTES); ?>">
                            View Listings
                        </button>
                    <?php endif; ?>

                    <button class="lux-btn lux-btn-outline-danger" data-action="delete" data-user-id="<?php echo (int) $user['id']; ?>">
                        Delete
                    </button>

                    <button class="lux-btn lux-btn-info" data-action="message" data-user-id="<?php echo (int) $user['id']; ?>">
                        Message
                    </button>

                    <?php if (in_array($user['role'], ['landlord', 'driver'], true)): ?>
                        <div class="lux-identity-panel" id="luxIdentityPanel-<?php echo (int) $user['id']; ?>" hidden></div>
                    <?php endif; ?>

                </div>

                <?php endif; ?>

            </div>

        <?php endforeach; ?>

    </div>

    <?php if ($totalPages > 1): ?>
    <div style="display:flex; align-items:center; justify-content:center; gap:16px; margin-top:30px;">
        <?php if ($page > 1): ?>
            <a class="lux-btn lux-btn-ghost" href="?page=<?php echo $page - 1; ?><?php echo $roleFilter ? '&role=' . urlencode($roleFilter) : ''; ?>">&laquo; Previous</a>
        <?php endif; ?>

        <span style="color:var(--gray);">Page <?php echo $page; ?> of <?php echo $totalPages; ?> (<?php echo $totalUsers; ?> users)</span>

        <?php if ($page < $totalPages): ?>
            <a class="lux-btn lux-btn-ghost" href="?page=<?php echo $page + 1; ?><?php echo $roleFilter ? '&role=' . urlencode($roleFilter) : ''; ?>">Next &raquo;</a>
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

<div class="lux-modal-overlay" id="luxLandlordListingsModal" aria-hidden="true">
    <div class="lux-modal-box lux-modal-wide">
        <h3 class="lux-confirm-title" id="luxLandlordListingsTitle">Listings</h3>
        <div class="lux-landlord-listings-grid" id="luxLandlordListingsGrid"></div>
        <div class="lux-modal-actions">
            <button class="lux-btn lux-btn-ghost" data-landlord-modal-close>Close</button>
        </div>
    </div>
</div>

<div class="lux-modal-overlay" id="luxDirectMessageModal" aria-hidden="true">
    <div class="lux-modal-box">
        <h3 class="lux-confirm-title">Send a message</h3>
        <input type="text" id="luxDmSubject" placeholder="Subject" maxlength="150"
               style="width:100%; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.15); color:white; padding:12px 14px; border-radius:12px; margin-bottom:14px;">
        <textarea id="luxDmBody" placeholder="Message" maxlength="5000"
                  style="width:100%; min-height:140px; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.15); color:white; padding:12px 14px; border-radius:12px; resize:vertical; margin-bottom:18px;"></textarea>
        <div class="lux-modal-actions">
            <button class="lux-btn lux-btn-ghost" data-dm-close>Cancel</button>
            <button class="lux-btn lux-btn-success" id="luxDmSend">Send</button>
        </div>
    </div>
</div>

<div id="mediaLightbox" class="media-lightbox" aria-hidden="true">
    <div class="media-lightbox-overlay" data-media-close></div>
    <div class="media-lightbox-content">
        <button class="media-lightbox-close" data-media-close>&times;</button>
        <button class="media-lightbox-nav media-lightbox-prev">&#8249;</button>
        <div class="media-lightbox-stage">
            <img class="media-lightbox-image" alt="">
        </div>
        <button class="media-lightbox-nav media-lightbox-next">&#8250;</button>
        <div class="media-lightbox-counter"></div>
    </div>
</div>

<script src="<?php echo BASE_URL; ?>/assets/js/admin/admin-core.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/idempotency.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/admin/users.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/property-media.js"></script>

<?php require_once '../../includes/footer.php'; ?>