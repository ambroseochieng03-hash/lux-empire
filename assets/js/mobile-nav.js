/*
=========================================
LUX EMPIRE — MOBILE NAV POPOVER
=========================================
Compact dropdown anchored under the hamburger button — replaces
the old full-width nav-links expansion. Opens/closes
#luxMobileNavPopover via #luxMobileToggleBtn; closes on outside
click, Escape, or after any link inside it is followed.
=========================================
*/

(function () {

    document.addEventListener('DOMContentLoaded', () => {

        var toggleBtn = document.getElementById('luxMobileToggleBtn');
        var popover = document.getElementById('luxMobileNavPopover');

        if (!toggleBtn || !popover) {
            return;
        }

        function openPopover() {
            popover.classList.add('is-open');
            popover.setAttribute('aria-hidden', 'false');
            toggleBtn.setAttribute('aria-expanded', 'true');
        }

        function closePopover() {
            popover.classList.remove('is-open');
            popover.setAttribute('aria-hidden', 'true');
            toggleBtn.setAttribute('aria-expanded', 'false');
        }

        toggleBtn.addEventListener('click', function (event) {
            event.stopPropagation();
            if (popover.classList.contains('is-open')) {
                closePopover();
            } else {
                openPopover();
            }
        });

        document.addEventListener('click', function (event) {
            if (!popover.classList.contains('is-open')) {
                return;
            }
            if (event.target.closest('#luxMobileNavPopover') || event.target.closest('#luxMobileToggleBtn')) {
                return;
            }
            closePopover();
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closePopover();
            }
        });

        // Close the popover once a plain nav link is followed. Modal
        // triggers (data-open-info-modal / data-open-role-select)
        // also live inside this popover and open a layer on top —
        // letting it close underneath them is fine either way.
        popover.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function () {
                closePopover();
            });
        });
    });

})();
