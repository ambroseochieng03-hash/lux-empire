(function () {

    if (window.__luxNavMenuInitialized) {
        return;
    }
    window.__luxNavMenuInitialized = true;

    // menu element -> { popover, trigger, parent, nextSibling }
    // (parent/nextSibling let us put the popover back exactly where
    // it came from when it closes, so the PHP markup / DOM order
    // is untouched between opens.)
    var portalState = new Map();

    function positionPopover(trigger, popover) {
        var rect = trigger.getBoundingClientRect();
        var width = popover.offsetWidth || 160;

        popover.style.width = width + 'px';

        var left = rect.right - width;
        left = Math.max(8, Math.min(left, window.innerWidth - width - 8));
        popover.style.left = left + 'px';

        var height = popover.offsetHeight || 200;
        var spaceBelow = window.innerHeight - rect.bottom;
        var openUpward = spaceBelow < height + 10 && rect.top > height + 10;

        popover.style.top = openUpward
            ? (rect.top - height - 10) + 'px'
            : (rect.bottom + 10) + 'px';
    }

    function closeMenu(menu) {
        var state = portalState.get(menu);
        menu.classList.remove('is-open');

        if (state) {
            state.popover.classList.remove('is-open');
            state.popover.setAttribute('aria-hidden', 'true');
            state.popover.style.position = '';
            state.popover.style.top = '';
            state.popover.style.left = '';
            state.popover.style.width = '';
            state.parent.insertBefore(state.popover, state.nextSibling);
            portalState.delete(menu);
        }
    }

    function closeAllMenus(exceptMenu) {
        document.querySelectorAll('.lux-nav-menu.is-open').forEach((menu) => {
            if (menu !== exceptMenu) {
                closeMenu(menu);
            }
        });
    }

    function openMenu(menu) {
        var trigger = menu.querySelector('.lux-nav-menu-trigger');
        var popover = menu.querySelector('.lux-nav-menu-popover');
        if (!trigger || !popover) {
            return;
        }

        portalState.set(menu, {
            popover: popover,
            trigger: trigger,
            parent: popover.parentNode,
            nextSibling: popover.nextSibling
        });

        // Give it real dimensions (display:flex) BEFORE moving/measuring —
        // an element still display:none reports 0 for offsetWidth/Height.
        popover.classList.add('is-open');
        popover.setAttribute('aria-hidden', 'false');
        popover.style.position = 'fixed';

        document.body.appendChild(popover);
        positionPopover(trigger, popover);

        menu.classList.add('is-open');
    }

    function repositionOpenMenus() {
        portalState.forEach(function (state) {
            positionPopover(state.trigger, state.popover);
        });
    }

    document.addEventListener('DOMContentLoaded', () => {

        document.addEventListener('click', (event) => {

            const trigger = event.target.closest('.lux-nav-menu-trigger');

            if (trigger) {
                const menu = trigger.closest('.lux-nav-menu');
                const isOpen = menu.classList.contains('is-open');

                closeAllMenus(menu);

                if (isOpen) {
                    closeMenu(menu);
                } else {
                    openMenu(menu);
                }

                return;
            }

            // The popover now lives in <body> while open, not inside
            // .lux-nav-menu — but it's still the same DOM node, so
            // .closest('.lux-nav-menu-popover') on a click still works.
            if (!event.target.closest('.lux-nav-menu-popover')) {
                closeAllMenus(null);
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeAllMenus(null);
            }
        });

        window.addEventListener('resize', repositionOpenMenus);
        window.addEventListener('scroll', repositionOpenMenus, true);
    });

})();