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
// FETCH USERS
// =====================================

$stmt = $pdo->query("
    SELECT
        id,
        full_name,
        email,
        phone,
        role,
        status,
        created_at
    FROM users
    ORDER BY created_at DESC
");

$users = $stmt->fetchAll();

?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/admin.css">

<div class="lux-dashboard-layout">

<main class="lux-dashboard-main lux-table-page">

    <!-- PAGE HEADER -->
    <div class="lux-page-header">

        <h1 class="lux-page-title">
            User Management
        </h1>

        <p class="lux-page-subtitle">
            Manage platform members, monitor user roles,
            supervise account status, and oversee
            the LUX EMPIRE ecosystem.
        </p>

    </div>

    <!-- USERS TABLE -->
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
                        Full Name
                    </th>

                    <th style="padding:18px; color:gold; text-align:left;">
                        Email
                    </th>

                    <th style="padding:18px; color:gold; text-align:left;">
                        Phone
                    </th>

                    <th style="padding:18px; color:gold; text-align:left;">
                        Role
                    </th>

                    <th style="padding:18px; color:gold; text-align:left;">
                        Status
                    </th>

                    <th style="padding:18px; color:gold; text-align:left;">
                        Joined
                    </th>

                    <th style="padding:18px; color:gold; text-align:left;">
                        Actions
                    </th>

                </tr>

            </thead>

            <tbody>

            <?php foreach($users as $user): ?>

            <tr style="
                border-bottom:1px solid rgba(255,255,255,0.05);
                transition:0.3s;
            ">

                <!-- ID -->
                <td data-label="ID" style="padding:20px; color:white;">
                    #<?= $user['id'] ?>
                </td>

                <!-- NAME -->
                <td data-label="Full Name" style="padding:20px; color:white; font-weight:bold;">
                    <?= htmlspecialchars($user['full_name']) ?>
                </td>

                <!-- EMAIL -->
                <td data-label="Email" style="padding:20px; color:var(--gray);">
                    <?= htmlspecialchars($user['email']) ?>
                </td>

                <!-- PHONE -->
                <td data-label="Phone" style="padding:20px; color:var(--gray);">
                    <?= htmlspecialchars($user['phone']) ?>
                </td>

                <!-- ROLE -->
                <td data-label="Role" style="padding:20px;">
                    <?php
                    $roleColor = match($user['role']) {
                        'admin' => '#ff4d4d',
                        'landlord' => '#4da6ff',
                        'driver' => '#00cc66',
                        default => '#d4af37'
                    };
                    ?>
                    <span style="
                        background:<?= $roleColor ?>22;
                        color:<?= $roleColor ?>;
                        padding:8px 14px;
                        border-radius:14px;
                        font-size:0.9rem;
                        font-weight:bold;
                    ">
                        <?= ucfirst($user['role']) ?>
                    </span>
                </td>

                <!-- STATUS -->
                <td style="padding:20px;">
                    <?php
                    $statusColor = match($user['status']) {
                        'active' => '#00cc66',
                        'suspended' => '#ff4d4d',
                        default => '#ffae42'
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
                        <?= ucfirst($user['status']) ?>
                    </span>
                </td>

                <!-- DATE -->
                <td style="padding:20px; color:var(--gray);">
                    <?= date("d M Y", strtotime($user['created_at'])) ?>
                </td>

                <!-- ACTIONS (FIXED + STYLED) -->
                <td style="padding:20px;">

                    <?php if ($user['role'] !== 'admin'): ?>

                    <div style="
                        display:flex;
                        gap:8px;
                        flex-wrap:wrap;
                    ">

                        <!-- SUSPEND / ACTIVATE TOGGLE -->
                        <?php if ($user['status'] === 'active'): ?>

                            <form action="<?php echo BASE_URL; ?>/api/admin/suspend_user.php" method="POST">
                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                <button style="
                                    background:#ff4d4d;
                                    color:white;
                                    border:none;
                                    padding:8px 12px;
                                    border-radius:10px;
                                    cursor:pointer;
                                    font-size:0.85rem;
                                ">
                                    Suspend
                                </button>
                            </form>

                        <?php else: ?>

                            <form action="<?php echo BASE_URL; ?>/api/admin/activate_user.php" method="POST">
                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                <button style="
                                    background:#00cc66;
                                    color:white;
                                    border:none;
                                    padding:8px 12px;
                                    border-radius:10px;
                                    cursor:pointer;
                                    font-size:0.85rem;
                                ">
                                    Activate
                                </button>
                            </form>

                        <?php endif; ?>

                        <!-- DELETE -->
                        <form action="<?php echo BASE_URL; ?>/api/admin/delete_user.php"
                            method="POST"
                            onsubmit="return confirm('Delete this user permanently?');">

                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">

                            <button style="
                                background:#222;
                                color:#ff4d4d;
                                border:1px solid #ff4d4d;
                                padding:8px 12px;
                                border-radius:10px;
                                cursor:pointer;
                                font-size:0.85rem;
                            ">
                                Delete
                            </button>

                        </form>

                    </div>

                    <?php endif; ?>

                </td>

            </tr>

            <?php endforeach; ?>

            </tbody>

        </table>

    </div>

</main>

</div>

<?php require_once '../../includes/footer.php'; ?>