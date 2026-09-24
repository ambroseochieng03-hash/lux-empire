/*
=========================================
LUX EMPIRE — NAV MODE PREFERENCE
=========================================
Per-device preference (localStorage — matches how this device
already remembers its trusted-login state, so it's consistent that
this is per-browser too, not synced across devices). Mobile-only:
CSS gates bottom-nav display to <=768px regardless of this class.
=========================================
*/

(function () {

    const STORAGE_KEY = 'luxNavMode';
    const body = document.body;

    function apply(mode) {
        body.classList.toggle('lux-nav-bottom', mode === 'bottom');
    }

    let saved = 'sidebar';
    try { saved = localStorage.getItem(STORAGE_KEY) || 'sidebar'; } catch (e) {}
    apply(saved);

    document.addEventListener('click', function (event) {

        const emergencyForwardBtn = event.target.closest('[data-forward-emergency]');
        if (emergencyForwardBtn) {
            /*
             * Forwards to the ORIGINAL sidebar emergency button (still
             * present in the DOM, just visually hidden by CSS in bottom
             * -bar mode) rather than reusing its data-open-emergency-modal
             * attribute directly here. This guarantees exactly the same
             * working code path fires, regardless of whether
             * emergency-alert.js binds via delegation or a direct
             * element reference.
             */
            const original = document.getElementById('luxSidebarEmergencyTrigger');
            if (original) original.click();
            return;
        }

        const toggleBtn = event.target.closest('[data-nav-mode-toggle]');
        if (toggleBtn) {
            const target = toggleBtn.getAttribute('data-nav-mode-toggle');
            try { localStorage.setItem(STORAGE_KEY, target); } catch (e) {}
            apply(target);
            const sheet = document.getElementById('luxBottomNavMoreSheet');
            if (sheet) sheet.classList.remove('is-open');
            return;
        }

        const moreBtn = event.target.closest('#luxBottomNavMoreBtn');
        if (moreBtn) {
            const sheet = document.getElementById('luxBottomNavMoreSheet');
            if (sheet) sheet.classList.add('is-open');
            return;
        }

        const closeTarget = event.target.closest('[data-more-sheet-close]');
        if (closeTarget) {
            const sheet = document.getElementById('luxBottomNavMoreSheet');
            if (sheet) sheet.classList.remove('is-open');
        }
    });

    /*
     * Hide the bar only while a media lightbox or a fullscreened map
     * is actually open — reappears automatically once closed. Watches
     * the lightbox's own aria-hidden attribute rather than requiring
     * any edit to property-media.js, and the standard Fullscreen API
     * event for the Google Maps fullscreen control.
     */
    function setTempHidden(hidden) {
        body.classList.toggle('lux-nav-hidden-temp', hidden);
    }

    document.addEventListener('fullscreenchange', function () {
        setTempHidden(!!document.fullscreenElement);
    });

    const lightboxObserver = new MutationObserver(function (mutations) {
        mutations.forEach(function (m) {
            if (m.attributeName === 'aria-hidden' && m.target.classList.contains('media-lightbox')) {
                setTempHidden(m.target.getAttribute('aria-hidden') === 'false');
            }
        });
    });

    document.querySelectorAll('.media-lightbox').forEach(function (el) {
        lightboxObserver.observe(el, { attributes: true });
    });

})();
