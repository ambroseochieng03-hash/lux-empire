(function () {
    'use strict';

    var baseUrl = (window.LUX_ADMIN || {}).baseUrl || '';
    var form = document.getElementById('luxBroadcastForm');

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        var role = document.getElementById('luxBroadcastRole').value;
        var subject = document.getElementById('luxBroadcastSubject').value.trim();
        var body = document.getElementById('luxBroadcastBody').value.trim();

        if (!subject || !body) {
            LuxAdmin.toast('Subject and message are required.', 'error');
            return;
        }

        LuxAdmin.confirm({
            title: 'Send this broadcast?',
            message: 'This will email and notify every matching user.',
            onConfirm: function () {
                LuxAdmin.request(baseUrl + '/api/admin/broadcast_send.php', {
                    body: { target_role: role, subject: subject, body: body }
                }).then(function (data) {
                    LuxAdmin.toast('Sent to ' + data.recipient_count + ' recipients.', 'success');
                    form.reset();
                }).catch(function (err) {
                    LuxAdmin.toast(err.message, 'error');
                });
            }
        });
    });

}());
