/**
 * LUX EMPIRE — THEME TOGGLE
 * SAVE AT: assets/js/theme-toggle.js
 *
 * Any element with [data-theme-toggle-btn] becomes a working
 * toggle — no per-button JS needed, this delegates from one
 * document-level click listener (matches the pattern used by
 * .chat-starter-btn / .book-now-btn elsewhere in this codebase).
 *
 * window.LUX_THEME_CONFIG = { baseUrl, isLoggedIn, csrfToken }
 * must be set BEFORE this script runs if you want account-sync —
 * see includes/navbar.php / includes/sidebar.php for where it's
 * defined. If it's absent, the toggle still works fully — it just
 * stays device-only (cookie + localStorage).
 */
(function () {
    'use strict';

    var COOKIE_NAME = 'lux_theme';
    var COOKIE_DAYS = 365;

    function setCookie(name, value, days) {
        var expires = '';
        if (days) {
            var date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            expires = '; expires=' + date.toUTCString();
        }
        document.cookie = name + '=' + value + expires + '; path=/; SameSite=Lax';
    }

    function syncButtons(theme) {
        document.querySelectorAll('[data-theme-toggle-btn]').forEach(function (btn) {
            btn.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false');

            var icon = btn.querySelector('[data-theme-toggle-icon]');
            if (icon) {
                icon.className = 'fa-solid ' + (theme === 'light' ? 'fa-moon' : 'fa-sun');
            }

            var label = btn.querySelector('[data-theme-toggle-label]');
            if (label) {
                label.textContent = theme === 'light' ? 'Dark Mode' : 'Light Mode';
            }
        });
    }

    function applyTheme(theme, skipPersist) {
        document.documentElement.setAttribute('data-theme', theme);
        syncButtons(theme);

        if (skipPersist) {
            return;
        }

        try {
            window.localStorage.setItem(COOKIE_NAME, theme);
        } catch (e) {
            /* localStorage unavailable (private browsing etc.) — cookie below still works */
        }

        setCookie(COOKIE_NAME, theme, COOKIE_DAYS);

        if (window.LUX_THEME_CONFIG && window.LUX_THEME_CONFIG.isLoggedIn) {
            fetch(window.LUX_THEME_CONFIG.baseUrl + '/api/user/set_theme.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                credentials: 'same-origin',
                body: 'theme=' + encodeURIComponent(theme)
                    + '&csrf_token=' + encodeURIComponent(window.LUX_THEME_CONFIG.csrfToken || '')
            }).catch(function () {
                /* best-effort — the cookie already holds the choice for this device
                   regardless of whether the account-sync request succeeds */
            });
        }
    }

    document.addEventListener('click', function (event) {
        var btn = event.target.closest('[data-theme-toggle-btn]');
        if (!btn) {
            return;
        }
        event.preventDefault();
        var current = document.documentElement.getAttribute('data-theme') || 'light';
        applyTheme(current === 'light' ? 'dark' : 'light', false);
    });

    // Sync button icon/label to whatever header.php already rendered
    // server-side — no theme CHANGE here, just matching the buttons
    // to the current state.
    document.addEventListener('DOMContentLoaded', function () {
        var current = document.documentElement.getAttribute('data-theme') || 'light';
        applyTheme(current, true);
    });
})();
