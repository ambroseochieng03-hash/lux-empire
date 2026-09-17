<?php

declare(strict_types=1);

require_once '../../includes/init.php';
require_once '../../includes/auth_check.php';
requireRoleAccess('admin');

require_once '../../config/csrf.php';
require_once '../../classes/AdminTruckService.php';

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
require_once '../../includes/sidebar.php';

$page = max(1, (int) ($_GET['page'] ?? 1));

$statusFilter = $_GET['status'] ?? '';
$allowedStatuses = ['pending', 'accepted', 'in_transit', 'completed', 'cancelled'];
if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = null;
}

$perPage = 50;
$offset = ($page - 1) * $perPage;

$truckService = new AdminTruckService();
$listResult = $truckService->listRequests($statusFilter, $perPage, $offset);
$requests = $listResult['requests'];
$totalRequests = $listResult['total'];
$totalPages = max(1, (int) ceil($totalRequests / $perPage));

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
        <h1 class="lux-page-title">Logistics Operations</h1>
        <p class="lux-page-subtitle">
            Monitor truck requests across the platform. Pending
            requests can be removed with a recorded reason; accepted
            and completed trips are kept as operational history.
        </p>
    </div>

    <div class="lux-admin-toolbar">
        <select class="lux-admin-filter-select" id="luxTruckStatusFilter">
            <option value="" <?php echo $statusFilter === null ? 'selected' : ''; ?>>All Statuses</option>
            <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
            <option value="accepted" <?php echo $statusFilter === 'accepted' ? 'selected' : ''; ?>>Accepted</option>
            <option value="in_transit" <?php echo $statusFilter === 'in_transit' ? 'selected' : ''; ?>>In Transit</option>
            <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Completed</option>
            <option value="cancelled" <?php echo $statusFilter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
        </select>
    </div>

    <div class="lux-card-grid" id="luxTruckGrid">

        <?php foreach ($requests as $request): ?>
            <?php
                $statusBadgeClass = match ($request['status']) {
                    'completed' => 'lux-badge-verified',
                    'in_transit' => 'lux-badge-active',
                    'cancelled' => 'lux-badge-suspended',
                    default => 'lux-badge-pending',
                };
            ?>

            <div class="lux-entity-card" data-status="<?php echo htmlspecialchars($request['status']); ?>" data-truck-card="<?php echo (int) $request['id']; ?>">

                <div class="lux-entity-card-header">
                    <div>
                        <div class="lux-entity-name"><?php echo htmlspecialchars($request['pickup_location']); ?></div>
                        <div class="lux-entity-meta">&#8594; <?php echo htmlspecialchars($request['destination']); ?></div>
                    </div>
                    <span class="lux-badge <?php echo $statusBadgeClass; ?>">
                        <?php echo ucfirst(str_replace('_', ' ', $request['status'])); ?>
                    </span>
                </div>

                <div class="lux-entity-meta">
                    Tenant: <?php echo htmlspecialchars($request['tenant_name']); ?> (<?php echo htmlspecialchars($request['tenant_phone']); ?>)<br>
                    Driver: <?php echo $request['driver_name'] ? htmlspecialchars($request['driver_name']) : 'Unassigned'; ?><br>
                    KES <?php echo number_format((float) $request['price']); ?> &middot; <?php echo date('d M Y H:i', strtotime($request['requested_at'])); ?>
                </div>

                <?php if ($request['status'] === 'pending'): ?>
                <div class="lux-entity-actions">
                    <button class="lux-btn lux-btn-outline-danger" data-action="delete" data-truck-id="<?php echo (int) $request['id']; ?>">
                        Delete
                    </button>
                </div>
                <?php endif; ?>

            </div>

        <?php endforeach; ?>

    </div>

    <?php if ($totalPages > 1): ?>
    <div style="display:flex; align-items:center; justify-content:center; gap:16px; margin-top:30px;">
        <?php if ($page > 1): ?>
            <a class="lux-btn lux-btn-ghost" href="?page=<?php echo $page - 1; ?><?php echo $statusFilter ? '&status=' . urlencode($statusFilter) : ''; ?>">&laquo; Previous</a>
        <?php endif; ?>

        <span style="color:var(--gray);">Page <?php echo $page; ?> of <?php echo $totalPages; ?> (<?php echo $totalRequests; ?> requests)</span>

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
<script src="<?php echo BASE_URL; ?>/assets/js/admin/truck-requests.js"></script>

<?php require_once '../../includes/footer.php'; ?>