<?php
/**
 * Expects $currentUserId, $autoOpenWithUserId (optional), $autoOpenHouseId (optional)
 * to already be set by the including page.
 */
require_once __DIR__ . '/../config/csrf.php';
$csrfToken = Csrf::token();
?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/chat.css">

<div class="chat-shell" id="chatShell">

    <div class="chat-list-pane" id="chatListPane">
        <div class="chat-list-header">
            <h2><i class="fa-solid fa-comments"></i> Chats</h2>
            <button type="button" class="chat-list-close-btn" id="chatListCloseBtn" aria-label="Hide chat list">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="chat-list" id="chatList">
            <div class="chat-list-empty">Loading conversations...</div>
        </div>
    </div>

    <div class="chat-thread-pane" id="chatThreadPane">
        <div class="chat-thread-placeholder" id="chatThreadPlaceholder">
            <i class="fa-solid fa-comment-dots"></i>
            <p>Select a chat to start messaging</p>
        </div>

        <div class="chat-thread-active" id="chatThreadActive" style="display:none;">
            <div class="chat-thread-header">
                <button class="chat-back-btn" id="chatBackBtn"><i class="fa-solid fa-arrow-left"></i></button>
                <div class="chat-thread-user">
                    <div class="chat-avatar"><i class="fa-solid fa-user"></i></div>
                    <div>
                        <h3 id="chatThreadName">—</h3>
                        <small id="chatThreadStatus">—</small>
                    </div>
                </div>
            </div>

            <div class="chat-messages" id="chatMessages"></div>

            <div class="chat-typing-indicator" id="chatTypingIndicator" style="display:none;">
                <span></span><span></span><span></span> typing...
            </div>

            <form class="chat-input-bar" id="chatInputForm">
                <input type="hidden" id="chatCsrfToken" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="text" id="chatMessageInput" placeholder="Type a message..." autocomplete="off">
                <button type="submit"><i class="fa-solid fa-paper-plane"></i></button>
            </form>
        </div>
    </div>

</div>

<script>
    window.LUX_CHAT_CONFIG = {
        baseUrl: "<?php echo BASE_URL; ?>",
        currentUserId: <?php echo (int) $currentUserId; ?>,
        autoOpenWithUserId: <?php echo isset($autoOpenWithUserId) ? (int) $autoOpenWithUserId : 'null'; ?>,
        autoOpenHouseId: <?php echo isset($autoOpenHouseId) && $autoOpenHouseId ? (int) $autoOpenHouseId : 'null'; ?>,
        autoOpenRole: <?php echo isset($autoOpenRole) ? "'" . htmlspecialchars($autoOpenRole) . "'" : 'null'; ?>
    };
</script>
<script src="<?php echo BASE_URL; ?>/assets/js/chat.js"></script>
