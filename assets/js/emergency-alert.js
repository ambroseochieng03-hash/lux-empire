/**
 * LUX EMPIRE — Emergency alert compose flow.
 * Requires window.LUX_EMERGENCY = { csrfToken, baseUrl } set inline
 * where the trigger button lives (sidebar.php).
 */
(function () {
    'use strict';

    var config = window.LUX_EMERGENCY || {};
    var triggerBtns = document.querySelectorAll('[data-open-emergency-modal]');
    var composeModal = document.getElementById('luxEmergencyModal');
    var responseModal = document.getElementById('luxEmergencyResponseModal');
    var messageInput = document.getElementById('luxEmergencyMessage');
    var submitBtn = document.getElementById('luxEmergencySubmit');
    var responseText = document.getElementById('luxEmergencyResponseText');

    if (!composeModal) {
        return;
    }

    function openCompose() {
        messageInput.value = '';
        composeModal.classList.add('is-open');
        composeModal.setAttribute('aria-hidden', 'false');
        messageInput.focus();
    }

    function closeCompose() {
        composeModal.classList.remove('is-open');
        composeModal.setAttribute('aria-hidden', 'true');
    }

    function openResponse(message) {
        responseText.textContent = message;
        responseModal.classList.add('is-open');
        responseModal.setAttribute('aria-hidden', 'false');
    }

    function closeResponse() {
        responseModal.classList.remove('is-open');
        responseModal.setAttribute('aria-hidden', 'true');
    }

    triggerBtns.forEach(function (btn) {
        btn.addEventListener('click', openCompose);
    });

    composeModal.querySelectorAll('[data-emergency-cancel]').forEach(function (btn) {
        btn.addEventListener('click', closeCompose);
    });

    responseModal.querySelectorAll('[data-emergency-response-close]').forEach(function (btn) {
        btn.addEventListener('click', closeResponse);
    });

    submitBtn.addEventListener('click', function () {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Sending...';

        var body = new URLSearchParams({
            csrf_token: config.csrfToken,
            message: messageInput.value.trim()
        });

        fetch(config.baseUrl + '/api/emergency/trigger_alert.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        })
        .then(function (response) { return response.json(); })
        .then(function (data) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Send Alert';

            if (!data.success) {
                alert(data.message || 'Could not send alert. Please try again.');
                return;
            }

            closeCompose();
            openResponse(data.message);
        })
        .catch(function () {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Send Alert';
            alert('Network error. Please try again.');
        });
    });

}());
