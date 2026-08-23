<?php
require_once __DIR__ . '/../config/csrf.php';
$chatStarterCsrf = Csrf::token();
?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/chat.css">

<div class="chat-starter-overlay" id="chatStarterOverlay">
    <div class="chat-starter-box">
        <button type="button" class="chat-starter-close" id="chatStarterClose">&times;</button>
        <h3 id="chatStarterTitle">Message</h3>
        <textarea id="chatStarterInput" placeholder="Type your message..." rows="4"></textarea>
        <button type="button" class="lux-btn" id="chatStarterSend">Send Message</button>
        <div id="chatStarterFeedback" style="display:none;"></div>
    </div>
</div>

<script>
    window.LUX_CHAT_STARTER_CONFIG = {
        baseUrl: "<?php echo BASE_URL; ?>",
        csrfToken: "<?php echo htmlspecialchars($chatStarterCsrf); ?>",
        currentUserRole: "<?php echo htmlspecialchars(Session::user()['role'] ?? ''); ?>"
    };
</script>
<script src="<?php echo BASE_URL; ?>/assets/js/chat-starter.js"></script>
