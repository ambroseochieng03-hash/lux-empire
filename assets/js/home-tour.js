/**
 * LUX EMPIRE — HOMEPAGE / NAVBAR TOUR
 * SAVE AT: assets/js/home-tour.js
 *
 * Loaded on public (non-dashboard) pages only, right after
 * product-tour.js. Shows first-time visitors a "want a quick
 * guide?" prompt, then walks them through the navbar — picking
 * the desktop layout or the mobile hamburger layout automatically,
 * based on what's actually visible on screen right now (not a
 * guessed breakpoint), so it keeps working even if the site's own
 * CSS breakpoints change later.
 *
 * Requires these (already-existing, untouched) hooks in navbar.php:
 *   #luxMobileToggleBtn, #luxMobileNavPopover, #luxNavLinks,
 *   #luxNavButtons .lux-nav-menu-trigger, #luxNavButtons .lux-nav-menu-popover
 * and a handful of harmless data-tour="..." attributes added to the
 * nav-links/mobile-popover anchors (see the integration notes) —
 * needed only because a couple of links share the same href.
 *
 * Storage: a single "seen" flag per browser, so returning visitors
 * are never asked again. window.LuxTour.addHelpButton() gives people
 * a way to bring the tour back manually if they want it later.
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'lux_home_tour_seen_v1';

    function isMobileNav() {
        var toggle = document.getElementById('luxMobileToggleBtn');
        return !!toggle && toggle.offsetParent !== null;
    }

    /*
     * Checks the ELEMENT'S ACTUAL RENDERED STATE instead of guessing
     * which CSS class a popover uses to show/hide itself (that was
     * the bug — nav-menu.js / mobile-nav.js may toggle visibility,
     * opacity, or display, not necessarily a class called "active").
     * This works no matter which technique they use.
     */
    function isOpen(el) {
        if (!el) { return false; }
        var rect = el.getBoundingClientRect();
        if (rect.width === 0 && rect.height === 0) { return false; }
        var style = window.getComputedStyle(el);
        if (style.display === 'none') { return false; }
        if (style.visibility === 'hidden') { return false; }
        if (parseFloat(style.opacity) === 0) { return false; }
        return true;
    }

    function openMobilePopover() {
        var toggle = document.getElementById('luxMobileToggleBtn');
        var popover = document.getElementById('luxMobileNavPopover');
        if (toggle && popover && !isOpen(popover)) {
            toggle.click(); // reuse the site's own open logic (mobile-nav.js)
        }
    }

    function closeMobilePopover() {
        var toggle = document.getElementById('luxMobileToggleBtn');
        var popover = document.getElementById('luxMobileNavPopover');
        if (toggle && popover && isOpen(popover)) {
            toggle.click();
        }
    }

    function openDesktopMenu() {
        var trigger = document.querySelector('#luxNavButtons .lux-nav-menu-trigger');
        var popover = document.querySelector('#luxNavButtons .lux-nav-menu-popover');
        if (trigger && popover && !isOpen(popover)) {
            trigger.click(); // reuse the site's own open logic (nav-menu.js)
        }
    }

    function closeDesktopMenu() {
        var trigger = document.querySelector('#luxNavButtons .lux-nav-menu-trigger');
        var popover = document.querySelector('#luxNavButtons .lux-nav-menu-popover');
        if (trigger && popover && isOpen(popover)) {
            trigger.click();
        }
    }

    function closeAnyOpenMenus() {
        closeDesktopMenu();
        closeMobilePopover();
    }

    function q(sel) { return function () { return document.querySelector(sel); }; }

    function buildDesktopSteps() {
        return [
            {
                element: null,
                title: 'Welcome to LUX EMPIRE! 👋',
                text: "Let's take a quick, one-minute look at how to get around. Click Next whenever you're ready."
            },
            {
                element: q('.logo'),
                title: 'Your way back home',
                text: 'Click this logo anytime to return to this page.',
                placement: 'bottom'
            },
            {
                element: q('.nav-links [data-tour="home"]'),
                title: 'Home',
                text: 'Brings you right back to this page.',
                placement: 'bottom'
            },
            {
                element: q('.nav-links [data-tour="homes"]'),
                title: 'LUX Homes',
                text: 'Tap here to browse houses you can book.',
                placement: 'bottom'
            },
            {
                element: q('.nav-links [data-tour="move"]'),
                title: 'LUX Move',
                text: 'Need to move? This helps you book a truck.',
                placement: 'bottom'
            },
            {
                element: q('.nav-links [data-tour="about"]'),
                title: 'About',
                text: 'A quick word about who we are.',
                placement: 'bottom'
            },
            {
                element: q('.nav-links [data-tour="contact"]'),
                title: 'Contact',
                text: 'Got a question? Reach us from here.',
                placement: 'bottom'
            },
            {
                element: q('#luxNavButtons .lux-nav-menu-trigger'),
                title: 'Access Empire',
                text: 'One button for everything — signing in or joining us. Let\u2019s open it.',
                placement: 'bottom'
            },
            {
                before: openDesktopMenu,
                delay: 200,
                element: q('#luxNavButtons a[href*="/login"]'),
                title: 'Login',
                text: 'Already have an account? Sign in here.',
                placement: 'left'
            },
            {
                before: openDesktopMenu,
                delay: 200,
                element: q('#luxNavButtons a[href*="/browse"]'),
                title: 'Browse Listings',
                text: 'Look around without needing an account.',
                placement: 'left'
            },
            {
                before: openDesktopMenu,
                delay: 200,
                element: q('#luxNavButtons a[href*="/register/landlord"]'),
                title: 'Register as Landlord',
                text: 'List your property and find tenants.',
                placement: 'left'
            },
            {
                before: openDesktopMenu,
                delay: 200,
                element: q('#luxNavButtons a[href*="/register/driver"]'),
                title: 'Register as Driver',
                text: 'Offer moving services and start earning.',
                placement: 'left'
            },
            {
                element: null,
                title: "That's it! 🎉",
                text: "You're all set. Enjoy exploring LUX EMPIRE."
            }
        ];
    }

    function buildMobileSteps() {
        return [
            {
                element: null,
                title: 'Welcome to LUX EMPIRE! 👋',
                text: "Let's take a quick, one-minute look at how to get around. Click Next whenever you're ready."
            },
            {
                element: q('.logo'),
                title: 'Your way back home',
                text: 'Tap this logo anytime to return to this page.',
                placement: 'bottom'
            },
            {
                element: q('#luxMobileToggleBtn'),
                title: 'Open the menu',
                text: 'Tap this icon (☰) anytime to see all our pages. Let\u2019s open it.',
                placement: 'left'
            },
            {
                before: openMobilePopover,
                delay: 200,
                element: q('#luxMobileNavPopover [data-tour="m-home"]'),
                title: 'Home',
                text: 'Brings you right back to this page.',
                placement: 'left'
            },
            {
                before: openMobilePopover,
                delay: 200,
                element: q('#luxMobileNavPopover [data-tour="m-browse"]'),
                title: 'Browse Listings',
                text: 'See houses you can book — no account needed to look.',
                placement: 'left'
            },
            {
                before: openMobilePopover,
                delay: 200,
                element: q('#luxMobileNavPopover [data-tour="m-login"]'),
                title: 'Sign In',
                text: 'Already with us? Log in here.',
                placement: 'left'
            },
            {
                before: openMobilePopover,
                delay: 200,
                element: q('#luxMobileNavPopover [data-tour="m-register"]'),
                title: 'Create Account',
                text: 'New here? Join the Empire.',
                placement: 'left'
            },
            {
                before: openMobilePopover,
                delay: 200,
                element: q('#luxMobileNavPopover [data-tour="m-about"]'),
                title: 'About',
                text: 'A quick word about who we are.',
                placement: 'left'
            },
            {
                before: openMobilePopover,
                delay: 200,
                element: q('#luxMobileNavPopover [data-tour="m-contact"]'),
                title: 'Contact',
                text: 'Got a question? Reach us here.',
                placement: 'left'
            },
            {
                before: openMobilePopover,
                delay: 200,
                element: q('#luxMobileNavPopover [data-tour="m-recover"]'),
                title: 'Recover Your Account',
                text: 'Forgot your password? Fix that here.',
                placement: 'left'
            },
            {
                element: null,
                title: "That's it! 🎉",
                text: "You're all set. Enjoy exploring LUX EMPIRE."
            }
        ];
    }

    function runTour() {
        var steps = isMobileNav() ? buildMobileSteps() : buildDesktopSteps();
        var tour = new window.LuxTour(steps, {
            storageKey: STORAGE_KEY,
            onFinish: closeAnyOpenMenus,
            onSkip: closeAnyOpenMenus
        });
        tour.start();
    }

    function markSeen() {
        try { window.localStorage.setItem(STORAGE_KEY, '1'); } catch (e) { /* ignore */ }
    }

    function hasSeenTour() {
        try { return window.localStorage.getItem(STORAGE_KEY) === '1'; } catch (e) { return false; }
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (!window.LuxTour) { return; }

        // small "?" button so anyone can bring the tour back later
        window.LuxTour.addHelpButton(runTour, 'Show me around');

        if (hasSeenTour()) { return; } // never re-prompt returning visitors

        setTimeout(function () {
            window.LuxTour.prompt({
                title: 'New here?',
                text: 'Want a quick, one-minute guide to getting around LUX EMPIRE?',
                yesLabel: 'Yes, show me',
                noLabel: 'No thanks',
                onAccept: function () { markSeen(); runTour(); },
                onDecline: function () { markSeen(); }
            });
        }, 900);
    });

})();