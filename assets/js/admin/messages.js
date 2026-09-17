(function () {
    'use strict';

    var baseUrl = (window.LUX_ADMIN || {}).baseUrl || '';
    var form = document.getElementById('luxBroadcastForm');
    var submitBtn = form.querySelector('button[type="submit"]');

    // One key per broadcast attempt — regenerated after a successful
    // send, so a genuinely new broadcast never reuses a previous
    // attempt's key, while retries of the SAME attempt (a slow
    // network causing a resend) keep reusing it, so the server-side
    // guard in broadcast_send.php can recognize a repeat.
    var currentKey = LuxIdempotency.generate();

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        var role = document.getElementById('luxBroadcastRole').value;
        var subject = document.getElementById('luxBroadcastSubject').value.trim();
        var body = document.getElementById('luxBroadcastBody').value.trim();

        if (!subject || !body) {
            LuxAdmin.toast('Subject and message are required.', 'error');
            return;
        }

        if (submitBtn.disabled) {
            // Already sending this exact attempt — ignore further
            // clicks/submits until it resolves.
            return;
        }

        LuxAdmin.confirm({
            title: 'Send this broadcast?',
            message: 'This will email and notify every matching user.',
            onConfirm: function () {

                submitBtn.disabled = true;
                var originalText = submitBtn.textContent;
                submitBtn.textContent = 'Sending...';

                LuxAdmin.request(baseUrl + '/api/admin/broadcast_send.php', {
                    body: {
                        target_role: role,
                        subject: subject,
                        body: body,
                        idempotency_key: currentKey
                    }
                }).then(function (data) {
                    LuxAdmin.toast('Sent to ' + data.recipient_count + ' recipients.', 'success');
                    form.reset();
                    // This attempt is done — the NEXT submit is a
                    // genuinely new broadcast and needs its own key.
                    currentKey = LuxIdempotency.generate();
                }).catch(function (err) {
                    LuxAdmin.toast(err.message, 'error');
                    // Keep the same key on failure — if this attempt
                    // actually succeeded server-side despite the
                    // client seeing an error, a retry with the same
                    // key replays that result instead of sending again.
                }).finally(function () {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                });
            }
        });
    });

}());