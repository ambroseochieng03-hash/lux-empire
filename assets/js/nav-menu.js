(function () {

    if (window.__luxNavMenuInitialized) {
        return;
    }
    window.__luxNavMenuInitialized = true;

    function closeAllMenus(exceptMenu) {
        document.querySelectorAll('.lux-nav-menu.is-open').forEach((menu) => {
            if (menu !== exceptMenu) {
                menu.classList.remove('is-open');
            }
        });
    }

    document.addEventListener('DOMContentLoaded', () => {

        document.addEventListener('click', (event) => {

            const trigger = event.target.closest('.lux-nav-menu-trigger');

            if (trigger) {
                const menu = trigger.closest('.lux-nav-menu');
                const isOpen = menu.classList.contains('is-open');

                closeAllMenus(menu);
                menu.classList.toggle('is-open', !isOpen);

                return;
            }

            if (!event.target.closest('.lux-nav-menu-popover')) {
                closeAllMenus(null);
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeAllMenus(null);
            }
        });
    });

})();
