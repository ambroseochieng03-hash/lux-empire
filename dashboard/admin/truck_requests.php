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

// =====================================
// FETCH TRUCK REQUESTS
// =====================================

$stmt = $pdo->query("
    SELECT

        truck_requests.id,
        truck_requests.pickup_location,
        truck_requests.destination,
        truck_requests.load_description,
        truck_requests.price,
        truck_requests.status,
        truck_requests.requested_at,

        tenant.full_name AS tenant_name,
        tenant.phone AS tenant_phone,

        driver.full_name AS driver_name

    FROM truck_requests

    JOIN users AS tenant
    ON truck_requests.tenant_id = tenant.id

    LEFT JOIN users AS driver
    ON truck_requests.driver_id = driver.id

    ORDER BY truck_requests.requested_at DESC
");

$requests = $stmt->fetchAll();

?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/admin.css">

<div class="lux-dashboard-layout">

<main class="lux-dashboard-main">

    <!-- PAGE HEADER -->
    <div class="lux-page-header">

        <h1 class="lux-page-title">
            Logistics Operations
        </h1>

        <p class="lux-page-subtitle">
            Monitor transportation activity across
            the LUX EMPIRE ecosystem, oversee
            truck requests, inspect driver assignments,
            and supervise logistics operations in real time.
        </p>

    </div>

    <!-- TABLE -->
    <div class="lux-card lux-table-wrapper">

        <table class="lux-table">

            <thead>

                <tr style="
                    border-bottom:1px solid rgba(255,255,255,0.1);
                ">

                    <th style="padding:18px; color:gold; text-align:left;">
                        ID
                    </th>

                    <th style="padding:18px; color:gold; text-align:left;">
                        Pickup
                    </th>

                    <th style="padding:18px; color:gold; text-align:left;">
                        Destination
                    </th>

                    <th style="padding:18px; color:gold; text-align:left;">
                        Tenant
                    </th>

                    <th style="padding:18px; color:gold; text-align:left;">
                        Driver
                    </th>

                    <th style="padding:18px; color:gold; text-align:left;">
                        Load
                    </th>

                    <th style="padding:18px; color:gold; text-align:left;">
                        Price
                    </th>

                    <th style="padding:18px; color:gold; text-align:left;">
                        Status
                    </th>

                    <th style="padding:18px; color:gold; text-align:left;">
                        Requested
                    </th>

                </tr>

            </thead>

            <tbody>

                <?php foreach($requests as $request): ?>

                    <tr style="
                        border-bottom:1px solid rgba(255,255,255,0.05);
                    ">

                        <!-- ID -->
                        <td data-label="ID" style="
                            padding:20px;
                            color:white;
                        ">
                            #<?= $request['id'] ?>
                        </td>

                        <!-- PICKUP -->
                        <td data-label="Pickup" style="
                            padding:20px;
                            color:white;
                            font-weight:bold;
                        ">
                            <?= htmlspecialchars($request['pickup_location']) ?>
                        </td>

                        <!-- DESTINATION -->
                        <td data-label="Destination" style="
                            padding:20px;
                            color:var(--gray);
                        ">
                            <?= htmlspecialchars($request['destination']) ?>
                        </td>

                        <!-- TENANT -->
                        <td data-label="Tenant" style="padding:20px;">

                            <div style="
                                color:white;
                                font-weight:bold;
                            ">
                                <?= htmlspecialchars($request['tenant_name']) ?>
                            </div>

                            <div style="
                                color:var(--gray);
                                margin-top:5px;
                                font-size:0.9rem;
                            ">
                                <?= htmlspecialchars($request['tenant_phone']) ?>
                            </div>

                        </td>

                        <!-- DRIVER -->
                        <td data-label="Driver" style="
                            padding:20px;
                            color:gold;
                            font-weight:bold;
                        ">

                            <?= $request['driver_name']
                                ? htmlspecialchars($request['driver_name'])
                                : 'Unassigned'
                            ?>

                        </td>

                        <!-- LOAD -->
                        <td data-label="Load" style="
                            padding:20px;
                            color:var(--gray);
                            max-width:220px;
                        ">
                            <?= htmlspecialchars($request['load_description'] ?? 'No description') ?>
                        </td>

                        <!-- PRICE -->
                        <td data-label="Price" style="
                            padding:20px;
                            color:white;
                            font-weight:bold;
                        ">
                            KES <?= number_format($request['price']) ?>
                        </td>

                        <!-- STATUS -->
                        <td data-label="Status" style="padding:20px;">

                            <?php

                            $statusColor = match($request['status']) {

                                'pending' =>
                                    '#ffae42',

                                'accepted' =>
                                    '#4da6ff',

                                'in_transit' =>
                                    '#00cc66',

                                'completed' =>
                                    '#d4af37',

                                default =>
                                    '#ff4d4d'
                            };

                            ?>

                            <span style="
                                background:<?= $statusColor ?>22;
                                color:<?= $statusColor ?>;
                                padding:8px 14px;
                                border-radius:14px;
                                font-size:0.9rem;
                                font-weight:bold;
                            ">
                                <?= ucfirst(
                                    str_replace(
                                        '_',
                                        ' ',
                                        $request['status']
                                    )
                                ) ?>
                            </span>

                        </td>

                        <!-- DATE -->
                        <td data-label="Requested" style="
                            padding:20px;
                            color:var(--gray);
                        ">
                            <?= date(
                                "d M Y H:i",
                                strtotime($request['requested_at'])
                            ) ?>
                        </td>

                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

    </div>

</main>

</div>

<?php require_once '../../includes/footer.php'; ?>