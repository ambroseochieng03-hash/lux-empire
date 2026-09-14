/**
 * LUX EMPIRE — DASHBOARD SIDEBAR TOUR
 * SAVE AT: assets/js/dashboard-tour.js
 *
 * Loaded on dashboard pages only, right after product-tour.js.
 * Reads window.LUX_TOUR_USER (set by sidebar.php) to know who's
 * logged in, which role's sidebar to explain, and whether they've
 * seen this dashboard's tour before (tracked per-user, so a shared
 * computer doesn't skip the tour for a second person).
 *
 * On phones/tablets the sidebar starts hidden behind
 * .lux-sidebar-toggle — the tour opens it for you (by clicking the
 * real button, so it behaves exactly like it would if the person
 * opened it themselves) before explaining what's inside.
 *
 * Requires window.LUX_TOUR_USER = { id, role, name } — see the
 * integration notes for the one line to add to sidebar.php.
 */
(function () {
    'use strict';

    function isMobileSidebar() {
        var toggle = document.querySelector('.lux-sidebar-toggle');
        return !!toggle && toggle.offsetParent !== null;
    }

    function openSidebarIfNeeded() {
        var sidebar = document.getElementById('luxSidebar');
        var toggle = document.querySelector('.lux-sidebar-toggle');
        if (isMobileSidebar() && sidebar && !sidebar.classList.contains('active')) {
            toggle.click(); // reuse the site's own toggle logic
        }
    }

    function q(sel) {
        return function () { return document.querySelector(sel); };
    }

    var ROLE_INTRO = {
        tenant: 'This is your control center for finding homes and booking moves.',
        landlord: 'This is where you manage your properties and bookings.',
        driver: 'This is your control center for moving jobs and deliveries.',
        admin: 'This is your control center for running the whole platform.'
    };

    var ROLE_LABEL = {
        tenant: 'Your Tenant Portal',
        landlord: 'Your Landlord Portal',
        driver: 'Your Driver Portal',
        admin: 'Your Admin HQ'
    };

    // hrefSuffix uses $= matching so it works no matter the site's BASE_URL prefix
    var ROLE_LINKS = {
        tenant: [
            { hrefSuffix: '/tenant', title: 'Dashboard', text: 'Your quick overview — bookings and requests at a glance.' },
            { hrefSuffix: '/tenant/search-houses', title: 'Find Homes', text: 'Browse and search for houses to rent.' },
            { hrefSuffix: '/tenant/my-bookings', title: 'My Bookings', text: 'See your booking requests and truck requests here.' },
            { hrefSuffix: '/tenant/request-truck', title: 'Request Move', text: 'Need a truck? Request one here.' },
            { hrefSuffix: '/tenant/track-driver', title: 'Track Driver', text: "Watch your driver's location live once they're on the way." },
            { hrefSuffix: '/tenant/messages', title: 'Chats', text: 'Message landlords and drivers directly.' },
            { hrefSuffix: '/tenant/notifications', title: 'Notifications', text: 'Updates about your bookings land here.' }
        ],
        landlord: [
            { hrefSuffix: '/landlord', title: 'Dashboard', text: 'Your quick overview of properties and bookings.' },
            { hrefSuffix: '/add-property', title: 'Add Property', text: 'List a new home for rent.' },
            { hrefSuffix: '/manage-houses', title: 'Manage Estates', text: 'Edit or update your current listings.' },
            { hrefSuffix: '/booking-requests', title: 'Booking Requests', text: 'See who wants to book your properties.' },
            { hrefSuffix: '/landlord/messages', title: 'Chats', text: 'Message your tenants directly.' },
            { hrefSuffix: '/landlord/notifications', title: 'Notifications', text: 'Updates about your properties land here.' }
        ],
        driver: [
            { hrefSuffix: '/driver', title: 'Dashboard', text: 'Your quick overview of jobs and trips.' },
            { hrefSuffix: '/driver/available-requests', title: 'Available Jobs', text: 'See moving jobs you can accept.' },
            { hrefSuffix: '/driver/messages', title: 'Chats', text: 'Message tenants directly.' },
            { hrefSuffix: '/driver/notifications', title: 'Notifications', text: 'Updates about your jobs land here.' },
            { hrefSuffix: '/driver/active-trip', title: 'Active Trip', text: 'Manage your current delivery.' },
            { hrefSuffix: '/driver/location-tracker', title: 'Live Tracker', text: 'Share your live location with tenants.' }
        ],
        admin: [
            { hrefSuffix: '/admin', title: 'Empire HQ', text: 'Your overview of the whole platform.' },
            { hrefSuffix: '/admin/users', title: 'Users', text: 'Manage everyone on the platform.' },
            { hrefSuffix: '/admin/houses', title: 'Estates', text: 'Oversee all property listings.' },
            { hrefSuffix: '/admin/truck-requests', title: 'Logistics', text: 'Keep an eye on all truck requests.' },
            { hrefSuffix: '/admin/reports', title: 'Reports', text: 'See platform reports and stats.' },
            { hrefSuffix: '/admin/emergency', title: 'Emergencies', text: 'Respond to emergency alerts fast.' },
            { hrefSuffix: '/admin/bookings', title: 'Bookings', text: 'Manage all booking activity.' },
            { hrefSuffix: '/admin/messages', title: 'Broadcast', text: 'Send messages to your users.' }
        ]
    };

    function buildSteps(user) {
        var role = user.role;
        var links = ROLE_LINKS[role] || [];
        var steps = [];

        if (isMobileSidebar()) {
            steps.push({
                element: q('.lux-sidebar-toggle'),
                title: 'Open your menu',
                text: 'On phones, tap this button anytime to open your side menu.',
                placement: 'top'
            });
        }

        steps.push({
            before: openSidebarIfNeeded,
            element: q('.sidebar-brand'),
            title: ROLE_LABEL[role] || 'Your Portal',
            text: ROLE_INTRO[role] || 'This is your control center.',
            placement: 'right'
        });

        steps.push({
            before: openSidebarIfNeeded,
            element: q('.sidebar-user'),
            title: "That's you",
            text: "Shows you're signed in, front and center.",
            placement: 'right'
        });

        links.forEach(function (link) {
            steps.push({
                before: openSidebarIfNeeded,
                element: q('.sidebar-nav a[href$="' + link.hrefSuffix + '"]'),
                title: link.title,
                text: link.text,
                placement: 'right'
            });
        });

        if (role === 'tenant' || role === 'driver') {
            steps.push({
                before: openSidebarIfNeeded,
                element: q('.lux-emergency-trigger-btn'),
                title: 'Emergency',
                text: 'If something goes wrong during a move, tap this for immediate help.',
                placement: 'right'
            });
        }

        steps.push({
            before: openSidebarIfNeeded,
            element: q('#luxLogoutTrigger'),
            title: 'Exit Empire',
            text: 'Tap here anytime to sign out safely.',
            placement: 'right'
        });

        steps.push({
            element: null,
            title: 'All set! 🎉',
            text: 'Enjoy your stay in the Empire.'
        });

        return steps;
    }

    function storageKeyFor(user) {
        return 'lux_dash_tour_seen_' + user.role + '_' + user.id;
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (!window.LuxTour || !window.LUX_TOUR_USER || !window.LUX_TOUR_USER.id) { return; }

        var user = window.LUX_TOUR_USER;
        var key = storageKeyFor(user);

        function runTour() {
            var tour = new window.LuxTour(buildSteps(user), { storageKey: key });
            tour.start();
        }

        function markSeen() {
            try { window.localStorage.setItem(key, '1'); } catch (e) { /* ignore */ }
        }

        function hasSeenTour() {
            try { return window.localStorage.getItem(key) === '1'; } catch (e) { return false; }
        }

        window.LuxTour.addHelpButton(runTour, 'Show me around');

        if (hasSeenTour()) { return; }

        var firstName = (user.name || '').split(' ')[0] || 'there';

        setTimeout(function () {
            window.LuxTour.prompt({
                title: 'Hi ' + firstName + '! 👋',
                text: 'Want a quick, one-minute tour of your new dashboard?',
                yesLabel: 'Yes, show me',
                noLabel: 'No thanks',
                onAccept: function () { markSeen(); runTour(); },
                onDecline: function () { markSeen(); }
            });
        }, 700);
    });

})();
