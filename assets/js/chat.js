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

    // Mirrors the server rules (the server is the one that actually enforces them).
    const EDIT_WINDOW_MS = (Number(cfg.editWindowMinutes) || 15) * 60000;
    const DELETE_ALL_WINDOW_MS = (Number(cfg.deleteEveryoneWindowMinutes) || 60) * 60000;

    let activeConversationId = null;
    let lastMessageId = 0;
    let sinceTime = null;
    let chatClosed = false;
    let messagePollTimer = null;
    let typingDebounce = null;

    /* ===================== HELPERS ===================== */

    function api(path, options = {}) {
        return fetch(`${cfg.baseUrl}/api/chat/${path}`, options).then(r => r.json());
    }

    function postApi(path, fields) {
        const formData = new URLSearchParams();
        Object.keys(fields).forEach((key) => formData.append(key, fields[key]));
        formData.append('csrf_token', csrfToken);
        return api(path, { method: 'POST', body: formData });
    }

    function sizeChatShell() {
        const rect = chatShell.getBoundingClientRect();
        const available = window.innerHeight - rect.top;
        chatShell.style.height = available + 'px';
    }

    window.addEventListener('resize', sizeChatShell);
    sizeChatShell();

    // The database stores Nairobi time (UTC+3, no daylight saving), not UTC.
    function parseServerTime(str) {
        return new Date(String(str).replace(' ', 'T') + '+03:00');
    }

    function timeAgo(dateStr) {
        if (!dateStr) return 'Offline';
        const diff = Math.max(0, (Date.now() - parseServerTime(dateStr).getTime()) / 1000);
        if (diff < 60) return 'just now';
        if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        return Math.floor(diff / 86400) + 'd ago';
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.innerText = str || '';
        return div.innerHTML;
    }

    /* ===================== CONTEXT MENU, LONG-PRESS, DIALOGS ===================== */

    let menuEl = null;

    function closeMenu() {
        if (menuEl) {
            menuEl.remove();
            menuEl = null;
        }
    }

    function showMenu(items, x, y) {
        closeMenu();

        const menu = document.createElement('div');
        menu.className = 'chat-context-menu';

        items.forEach((item) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'chat-context-item' + (item.danger ? ' is-danger' : '');
            btn.innerHTML = `<i class="fa-solid ${item.icon}"></i> `;
            btn.appendChild(document.createTextNode(item.label));
            btn.addEventListener('click', (event) => {
                event.stopPropagation();
                closeMenu();
                item.run();
            });
            menu.appendChild(btn);
        });

        document.body.appendChild(menu);

        const rect = menu.getBoundingClientRect();
        const left = Math.min(x, window.innerWidth - rect.width - 8);
        const top = Math.min(y, window.innerHeight - rect.height - 8);
        menu.style.left = Math.max(8, left) + 'px';
        menu.style.top = Math.max(8, top) + 'px';

        menuEl = menu;

        setTimeout(() => document.addEventListener('click', closeMenu, { once: true }), 0);
    }

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeMenu();
    });

    window.addEventListener('scroll', closeMenu, true);

    // Press and hold (phones/tablets). The click that follows the release is swallowed
    // so it doesn't immediately close the menu or open the chat underneath.
    function attachLongPress(el, handler) {
        let timer = null;

        function suppressNextClick() {
            const stop = (event) => {
                event.stopPropagation();
                event.preventDefault();
            };
            el.addEventListener('click', stop, { capture: true, once: true });
            setTimeout(() => el.removeEventListener('click', stop, true), 800);
        }

        el.addEventListener('touchstart', (event) => {
            const touch = event.touches[0];
            timer = setTimeout(() => {
                timer = null;
                suppressNextClick();
                handler(touch.clientX, touch.clientY);
            }, 500);
        }, { passive: true });

        ['touchend', 'touchmove', 'touchcancel'].forEach((name) => {
            el.addEventListener(name, () => {
                if (timer) {
                    clearTimeout(timer);
                    timer = null;
                }
            }, { passive: true });
        });
    }

    function showDialog({ title, message, confirmLabel, danger, editValue }) {
        return new Promise((resolve) => {

            const overlay = document.createElement('div');
            overlay.className = 'chat-dialog-overlay';

            const box = document.createElement('div');
            box.className = 'chat-dialog-box';
            box.setAttribute('role', 'dialog');
            box.setAttribute('aria-modal', 'true');

            const heading = document.createElement('h3');
            heading.textContent = title;
            box.appendChild(heading);

            if (message) {
                const p = document.createElement('p');
                p.textContent = message;
                box.appendChild(p);
            }

            let input = null;

            if (editValue !== undefined) {
                input = document.createElement('textarea');
                input.className = 'chat-dialog-input';
                input.rows = 3;
                input.maxLength = 2000;
                input.value = editValue;
                box.appendChild(input);
            }

            const actions = document.createElement('div');
            actions.className = 'chat-dialog-actions';

            const cancelBtn = document.createElement('button');
            cancelBtn.type = 'button';
            cancelBtn.className = 'chat-dialog-cancel';
            cancelBtn.textContent = 'Cancel';

            const okBtn = document.createElement('button');
            okBtn.type = 'button';
            okBtn.className = 'chat-dialog-ok' + (danger ? ' is-danger' : '');
            okBtn.textContent = confirmLabel;

            actions.append(cancelBtn, okBtn);
            box.appendChild(actions);
            overlay.appendChild(box);

            function finish(ok) {
                document.removeEventListener('keydown', onKey);
                overlay.remove();
                resolve({ ok: ok, value: input ? input.value : '' });
            }

            function onKey(event) {
                if (event.key === 'Escape') finish(false);
            }

            document.addEventListener('keydown', onKey);
            cancelBtn.addEventListener('click', () => finish(false));
            okBtn.addEventListener('click', () => finish(true));
            overlay.addEventListener('click', (event) => {
                if (event.target === overlay) finish(false);
            });

            document.body.appendChild(overlay);
            (input || okBtn).focus();
        });
    }

    /* ===================== CONVERSATION LIST ===================== */

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
                <button type="button" class="chat-list-menu-btn" aria-label="Chat options">
                    <i class="fa-solid fa-ellipsis-vertical"></i>
                </button>
            `;

            item.addEventListener('click', () => openConversation(c.id, c.with_name, c.with_last_seen));

            item.querySelector('.chat-list-menu-btn').addEventListener('click', (event) => {
                event.stopPropagation();
                const rect = event.currentTarget.getBoundingClientRect();
                openConversationMenu(c, rect.left, rect.bottom);
            });

            item.addEventListener('contextmenu', (event) => {
                event.preventDefault();
                openConversationMenu(c, event.clientX, event.clientY);
            });

            attachLongPress(item, (x, y) => openConversationMenu(c, x, y));

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

    function openConversationMenu(conversation, x, y) {
        showMenu([
            {
                label: 'Delete chat for me',
                icon: 'fa-trash',
                danger: true,
                run: () => deleteConversationForMe(conversation)
            }
        ], x, y);
    }

    async function deleteConversationForMe(conversation) {
        const result = await showDialog({
            title: 'Delete this chat for you?',
            message: 'The whole conversation disappears from YOUR list only. ' + conversation.with_name + ' keeps their copy, and a new message from them brings the chat back. A private record is kept for safety and dispute resolution.',
            confirmLabel: 'Delete chat',
            danger: true
        });

        if (!result.ok) return;

        try {
            const data = await postApi('delete_conversation.php', { conversation_id: conversation.id });

            if (!data.ok) {
                alert(data.error || 'Could not delete this chat.');
                return;
            }

            if (activeConversationId == conversation.id) {
                showNoActiveConversation();
            }

            loadConversations();
        } catch (e) {
            alert('Could not delete this chat. Please check your connection and try again.');
        }
    }

    function showNoActiveConversation() {
        clearInterval(messagePollTimer);
        activeConversationId = null;
        chatThreadActive.style.display = 'none';
        chatThreadPlaceholder.style.display = '';
        chatShell.classList.remove('thread-open');
    }

    function loadConversations() {
        if (document.hidden) return;

        api('fetch_conversations.php').then(data => {
            if (data.conversations) renderConversations(data.conversations);
        });
    }

    function openConversation(id, name, lastSeen) {
        activeConversationId = id;
        lastMessageId = 0;
        sinceTime = null;
        setChatClosed(false);
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

    /* ===================== MESSAGES ===================== */

    function pollMessages() {
        if (!activeConversationId || document.hidden) return;

        const requestedFor = activeConversationId;
        const params = new URLSearchParams({
            conversation_id: requestedFor,
            after_id: lastMessageId
        });

        if (sinceTime) params.set('since', sinceTime);

        api(`fetch_messages.php?${params.toString()}`).then(data => {
            if (requestedFor !== activeConversationId) return; // they switched chats meanwhile
            if (data.error || !Array.isArray(data.messages)) return;

            if (typeof data.closed === 'boolean') {
                setChatClosed(data.closed);
            }

            if (data.server_time) {
                sinceTime = data.server_time;
            }

            // Edits / "delete for everyone" the other person made to older messages.
            (data.changes || []).forEach(m => {
                const existing = chatMessages.querySelector(`[data-message-id="${m.id}"]`);
                if (existing) fillBubble(existing, m);
            });

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

    function setChatClosed(closed) {
        chatClosed = closed;
        chatMessageInput.disabled = closed;

        const sendButton = chatInputForm.querySelector('button[type="submit"]');
        if (sendButton) sendButton.disabled = closed;

        chatMessageInput.placeholder = closed
            ? 'This chat is closed — there is no active booking between you.'
            : 'Type a message...';
    }

    function appendSystemNote(text) {
        const note = document.createElement('div');
        note.className = 'chat-bubble ai';
        note.textContent = text;
        chatMessages.appendChild(note);
    }

    function fillBubble(bubble, m) {
        bubble._msg = m;

        const mine = parseInt(m.sender_id, 10) === cfg.currentUserId;
        const deleted = parseInt(m.is_deleted, 10) === 1;
        const edited = parseInt(m.is_edited, 10) === 1;

        bubble.className = 'chat-bubble ' + (m.sender_type === 'ai' ? 'ai' : (mine ? 'mine' : 'theirs')) + (deleted ? ' is-deleted' : '');
        bubble.textContent = '';

        const text = document.createElement('span');
        text.className = 'chat-bubble-text';
        text.textContent = deleted ? 'This message was deleted' : m.message;
        bubble.appendChild(text);

        const meta = document.createElement('small');
        const time = parseServerTime(m.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        meta.textContent = (m.sender_type === 'ai' ? 'LUX EMPIRE Assistant · ' : '') + time + (edited && !deleted ? ' · edited' : '');
        bubble.appendChild(meta);

        const menuBtn = document.createElement('button');
        menuBtn.type = 'button';
        menuBtn.className = 'chat-bubble-menu-btn';
        menuBtn.setAttribute('aria-label', 'Message options');
        menuBtn.innerHTML = '<i class="fa-solid fa-chevron-down"></i>';
        menuBtn.addEventListener('click', (event) => {
            event.stopPropagation();
            const rect = menuBtn.getBoundingClientRect();
            openMessageMenu(bubble, rect.left, rect.bottom);
        });
        bubble.appendChild(menuBtn);
    }

    function appendMessage(m) {
        if (chatMessages.querySelector(`[data-message-id="${m.id}"]`)) return; // already shown

        const bubble = document.createElement('div');
        bubble.dataset.messageId = m.id;
        fillBubble(bubble, m);

        bubble.addEventListener('contextmenu', (event) => {
            event.preventDefault();
            openMessageMenu(bubble, event.clientX, event.clientY);
        });

        attachLongPress(bubble, (x, y) => openMessageMenu(bubble, x, y));

        chatMessages.appendChild(bubble);
    }

    function openMessageMenu(bubble, x, y) {
        const m = bubble._msg;
        const mine = parseInt(m.sender_id, 10) === cfg.currentUserId && m.sender_type === 'user';
        const deleted = parseInt(m.is_deleted, 10) === 1;
        const age = Date.now() - parseServerTime(m.created_at).getTime();

        const items = [];

        if (mine && !deleted && !chatClosed && age <= EDIT_WINDOW_MS) {
            items.push({ label: 'Edit', icon: 'fa-pen', run: () => editMessage(bubble) });
        }

        items.push({ label: 'Delete for me', icon: 'fa-eye-slash', run: () => deleteForMe(bubble) });

        if (mine && !deleted && !chatClosed && age <= DELETE_ALL_WINDOW_MS) {
            items.push({ label: 'Delete for everyone', icon: 'fa-trash', danger: true, run: () => deleteForEveryone(bubble) });
        }

        showMenu(items, x, y);
    }

    async function editMessage(bubble) {
        const m = bubble._msg;

        const result = await showDialog({
            title: 'Edit message',
            message: 'The other person will see that this message was edited. A record of the original is kept.',
            confirmLabel: 'Save',
            editValue: m.message
        });

        if (!result.ok) return;

        const newText = result.value.trim();
        if (!newText || newText === m.message) return;

        try {
            const data = await postApi('edit_message.php', { message_id: m.id, message: newText });

            if (data.message) {
                fillBubble(bubble, data.message);
                if (data.notice) appendSystemNote(data.notice);
            } else {
                alert(data.error || 'Could not edit this message.');
            }
        } catch (e) {
            alert('Could not edit this message. Please check your connection and try again.');
        }
    }

    async function deleteForMe(bubble) {
        const m = bubble._msg;

        const result = await showDialog({
            title: 'Delete this message for you?',
            message: 'It disappears from YOUR chat only. The other person can still see it.',
            confirmLabel: 'Delete for me',
            danger: true
        });

        if (!result.ok) return;

        try {
            const data = await postApi('delete_message.php', { message_id: m.id, scope: 'me' });

            if (data.ok) {
                bubble.remove();
            } else {
                alert(data.error || 'Could not delete this message.');
            }
        } catch (e) {
            alert('Could not delete this message. Please check your connection and try again.');
        }
    }

    async function deleteForEveryone(bubble) {
        const m = bubble._msg;

        const result = await showDialog({
            title: 'Delete this message for everyone?',
            message: 'It becomes "This message was deleted" for both of you. LUX EMPIRE keeps a private record for safety and dispute resolution.',
            confirmLabel: 'Delete for everyone',
            danger: true
        });

        if (!result.ok) return;

        try {
            const data = await postApi('delete_message.php', { message_id: m.id, scope: 'everyone' });

            if (data.message) {
                fillBubble(bubble, data.message);
            } else {
                alert(data.error || 'Could not delete this message.');
            }
        } catch (e) {
            alert('Could not delete this message. Please check your connection and try again.');
        }
    }

    /* ===================== SENDING, TYPING, PRESENCE ===================== */

    chatInputForm.addEventListener('submit', (e) => {
        e.preventDefault();
        const text = chatMessageInput.value.trim();
        if (!text || !activeConversationId || chatClosed) return;

        chatMessageInput.value = '';

        postApi('send_message.php', { conversation_id: activeConversationId, message: text }).then(data => {
            if (data.message) {
                appendMessage(data.message);
                lastMessageId = Math.max(lastMessageId, parseInt(data.message.id, 10));
                if (data.notice) appendSystemNote(data.notice);
                chatMessages.scrollTop = chatMessages.scrollHeight;
            } else if (data.error) {
                chatMessageInput.value = text;
                alert(data.error);
            }
        }).catch(() => {
            chatMessageInput.value = text;
            alert('Could not send your message. Please check your connection and try again.');
        });
    });

    chatMessageInput.addEventListener('input', () => {
        if (!activeConversationId) return;
        clearTimeout(typingDebounce);
        typingDebounce = setTimeout(() => {
            postApi('typing_status.php', { conversation_id: activeConversationId });
        }, 300);
    });

    if (chatBackBtn) {
        chatBackBtn.addEventListener('click', () => {
            chatShell.classList.remove('thread-open'); // back to the list
            clearInterval(messagePollTimer);
            activeConversationId = null;
        });
    }

    // Presence heartbeat + conversation list refresh — only while this tab is
    // visible. A hidden tab makes no requests at all.
    setInterval(() => {
        if (!document.hidden) api('heartbeat.php', { method: 'POST' });
    }, 15000);
    api('heartbeat.php', { method: 'POST' });

    loadConversations();
    setInterval(loadConversations, 10000);

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            pollMessages();
            loadConversations();
        }
    });

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