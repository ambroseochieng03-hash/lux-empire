<?php

declare(strict_types=1);

require_once '../../includes/init.php';
require_once '../../includes/auth_check.php';
requireRoleAccess('admin');

require_once '../../config/csrf.php';

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
require_once '../../includes/sidebar.php';

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
        <h1 class="lux-page-title">Empire Broadcast</h1>
        <p class="lux-page-subtitle">
            Send a message to platform members by email and
            in-app notification. Choose who receives it, then write
            your message below.
        </p>
    </div>

    <div class="lux-card" style="padding:30px; border-radius:24px; max-width:700px;">

        <form id="luxBroadcastForm">

            <div style="margin-bottom:20px;">
                <label style="color:var(--gray); display:block; margin-bottom:8px;">Recipients</label>
                <select class="lux-admin-filter-select" id="luxBroadcastRole" style="width:100%;">
                    <option value="all">Everyone</option>
                    <option value="tenant">Tenants</option>
                    <option value="landlord">Landlords</option>
                    <option value="driver">Drivers</option>
                    <option value="admin">Fellow Admins</option>
                </select>
            </div>

            <div style="margin-bottom:20px;">
                <label style="color:var(--gray); display:block; margin-bottom:8px;">Subject</label>
                <input type="text" id="luxBroadcastSubject" maxlength="150" required
                       style="width:100%; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.15); color:white; padding:12px 14px; border-radius:12px;">
            </div>

            <div style="margin-bottom:24px;">
                <label style="color:var(--gray); display:block; margin-bottom:8px;">Message</label>
                <textarea id="luxBroadcastBody" maxlength="5000" required
                          style="width:100%; min-height:180px; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.15); color:white; padding:12px 14px; border-radius:12px; resize:vertical;"></textarea>
            </div>

            <button type="submit" class="lux-btn lux-btn-success">Send Broadcast</button>

        </form>

    </div>

</main>

</div>

<script src="<?php echo BASE_URL; ?>/assets/js/admin/admin-core.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/admin/messages.js"></script>

<?php require_once '../../includes/footer.php'; ?>
