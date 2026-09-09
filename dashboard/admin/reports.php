<?php
declare(strict_types=1);

require_once '../../includes/init.php';
require_once '../../includes/auth_check.php';
requireRoleAccess('admin');

require_once '../../classes/AdminReportService.php';

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
require_once '../../includes/sidebar.php';

$db = new Database();
$pdo = $db->connect();

$reportService = new AdminReportService();
$moderation = $reportService->getModerationSummary();
$recentActions = $reportService->getRecentAdminActions();

// =====================================
// USERS STATS
// =====================================
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

$rolesStmt = $pdo->query("SELECT role, COUNT(*) as total FROM users GROUP BY role");
$roles = $rolesStmt->fetchAll();

// =====================================
// HOUSING STATS
// =====================================
$totalHouses = $pdo->query("SELECT COUNT(*) FROM houses")->fetchColumn();

$houseStatusStmt = $pdo->query("SELECT status, COUNT(*) as total FROM houses GROUP BY status");
$houseStatuses = $houseStatusStmt->fetchAll();

// =====================================
// LOGISTICS STATS
// =====================================
$totalRequests = $pdo->query("SELECT COUNT(*) FROM truck_requests")->fetchColumn();

$completedTrips = $pdo->query("SELECT COUNT(*) FROM truck_requests WHERE status = 'completed'")->fetchColumn();
$pendingTrips = $pdo->query("SELECT COUNT(*) FROM truck_requests WHERE status = 'pending'")->fetchColumn();
$activeTrips = $pdo->query("SELECT COUNT(*) FROM truck_requests WHERE status = 'in_transit'")->fetchColumn();

$actionTypeLabels = [
    'delete_user' => 'Deleted user',
    'delete_listing' => 'Deleted listing',
    'delete_booking' => 'Deleted booking',
    'delete_truck_request' => 'Deleted truck request',
    'delete_emergency_alert' => 'Deleted emergency alert',
];
?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/admin.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/admin-cards.css">

<div class="lux-dashboard-layout">

<main class="lux-dashboard-main">

    <div class="lux-page-header">
        <h1 class="lux-page-title">Empire Analytics</h1>
        <p class="lux-page-subtitle">
            Real-time overview of LUX EMPIRE operations, moderation
            queue health, and recent administrative activity.
        </p>
    </div>

    <!-- MODERATION QUEUE HEALTH -->
    <div class="lux-stats-grid">

        <div class="lux-card" style="padding:25px; border-radius:25px;">
            <h3 style="color:var(--gray);">Flagged Users</h3>
            <div style="color:#ff4d4d; font-size:2.3rem; font-weight:bold;"><?= $moderation['flagged_users'] ?></div>
        </div>

        <div class="lux-card" style="padding:25px; border-radius:25px;">
            <h3 style="color:var(--gray);">Flagged Listings</h3>
            <div style="color:#ff4d4d; font-size:2.3rem; font-weight:bold;"><?= $moderation['flagged_listings'] ?></div>
        </div>

        <div class="lux-card" style="padding:25px; border-radius:25px;">
            <h3 style="color:var(--gray);">Hidden Listings</h3>
            <div style="color:#ffae42; font-size:2.3rem; font-weight:bold;"><?= $moderation['hidden_listings'] ?></div>
        </div>

        <div class="lux-card" style="padding:25px; border-radius:25px;">
            <h3 style="color:var(--gray);">Active Emergencies</h3>
            <div style="color:#ff4d4d; font-size:2.3rem; font-weight:bold;"><?= $moderation['active_emergencies'] ?></div>
        </div>

        <div class="lux-card" style="padding:25px; border-radius:25px;">
            <h3 style="color:var(--gray);">Landlords Awaiting Verification</h3>
            <div style="color:#4da6ff; font-size:2.3rem; font-weight:bold;"><?= $moderation['pending_landlord_verification'] ?></div>
        </div>

        <div class="lux-card" style="padding:25px; border-radius:25px;">
            <h3 style="color:var(--gray);">Drivers Awaiting Verification</h3>
            <div style="color:#4da6ff; font-size:2.3rem; font-weight:bold;"><?= $moderation['pending_driver_verification'] ?></div>
        </div>

        <div class="lux-card" style="padding:25px; border-radius:25px;">
            <h3 style="color:var(--gray);">Suspended Users</h3>
            <div style="color:#ff4d4d; font-size:2.3rem; font-weight:bold;"><?= $moderation['suspended_users'] ?></div>
        </div>

    </div>

    <!-- PLATFORM TOTALS -->
    <div class="lux-analytics-grid">

        <div class="lux-card" style="padding:30px; border-radius:30px;">
            <h2 style="color:white; margin-bottom:20px;">Users by Role</h2>
            <?php foreach ($roles as $r): ?>
                <div style="margin-bottom:15px;">
                    <div style="color:white; font-weight:bold;"><?= ucfirst($r['role']) ?></div>
                    <div style="color:var(--gray);"><?= $r['total'] ?> users</div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="lux-card" style="padding:30px; border-radius:30px;">
            <h2 style="color:white; margin-bottom:20px;">House Status</h2>
            <?php foreach ($houseStatuses as $h): ?>
                <div style="margin-bottom:15px;">
                    <div style="color:white; font-weight:bold;"><?= ucfirst($h['status']) ?></div>
                    <div style="color:var(--gray);"><?= $h['total'] ?> listings</div>
                </div>
            <?php endforeach; ?>
        </div>

    </div>

    <!-- LOGISTICS BREAKDOWN -->
    <div class="lux-card lux-logistics-card">
        <h2 style="color:white; margin-bottom:25px;">Logistics Overview</h2>
        <div class="lux-logistics-grid">
            <div>
                <div style="color:var(--gray);">Pending</div>
                <div style="color:#ffae42; font-size:2rem;"><?= $pendingTrips ?></div>
            </div>
            <div>
                <div style="color:var(--gray);">Active</div>
                <div style="color:#4da6ff; font-size:2rem;"><?= $activeTrips ?></div>
            </div>
            <div>
                <div style="color:var(--gray);">Completed</div>
                <div style="color:#00cc66; font-size:2rem;"><?= $completedTrips ?></div>
            </div>
        </div>
    </div>

    <!-- RECENT ADMIN ACTIONS -->
    <div class="lux-card" style="padding:30px; border-radius:30px; margin-top:30px;">
        <h2 style="color:white; margin-bottom:20px;">Recent Administrative Actions</h2>

        <?php if (empty($recentActions)): ?>
            <p style="color:var(--gray);">No recorded actions yet.</p>
        <?php else: ?>
            <?php foreach ($recentActions as $action): ?>
                <div style="padding:16px 0; border-bottom:1px solid rgba(255,255,255,0.08);">
                    <div style="color:white; font-weight:bold;">
                        <?= htmlspecialchars($actionTypeLabels[$action['action_type']] ?? $action['action_type']) ?>
                        <span style="color:var(--gray); font-weight:normal;">
                            (<?= htmlspecialchars($action['target_table']) ?> #<?= (int) $action['target_id'] ?>)
                        </span>
                    </div>
                    <div style="color:var(--gray); margin-top:4px; font-size:0.9rem;">
                        by <?= htmlspecialchars($action['admin_name']) ?> &middot; <?= date('d M Y H:i', strtotime($action['created_at'])) ?>
                    </div>
                    <div style="color:var(--gold); margin-top:6px; font-size:0.9rem; word-break:break-word;">
                        "<?= htmlspecialchars($action['reason']) ?>"
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

    </div>

</main>

</div>

<?php require_once '../../includes/footer.php'; ?>