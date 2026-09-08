/**
 * LUX EMPIRE — Admin Core
 * Shared CSRF-aware AJAX helper, toast notifications, and a
 * reusable confirmation modal controller for every admin page.
 * Expects window.LUX_ADMIN = { csrfToken, baseUrl } set by the page.
 */
(function () {
    'use strict';

    var config = window.LUX_ADMIN || {};

    function toast(message, type) {
        var container = document.getElementById('luxAdminToasts');
        if (!container) {
            container = document.createElement('div');
            container.id = 'luxAdminToasts';
            container.className = 'lux-toast-stack';
            document.body.appendChild(container);
        }

        var el = document.createElement('div');
        el.className = 'lux-toast lux-toast-' + (type || 'info');
        el.textContent = message;
        container.appendChild(el);

        requestAnimationFrame(function () {
            el.classList.add('is-visible');
        });

        setTimeout(function () {
            el.classList.remove('is-visible');
            setTimeout(function () { el.remove(); }, 300);
        }, 3500);
    }

    function adminRequest(url, options) {
        options = options || {};
        var method = options.method || 'POST';

        var fetchOptions = {
            method: method,
            headers: {},
            credentials: 'same-origin'
        };

        if (method !== 'GET') {
            fetchOptions.headers['X-CSRF-Token'] = config.csrfToken;
            fetchOptions.headers['Content-Type'] = 'application/x-www-form-urlencoded';
            fetchOptions.body = new URLSearchParams(options.body || {}).toString();
        }

        return fetch(url, fetchOptions).then(function (response) {
            return response.json().then(function (data) {
                if (!response.ok || !data.success) {
                    throw new Error(data.error || 'Request failed.');
                }
                return data;
            });
        });
    }

    var confirmModal = document.getElementById('luxConfirmModal');
    var confirmPending = null;

    function openConfirm(options) {
        if (!confirmModal) {
            if (window.confirm(options.message)) {
                options.onConfirm();
            }
            return;
        }

        confirmModal.querySelector('.lux-confirm-title').textContent = options.title || 'Are you sure?';
        confirmModal.querySelector('.lux-confirm-message').textContent = options.message || '';

        var reasonWrap = confirmModal.querySelector('.lux-confirm-reason-wrap');
        var reasonInput = confirmModal.querySelector('.lux-confirm-reason-input');

        if (options.requireReason) {
            reasonWrap.hidden = false;
            reasonInput.value = '';
        } else {
            reasonWrap.hidden = true;
        }

        confirmPending = options;
        confirmModal.classList.add('is-open');
        confirmModal.setAttribute('aria-hidden', 'false');
    }

    function closeConfirm() {
        confirmModal.classList.remove('is-open');
        confirmModal.setAttribute('aria-hidden', 'true');
        confirmPending = null;
    }

    if (confirmModal) {
        confirmModal.querySelectorAll('[data-confirm-close]').forEach(function (btn) {
            btn.addEventListener('click', closeConfirm);
        });

        confirmModal.querySelector('.lux-confirm-accept').addEventListener('click', function () {
            if (!confirmPending) {
                return;
            }

            var reasonInput = confirmModal.querySelector('.lux-confirm-reason-input');

            if (confirmPending.requireReason && !reasonInput.value.trim()) {
                reasonInput.focus();
                return;
            }

            var pending = confirmPending;
            closeConfirm();
            pending.onConfirm(reasonInput.value.trim());
        });
    }

    window.LuxAdmin = {
        request: adminRequest,
        toast: toast,
        confirm: openConfirm
    };

}());
