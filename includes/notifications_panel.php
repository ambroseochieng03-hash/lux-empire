<?php
require_once __DIR__ . '/../config/csrf.php';
$notifCsrfToken = Csrf::token();
?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/notifications.css">

<div class="notif-shell">
    <div class="notif-header">
        <h2><i class="fa-solid fa-bell"></i> Notifications</h2>
        <button type="button" id="notifMarkAllBtn">Mark all as read</button>
    </div>
    <div class="notif-list" id="notifList">
        <div class="notif-empty">Loading notifications...</div>
    </div>
</div>

<script>
    window.LUX_NOTIF_CONFIG = {
        baseUrl: "<?php echo BASE_URL; ?>",
        csrfToken: "<?php echo htmlspecialchars($notifCsrfToken); ?>"
    };
</script>
<script src="<?php echo BASE_URL; ?>/assets/js/notifications.js"></script>
