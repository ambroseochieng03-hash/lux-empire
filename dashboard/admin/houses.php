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
// FETCH HOUSES
// =====================================

$stmt = $pdo->query("
    SELECT
        houses.id,
        houses.title,
        houses.location,
        houses.price,
        houses.status,
        houses.house_type,
        houses.created_at,

        users.full_name AS landlord_name,
        users.email AS landlord_email

    FROM houses

    JOIN users
    ON houses.landlord_id = users.id

    ORDER BY houses.created_at DESC
");

$houses = $stmt->fetchAll();

?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/admin.css">

<div class="lux-dashboard-layout">

<main class="lux-dashboard-main">

    <!-- HEADER -->
    <div class="lux-page-header">

        <h1 class="lux-page-title">
            🏠 Property Oversight
        </h1>

        <p class="lux-page-subtitle">
            Monitor listed properties across the
            LUX EMPIRE ecosystem, supervise landlord
            activity, review pricing, and oversee
            housing operations platform-wide.
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
                        Property
                    </th>

                    <th style="padding:18px; color:gold; text-align:left;">
                        Type
                    </th>

                    <th style="padding:18px; color:gold; text-align:left;">
                        Location
                    </th>

                    <th style="padding:18px; color:gold; text-align:left;">
                        Price
                    </th>

                    <th style="padding:18px; color:gold; text-align:left;">
                        Landlord
                    </th>

                    <th style="padding:18px; color:gold; text-align:left;">
                        Status
                    </th>

                    <th style="padding:18px; color:gold; text-align:left;">
                        Added
                    </th>

                </tr>

            </thead>

            <tbody>

                <?php foreach($houses as $house): ?>

                    <tr style="
                        border-bottom:1px solid rgba(255,255,255,0.05);
                        transition:0.3s;
                    ">

                        <!-- ID -->
                        <td data-label="ID" style="
                            padding:20px;
                            color:white;
                        ">
                            #<?= $house['id'] ?>
                        </td>

                        <!-- TITLE -->
                        <td data-label="Property" style="
                            padding:20px;
                        ">

                            <div style="
                                color:white;
                                font-weight:bold;
                                margin-bottom:6px;
                            ">
                                <?= htmlspecialchars($house['title']) ?>
                            </div>

                            <div style="
                                color:var(--gray);
                                font-size:0.9rem;
                            ">
                                Premium Listing
                            </div>

                        </td>

                        <!-- TYPE -->
                        <td data-label="Type" style="
                            padding:20px;
                            color:gold;
                        ">
                            <?= htmlspecialchars($house['house_type'] ?? 'N/A') ?>
                        </td>

                        <!-- LOCATION -->
                        <td data-label="Location" style="
                            padding:20px;
                            color:var(--gray);
                        ">
                            <?= htmlspecialchars($house['location']) ?>
                        </td>

                        <!-- PRICE -->
                        <td data-label="Price" style="
                            padding:20px;
                            color:white;
                            font-weight:bold;
                        ">
                            KES <?= number_format($house['price']) ?>
                        </td>

                        <!-- LANDLORD -->
                        <td data-label="Landlord" style="padding:20px;">

                            <div style="
                                color:white;
                                font-weight:bold;
                            ">
                                <?= htmlspecialchars($house['landlord_name']) ?>
                            </div>

                            <div style="
                                color:var(--gray);
                                margin-top:5px;
                                font-size:0.9rem;
                            ">
                                <?= htmlspecialchars($house['landlord_email']) ?>
                            </div>

                        </td>

                        <!-- STATUS -->
                        <td data-label="Status" style="padding:20px;">

                            <?php

                            $statusColor = match($house['status']) {

                                'available' =>
                                    '#00cc66',

                                'booked' =>
                                    '#ffae42',

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
                                <?= ucfirst($house['status']) ?>
                            </span>

                        </td>

                        <!-- DATE -->
                        <td data-label="Added" style="
                            padding:20px;
                            color:var(--gray);
                        ">
                            <?= date(
                                "d M Y",
                                strtotime($house['created_at'])
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