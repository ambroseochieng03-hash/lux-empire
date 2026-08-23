(function () {
    const cfg = window.LUX_CHAT_STARTER_CONFIG;
    const overlay = document.getElementById('chatStarterOverlay');
    const title = document.getElementById('chatStarterTitle');
    const input = document.getElementById('chatStarterInput');
    const sendBtn = document.getElementById('chatStarterSend');
    const closeBtn = document.getElementById('chatStarterClose');
    const feedback = document.getElementById('chatStarterFeedback');

    let currentBtn = null;

    // Event delegation: works for buttons already on the page AND any
    // rendered dynamically later.
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.chat-starter-btn');
        if (!btn) return;

        currentBtn = btn;
        title.textContent = 'Message ' + (btn.dataset.otherName || '');
        input.value = '';
        input.style.display = '';
        sendBtn.style.display = '';
        sendBtn.disabled = false;
        feedback.style.display = 'none';
        overlay.classList.add('active');
        input.focus();
    });

    function closeModal() {
        overlay.classList.remove('active');
        currentBtn = null;
    }

    closeBtn.addEventListener('click', closeModal);
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) closeModal();
    });

    sendBtn.addEventListener('click', () => {
        const text = input.value.trim();
        if (!text || !currentBtn) return;

        sendBtn.disabled = true;

        const startForm = new URLSearchParams();
        startForm.append('csrf_token', cfg.csrfToken);

        if (cfg.currentUserRole === 'tenant') {
            startForm.append('other_user_id', currentBtn.dataset.otherUserId);
            startForm.append('other_role', currentBtn.dataset.otherRole);
            if (currentBtn.dataset.houseId) startForm.append('house_id', currentBtn.dataset.houseId);
            if (currentBtn.dataset.truckRequestId) startForm.append('truck_request_id', currentBtn.dataset.truckRequestId);
        } else {
            // Driver initiating with a known tenant.
            startForm.append('tenant_id', currentBtn.dataset.tenantId);
            if (currentBtn.dataset.truckRequestId) startForm.append('truck_request_id', currentBtn.dataset.truckRequestId);
        }

        fetch(`${cfg.baseUrl}/api/chat/start_conversation.php`, { method: 'POST', body: startForm })
            .then(r => r.json())
            .then(data => {
                if (data.error || !data.conversation) {
                    feedback.textContent = data.error || 'Could not start conversation.';
                    feedback.style.display = 'block';
                    sendBtn.disabled = false;
                    return;
                }

                const msgForm = new URLSearchParams();
                msgForm.append('csrf_token', cfg.csrfToken);
                msgForm.append('conversation_id', data.conversation.id);
                msgForm.append('message', text);

                return fetch(`${cfg.baseUrl}/api/chat/send_message.php`, { method: 'POST', body: msgForm })
                    .then(r => r.json())
                    .then(msgResult => {
                        sendBtn.disabled = false;

                        if (msgResult.error) {
                            feedback.textContent = msgResult.error;
                            feedback.style.display = 'block';
                            return;
                        }

                        input.style.display = 'none';
                        sendBtn.style.display = 'none';
                        feedback.textContent = 'Message sent — find the full conversation under Chats.';
                        feedback.style.display = 'block';

                        setTimeout(closeModal, 1800);
                    });
            })
            .catch(() => {
                sendBtn.disabled = false;
                feedback.textContent = 'Something went wrong. Please try again.';
                feedback.style.display = 'block';
            });
    });
})();
