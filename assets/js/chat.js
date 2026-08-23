(function () {
    const cfg = window.LUX_CHAT_CONFIG;
    const chatList = document.getElementById('chatList');
    const chatMessages = document.getElementById('chatMessages');
    const chatThreadName = document.getElementById('chatThreadName');
    const chatThreadStatus = document.getElementById('chatThreadStatus');
    const chatThreadPlaceholder = document.getElementById('chatThreadPlaceholder');
    const chatThreadActive = document.getElementById('chatThreadActive');
    const chatInputForm = document.getElementById('chatInputForm');
    const chatMessageInput = document.getElementById('chatMessageInput');
    const chatTypingIndicator = document.getElementById('chatTypingIndicator');
    const chatShell = document.getElementById('chatShell');
    const chatBackBtn = document.getElementById('chatBackBtn');
    const csrfToken = document.getElementById('chatCsrfToken').value;
    const chatListCloseBtn = document.getElementById('chatListCloseBtn');

    let activeConversationId = null;
    let lastMessageId = 0;
    let messagePollTimer = null;
    let typingDebounce = null;

    function api(path, options = {}) {
        return fetch(`${cfg.baseUrl}/api/chat/${path}`, options).then(r => r.json());
    }

    function sizeChatShell() {
        const rect = chatShell.getBoundingClientRect();
        const available = window.innerHeight - rect.top;
        chatShell.style.height = available + 'px';
    }

    window.addEventListener('resize', sizeChatShell);
    sizeChatShell();

    function timeAgo(dateStr) {
        if (!dateStr) return 'Offline';
        const diff = (Date.now() - new Date(dateStr.replace(' ', 'T') + 'Z')) / 1000;
        if (diff < 60) return 'just now';
        if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        return Math.floor(diff / 86400) + 'd ago';
    }

    function renderConversations(conversations) {
        chatList.innerHTML = '';

        if (conversations.length === 0) {
            chatList.innerHTML = '<div class="chat-list-empty" style="padding:24px;color:var(--gray);">No conversations yet.</div>';
        }

        let totalUnread = 0;

        conversations.forEach(c => {
            const unread = parseInt(c.unread_count, 10) || 0;
            totalUnread += unread;

            const item = document.createElement('div');
            item.className = 'chat-list-item' + (unread > 0 ? ' unread' : '') + (activeConversationId == c.id ? ' active' : '');
            item.innerHTML = `
                <div class="chat-list-avatar"><i class="fa-solid fa-user"></i></div>
                <div class="chat-list-meta">
                    <div class="chat-list-name">
                        <span>${escapeHtml(c.with_name)}</span>
                        ${unread > 0 ? `<span class="chat-unread-count">${unread}</span>` : ''}
                    </div>
                    <div class="chat-list-preview">${escapeHtml(c.last_message || 'Say hello 👋')}</div>
                </div>
            `;
            item.addEventListener('click', () => openConversation(c.id, c.with_name, c.with_last_seen));
            chatList.appendChild(item);
        });

        const badge = document.getElementById('sidebarChatBadge');
        if (badge) {
            if (totalUnread > 0) {
                badge.textContent = totalUnread;
                badge.style.display = 'inline-flex';
            } else {
                badge.style.display = 'none';
            }
        }
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.innerText = str || '';
        return div.innerHTML;
    }

    function loadConversations() {
        api('fetch_conversations.php').then(data => {
            if (data.conversations) renderConversations(data.conversations);
        });
    }

    function openConversation(id, name, lastSeen) {
        activeConversationId = id;
        lastMessageId = 0;
        chatMessages.innerHTML = '';
        chatThreadPlaceholder.style.display = 'none';
        chatThreadActive.style.display = 'flex';
        chatThreadName.textContent = name;
        chatThreadStatus.textContent = timeAgo(lastSeen);
        chatShell.classList.add('thread-open'); // opening a chat slides the list away

        clearInterval(messagePollTimer);
        pollMessages();
        messagePollTimer = setInterval(pollMessages, 3000);
        loadConversations();
    }

    function pollMessages() {
        if (!activeConversationId) return;

        api(`fetch_messages.php?conversation_id=${activeConversationId}&after_id=${lastMessageId}`).then(data => {
            if (data.error) return;

            data.messages.forEach(m => {
                appendMessage(m);
                lastMessageId = Math.max(lastMessageId, parseInt(m.id, 10));
            });

            chatTypingIndicator.style.display = data.typing ? 'flex' : 'none';

            if (data.presence) {
                chatThreadStatus.textContent = data.presence.online ? 'Online' : timeAgo(data.presence.last_seen_at);
            }

            if (data.messages.length > 0) {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        });
    }

    function appendMessage(m) {
        const bubble = document.createElement('div');
        const mine = parseInt(m.sender_id, 10) === cfg.currentUserId;

        bubble.className = 'chat-bubble ' + (m.sender_type === 'ai' ? 'ai' : (mine ? 'mine' : 'theirs'));
        bubble.innerHTML = `${escapeHtml(m.message)}<small>${m.sender_type === 'ai' ? 'LUX EMPIRE Assistant · ' : ''}${new Date(m.created_at.replace(' ', 'T') + 'Z').toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'})}</small>`;
        chatMessages.appendChild(bubble);
    }

    chatInputForm.addEventListener('submit', (e) => {
        e.preventDefault();
        const text = chatMessageInput.value.trim();
        if (!text || !activeConversationId) return;

        const formData = new URLSearchParams();
        formData.append('conversation_id', activeConversationId);
        formData.append('message', text);
        formData.append('csrf_token', csrfToken);

        chatMessageInput.value = '';

        api('send_message.php', { method: 'POST', body: formData }).then(data => {
            if (data.message) {
                appendMessage(data.message);
                lastMessageId = Math.max(lastMessageId, parseInt(data.message.id, 10));
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        });
    });

    chatMessageInput.addEventListener('input', () => {
        if (!activeConversationId) return;
        clearTimeout(typingDebounce);
        typingDebounce = setTimeout(() => {
            const formData = new URLSearchParams();
            formData.append('conversation_id', activeConversationId);
            api('typing_status.php', { method: 'POST', body: formData });
        }, 300);
    });

    if (chatBackBtn) {
        chatBackBtn.addEventListener('click', () => {
            chatShell.classList.remove('thread-open'); // back to the list
            clearInterval(messagePollTimer);
            activeConversationId = null;
        });
    }

    // Global presence heartbeat while this page is open.
    setInterval(() => api('heartbeat.php', { method: 'POST' }), 15000);
    api('heartbeat.php', { method: 'POST' });

    loadConversations();
    setInterval(loadConversations, 8000);

    // Auto-open a conversation if arrived via a "Message Landlord" button.
    if (cfg.autoOpenWithUserId) {
        const formData = new URLSearchParams();
        formData.append('other_user_id', cfg.autoOpenWithUserId);
        formData.append('other_role', cfg.autoOpenRole || 'landlord');
        if (cfg.autoOpenHouseId) formData.append('house_id', cfg.autoOpenHouseId);
        formData.append('csrf_token', csrfToken);

        api('start_conversation.php', { method: 'POST', body: formData }).then(data => {
            if (data.conversation) {
                api('fetch_conversations.php').then(convData => {
                    const match = convData.conversations.find(c => c.id == data.conversation.id);
                    openConversation(data.conversation.id, match ? match.with_name : 'Chat', match ? match.with_last_seen : null);
                });
            }
        });
    }

    if (chatListCloseBtn) {
        chatListCloseBtn.addEventListener('click', () => {
            chatShell.classList.add('thread-open');
        });
    }

})();
