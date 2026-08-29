/**
 * MY BOOKINGS — tabs, search, sort, status filter
 * ---------------------------------------------------------------
 * Purely client-side. Every booking/truck card already carries the data
 * it needs, rendered by dashboard/tenant/my_bookings.php:
 *   data-type      "house" | "truck"
 *   data-status    the actual status string from the database
 *   data-timestamp unix timestamp (booking_date / requested_at)
 *   data-search    lowercased title+location or pickup+destination
 *
 * No backend calls are made here — filtering/sorting only show, hide,
 * and reorder cards already present in the DOM.
 *
 * NOTE: this file previously also preserved scroll position around a
 * full-page reload triggered by the Cancel/Delete forms. Those forms
 * are now handled entirely via AJAX by assets/js/bookings.js (no
 * reload happens), so that logic no longer applies and has been
 * removed rather than left as dead code.
 */
(function () {
    'use strict';

    var tabButtons   = document.querySelectorAll('.booking-tab-btn');
    var houseSection = document.getElementById('houseBookingsSection');
    var truckSection = document.getElementById('truckRequestsSection');
    var searchInput  = document.getElementById('bookingSearchInput');
    var sortSelect   = document.getElementById('bookingSortSelect');
    var statusSelect = document.getElementById('bookingStatusSelect');

    if (!houseSection || !truckSection) {
        return;
    }

    var HOUSE_STATUSES = [
        ['pending', 'Pending'],
        ['approved', 'Approved'],
        ['rejected', 'Rejected'],
        ['cancelled', 'Cancelled']
    ];

    var TRUCK_STATUSES = [
        ['pending', 'Pending'],
        ['accepted', 'Accepted'],
        ['in_transit', 'In Transit'],
        ['completed', 'Completed'],
        ['cancelled', 'Cancelled']
    ];

    var activeTab = 'all';

    function populateStatusOptions(type) {
        var list = type === 'house' ? HOUSE_STATUSES : TRUCK_STATUSES;

        statusSelect.innerHTML = '<option value="">All Statuses</option>';

        list.forEach(function (pair) {
            var opt = document.createElement('option');
            opt.value = pair[0];
            opt.textContent = pair[1];
            statusSelect.appendChild(opt);
        });
    }

    function setTab(tab) {
        activeTab = tab;

        tabButtons.forEach(function (btn) {
            btn.classList.toggle('is-active', btn.getAttribute('data-tab') === tab);
        });

        if (tab === 'house') {
            houseSection.hidden = false;
            truckSection.hidden = true;
            sortSelect.hidden = false;
            statusSelect.hidden = false;
            populateStatusOptions('house');
        } else if (tab === 'truck') {
            houseSection.hidden = true;
            truckSection.hidden = false;
            sortSelect.hidden = false;
            statusSelect.hidden = false;
            populateStatusOptions('truck');
        } else {
            houseSection.hidden = false;
            truckSection.hidden = false;
            sortSelect.hidden = true;
            statusSelect.hidden = true;
        }

        statusSelect.value = '';
        applyFilters();
    }

    function visibleCards() {
        var cards = [];

        if (activeTab === 'all' || activeTab === 'house') {
            cards = cards.concat(Array.prototype.slice.call(
                houseSection.querySelectorAll('.booking-card[data-type="house"]')
            ));
        }

        if (activeTab === 'all' || activeTab === 'truck') {
            cards = cards.concat(Array.prototype.slice.call(
                truckSection.querySelectorAll('.booking-card[data-type="truck"]')
            ));
        }

        return cards;
    }

    function applyFilters() {
        var query = searchInput.value.trim().toLowerCase();
        var statusValue = statusSelect.hidden ? '' : statusSelect.value;

        visibleCards().forEach(function (card) {
            var matchesSearch = !query || (card.getAttribute('data-search') || '').indexOf(query) !== -1;
            var matchesStatus = !statusValue || card.getAttribute('data-status') === statusValue;
            card.hidden = !(matchesSearch && matchesStatus);
        });

        applySort();
    }

    function applySort() {
        if (sortSelect.hidden) {
            return;
        }

        var order = sortSelect.value;

        var grid = activeTab === 'house'
            ? houseSection.querySelector('.tenant-grid')
            : activeTab === 'truck'
                ? truckSection.querySelector('.tenant-grid')
                : null;

        if (!grid) {
            return;
        }

        var cards = Array.prototype.slice.call(grid.querySelectorAll('.booking-card'));

        cards.sort(function (a, b) {
            var tA = parseInt(a.getAttribute('data-timestamp'), 10) || 0;
            var tB = parseInt(b.getAttribute('data-timestamp'), 10) || 0;
            return order === 'oldest' ? (tA - tB) : (tB - tA);
        });

        cards.forEach(function (card) {
            grid.appendChild(card);
        });
    }

    tabButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            setTab(btn.getAttribute('data-tab'));
        });
    });

    searchInput.addEventListener('input', applyFilters);
    statusSelect.addEventListener('change', applyFilters);
    sortSelect.addEventListener('change', applySort);

    setTab('all');

}());