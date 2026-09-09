<?php
declare(strict_types=1);

require_once '../../includes/init.php';
require_once '../../includes/auth_check.php';
requireRoleAccess('admin');

require_once '../../config/csrf.php';
require_once '../../classes/AdminEmergencyService.php';

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
require_once '../../includes/sidebar.php';

$emergencyService = new AdminEmergencyService();
$alerts = $emergencyService->listAlerts();

$csrfToken = Csrf::token();

$activeCount = 0;
$respondingCount = 0;
$resolvedCount = 0;

foreach ($alerts as $a) {
    if ($a['status'] === 'active') $activeCount++;
    if ($a['status'] === 'responding') $respondingCount++;
    if ($a['status'] === 'resolved') $resolvedCount++;
}
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/emergency.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin-cards.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin-emergency.css">

<script>
    window.LUX_ADMIN = {
        csrfToken: "<?php echo htmlspecialchars($csrfToken, ENT_QUOTES); ?>",
        baseUrl: "<?php echo BASE_URL; ?>"
    };
</script>

<div class="emergency-layout">

<main class="emergency-main">

    <div class="lux-page-header">
        <h1 class="lux-page-title">Emergency Control Center</h1>
        <p class="lux-page-subtitle">
            Live monitoring of all emergency alerts from tenants and drivers.
            Respond immediately to critical incidents across the platform.
        </p>
    </div>

    <div class="lux-emergency-stats-bar">
        <div class="lux-emergency-stat lux-emergency-stat-active">Active: <?= $activeCount ?></div>
        <div class="lux-emergency-stat lux-emergency-stat-responding">Responding: <?= $respondingCount ?></div>
        <div class="lux-emergency-stat lux-emergency-stat-resolved">Resolved: <?= $resolvedCount ?></div>
    </div>

    <div class="emergency-grid" id="luxEmergencyGrid">

        <?php if (count($alerts) === 0): ?>
            <div class="lux-entity-meta">No emergency alerts found.</div>
        <?php endif; ?>

        <?php foreach ($alerts as $alert): ?>
            <?php
                $statusClass = 'lux-emergency-status-' . $alert['status'];
                $borderClass = 'lux-emergency-border-' . $alert['status'];
            ?>

            <div class="emergency-card <?= $borderClass ?>" data-alert-card="<?= (int) $alert['id'] ?>">

                <div class="lux-entity-name"><?= htmlspecialchars($alert['full_name']) ?></div>
                <div class="lux-entity-meta">
                    <?= ucfirst($alert['role']) ?> &bull; <?= htmlspecialchars($alert['phone']) ?>
                </div>

                <div class="lux-emergency-message">
                    <?= htmlspecialchars($alert['message']) ?>
                </div>

                <div class="lux-entity-meta">
                    <?php if ($alert['trip_id']): ?>Trip ID: #<?= (int) $alert['trip_id'] ?><br><?php endif; ?>
                    <?php if ($alert['booking_id']): ?>Booking ID: #<?= (int) $alert['booking_id'] ?><br><?php endif; ?>
                    <?= date("d M Y H:i", strtotime($alert['created_at'])) ?>
                </div>

                <?php if ($alert['trip_id']): ?>
                <div class="lux-intelligence-box">
                    <div class="lux-intelligence-heading">Live Trip Intelligence</div>
                    <div class="lux-entity-name">Status: <?= ucfirst(str_replace('_', ' ', $alert['trip_status'] ?? 'unknown')) ?></div>
                    <div class="lux-entity-meta">Pickup: <?= htmlspecialchars($alert['pickup_location'] ?? 'Unknown') ?></div>
                    <div class="lux-entity-meta">Destination: <?= htmlspecialchars($alert['destination'] ?? 'Unknown') ?></div>
                    <hr class="lux-intelligence-divider">
                    <div class="lux-intelligence-subheading lux-intelligence-tenant">Tenant Location</div>
                    <div class="lux-entity-meta">Lat: <?= htmlspecialchars($alert['tenant_latitude'] ?? 'N/A') ?> &nbsp; Long: <?= htmlspecialchars($alert['tenant_longitude'] ?? 'N/A') ?></div>
                    <hr class="lux-intelligence-divider">
                    <div class="lux-intelligence-subheading lux-intelligence-driver">Driver Intelligence</div>
                    <div class="lux-entity-name"><?= htmlspecialchars($alert['driver_name'] ?? 'No driver assigned') ?></div>
                    <div class="lux-entity-meta"><?= htmlspecialchars($alert['driver_phone'] ?? 'N/A') ?></div>
                    <div class="lux-entity-meta">Lat: <?= htmlspecialchars($alert['driver_latitude'] ?? 'N/A') ?> &nbsp; Long: <?= htmlspecialchars($alert['driver_longitude'] ?? 'N/A') ?></div>
                </div>
                <?php endif; ?>

                <span class="lux-badge <?= $statusClass ?>" data-status-badge><?= ucfirst($alert['status']) ?></span>

                <div class="lux-entity-actions">
                    <button class="lux-btn lux-btn-warning" data-action="responding" data-alert-id="<?= (int) $alert['id'] ?>">Respond</button>
                    <button class="lux-btn lux-btn-success" data-action="resolved" data-alert-id="<?= (int) $alert['id'] ?>">Resolve</button>
                    <button class="lux-btn lux-btn-danger" data-action="dismissed" data-alert-id="<?= (int) $alert['id'] ?>">Dismiss</button>
                    <button class="lux-btn lux-btn-outline-danger" data-action="delete" data-alert-id="<?= (int) $alert['id'] ?>">Delete Permanently</button>
                </div>

            </div>

        <?php endforeach; ?>

    </div>

</main>

</div>

<?php require __DIR__ . '/../../includes/admin/confirm_modal.php'; ?>

<script src="<?= BASE_URL ?>/assets/js/admin/admin-core.js"></script>
<script src="<?= BASE_URL ?>/assets/js/admin/emergency.js"></script>

<?php require_once '../../includes/footer.php'; ?>