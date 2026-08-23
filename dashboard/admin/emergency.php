<?php
require_once '../../includes/init.php';
require_once '../../includes/auth_check.php';
requireRoleAccess('admin');

require_once '../../config/db.php';

$db = new Database();
$pdo = $db->connect();

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
require_once '../../includes/sidebar.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/emergency.css">

<?php

$stmt = $pdo->query("
    SELECT
        ea.*,

        u.full_name,
        u.phone,
        u.role,

        tr.status AS trip_status,
        tr.pickup_location,
        tr.destination,

        tenant_loc.latitude AS tenant_latitude,
        tenant_loc.longitude AS tenant_longitude,

        driver_loc.latitude AS driver_latitude,
        driver_loc.longitude AS driver_longitude,

        driver.full_name AS driver_name,
        driver.phone AS driver_phone

    FROM emergency_alerts ea

    JOIN users u
    ON ea.user_id = u.id

    LEFT JOIN truck_requests tr
    ON ea.trip_id = tr.id

    LEFT JOIN users driver
    ON tr.driver_id = driver.id

    LEFT JOIN tenant_locations tenant_loc
    ON tr.tenant_id = tenant_loc.tenant_id

    LEFT JOIN driver_locations driver_loc
    ON tr.driver_id = driver_loc.driver_id

    ORDER BY ea.created_at DESC
");

$alerts = $stmt->fetchAll();

?>

<div class="emergency-layout">

<main class="emergency-main">

    <!-- HEADER -->
    <div style="margin-bottom:35px;">

        <h1 style="
            font-size:3rem;
            color:var(--gold);
            font-family:'Cinzel', serif;
        ">
            🚨 Emergency Control Center
        </h1>

        <p style="color:var(--gray); max-width:800px;">
            Live monitoring of all emergency alerts from tenants and drivers.
            Respond immediately to critical incidents across the platform.
        </p>

    </div>

    <!-- STATS BAR -->
    <div class="emergency-stats-bar">

        <?php
            $activeCount = 0;
            $respondingCount = 0;
            $resolvedCount = 0;

            foreach ($alerts as $a) {
                if ($a['status'] === 'active') $activeCount++;
                if ($a['status'] === 'responding') $respondingCount++;
                if ($a['status'] === 'resolved') $resolvedCount++;
            }
        ?>

        <div style="
            background:#ff4d4d22;
            padding:15px 20px;
            border-radius:16px;
            color:#ff4d4d;
        ">
            Active: <?= $activeCount ?>
        </div>

        <div style="
            background:#ffae4222;
            padding:15px 20px;
            border-radius:16px;
            color:#ffae42;
        ">
            Responding: <?= $respondingCount ?>
        </div>

        <div style="
            background:#00cc6622;
            padding:15px 20px;
            border-radius:16px;
            color:#00cc66;
        ">
            Resolved: <?= $resolvedCount ?>
        </div>

    </div>

    <!-- ALERT GRID -->
    <div class="emergency-grid">

        <?php if (count($alerts) === 0): ?>

            <div style="color:var(--gray);">
                No emergency alerts found.
            </div>

        <?php endif; ?>

        <?php foreach ($alerts as $alert): ?>

            <?php
                $color = match($alert['status']) {
                    'active' => '#ff4d4d',
                    'responding' => '#ffae42',
                    'resolved' => '#00cc66',
                    default => '#999'
                };
            ?>

            <div class="emergency-card"
                 style="
                    border-left:5px solid <?= $color ?>;
                 ">

                <!-- USER INFO -->
                <div style="margin-bottom:12px;">

                    <div style="
                        color:white;
                        font-weight:bold;
                        font-size:1.1rem;
                    ">
                        <?= htmlspecialchars($alert['full_name']) ?>
                    </div>

                    <div style="
                        color:var(--gray);
                        font-size:0.9rem;
                    ">
                        <?= ucfirst($alert['role']) ?>
                        •
                        <?= htmlspecialchars($alert['phone']) ?>
                    </div>

                </div>

                <!-- MESSAGE -->
                <div style="
                    color:var(--gold);
                    font-weight:bold;
                    margin-bottom:12px;
                    word-break:break-word;
                ">
                    <?= htmlspecialchars($alert['message']) ?>
                </div>

                <!-- CONTEXT -->
                <div style="
                    color:var(--gray);
                    font-size:0.9rem;
                    margin-bottom:15px;
                    word-break:break-word;
                ">

                    <?php if ($alert['trip_id']): ?>
                        Trip ID: #<?= $alert['trip_id'] ?><br>
                    <?php endif; ?>

                    <?php if ($alert['booking_id']): ?>
                        Booking ID: #<?= $alert['booking_id'] ?><br>
                    <?php endif; ?>

                    <?= date("d M Y H:i", strtotime($alert['created_at'])) ?>

                </div>

                <?php if ($alert['trip_id']): ?>

                <div class="emergency-intelligence-box">

                    <div style="
                        color:gold;
                        font-weight:bold;
                        margin-bottom:10px;
                    ">
                        Live Trip Intelligence
                    </div>

                    <div style="color:white; margin-bottom:8px;">
                        Status:
                        <?= ucfirst(
                            str_replace(
                                '_',
                                ' ',
                                $alert['trip_status'] ?? 'unknown'
                            )
                        ) ?>
                    </div>

                    <div style="
                        color:var(--gray);
                        margin-bottom:8px;
                        word-break:break-word;
                    ">
                        Pickup:
                        <?= htmlspecialchars($alert['pickup_location'] ?? 'Unknown') ?>
                    </div>

                    <div style="
                        color:var(--gray);
                        margin-bottom:12px;
                        word-break:break-word;
                    ">
                        Destination:
                        <?= htmlspecialchars($alert['destination'] ?? 'Unknown') ?>
                    </div>

                    <hr style="
                        border:none;
                        border-top:1px solid rgba(255,255,255,0.08);
                        margin:12px 0;
                    ">

                    <div style="
                        color:#00cc66;
                        font-weight:bold;
                        margin-bottom:8px;
                    ">
                        Tenant Location
                    </div>

                    <div style="
                        color:var(--gray);
                        font-size:0.9rem;
                        word-break:break-word;
                    ">
                        Lat:
                        <?= htmlspecialchars($alert['tenant_latitude'] ?? 'N/A') ?>
                        <br>

                        Long:
                        <?= htmlspecialchars($alert['tenant_longitude'] ?? 'N/A') ?>
                    </div>

                    <hr style="
                        border:none;
                        border-top:1px solid rgba(255,255,255,0.08);
                        margin:12px 0;
                    ">

                    <div style="
                        color:#4da6ff;
                        font-weight:bold;
                        margin-bottom:8px;
                    ">
                        Driver Intelligence
                    </div>

                    <div style="
                        color:white;
                        margin-bottom:6px;
                        word-break:break-word;
                    ">
                        <?= htmlspecialchars(
                            $alert['driver_name'] ?? 'No driver assigned'
                        ) ?>
                    </div>

                    <div style="
                        color:var(--gray);
                        margin-bottom:8px;
                        word-break:break-word;
                    ">
                        <?= htmlspecialchars(
                            $alert['driver_phone'] ?? 'N/A'
                        ) ?>
                    </div>

                    <div style="
                        color:var(--gray);
                        font-size:0.9rem;
                        word-break:break-word;
                    ">
                        Lat:
                        <?= htmlspecialchars($alert['driver_latitude'] ?? 'N/A') ?>
                        <br>

                        Long:
                        <?= htmlspecialchars($alert['driver_longitude'] ?? 'N/A') ?>
                    </div>

                </div>

                <?php endif; ?>

                <!-- STATUS BADGE -->
                <div style="
                    display:inline-block;
                    padding:6px 12px;
                    border-radius:12px;
                    background:<?= $color ?>22;
                    color:<?= $color ?>;
                    font-weight:bold;
                    margin-bottom:15px;
                ">
                    <?= ucfirst($alert['status']) ?>
                </div>

                <!-- ACTIONS -->
                <div class="emergency-actions">

                    <!-- RESPOND -->
                    <form method="POST"
                          action="../../api/admin/update_emergency_status.php">

                        <input type="hidden"
                               name="id"
                               value="<?= $alert['id'] ?>">

                        <input type="hidden"
                               name="status"
                               value="responding">

                        <button style="
                            background:#ffae42;
                            border:none;
                            padding:10px 14px;
                            border-radius:12px;
                            font-weight:bold;
                            cursor:pointer;
                        ">
                            Respond
                        </button>

                    </form>

                    <!-- RESOLVE -->
                    <form method="POST"
                          action="../../api/admin/update_emergency_status.php">

                        <input type="hidden"
                               name="id"
                               value="<?= $alert['id'] ?>">

                        <input type="hidden"
                               name="status"
                               value="resolved">

                        <button style="
                            background:#00cc66;
                            color:white;
                            border:none;
                            padding:10px 14px;
                            border-radius:12px;
                            font-weight:bold;
                            cursor:pointer;
                        ">
                            Resolve
                        </button>

                    </form>

                    <!-- DISMISS -->
                    <form method="POST"
                          action="../../api/admin/update_emergency_status.php">

                        <input type="hidden"
                               name="id"
                               value="<?= $alert['id'] ?>">

                        <input type="hidden"
                               name="status"
                               value="dismissed">

                        <button style="
                            background:#ff4d4d;
                            color:white;
                            border:none;
                            padding:10px 14px;
                            border-radius:12px;
                            font-weight:bold;
                            cursor:pointer;
                        ">
                            Dismiss
                        </button>

                    </form>

                </div>

            </div>

        <?php endforeach; ?>

    </div>

</main>

</div>

<?php require_once '../../includes/footer.php'; ?>