/**
 * LUX EMPIRE — Admin Users Page
 * Wires suspend/activate/flag/unflag/verify/delete and the landlord
 * "view listings" drill-down, via LuxAdmin (admin-core.js).
 */
(function () {
    'use strict';

    var baseUrl = (window.LUX_ADMIN || {}).baseUrl || '';
    var grid = document.getElementById('luxUserGrid');
    var roleFilter = document.getElementById('luxUserRoleFilter');

    var landlordModal = document.getElementById('luxLandlordListingsModal');
    var landlordModalTitle = document.getElementById('luxLandlordListingsTitle');
    var landlordModalGrid = document.getElementById('luxLandlordListingsGrid');

    function findCard(userId) {
        return grid.querySelector('[data-user-card="' + userId + '"]');
    }

    if (roleFilter) {
        roleFilter.addEventListener('change', function () {
            var value = roleFilter.value;
            var url = new URL(window.location.href);
            if (value) {
                url.searchParams.set('role', value);
            } else {
                url.searchParams.delete('role');
            }
            url.searchParams.delete('page'); // changing the filter always starts back at page 1
            window.location.href = url.toString();
        });
    }

    function performAction(url, body, card, successMessage, reloadAfter) {
        LuxAdmin.request(url, { body: body }).then(function (data) {
            LuxAdmin.toast(successMessage, 'success');
            if (reloadAfter) {
                setTimeout(function () { window.location.reload(); }, 600);
                return;
            }
            if (card && data.status) {
                var badge = card.querySelector('[data-status-badge]');
                if (badge && (data.status === 'active' || data.status === 'suspended')) {
                    badge.textContent = data.status.charAt(0).toUpperCase() + data.status.slice(1);
                    badge.className = 'lux-badge ' + (data.status === 'active' ? 'lux-badge-active' : 'lux-badge-suspended');
                }
            }
        }).catch(function (err) {
            LuxAdmin.toast(err.message, 'error');
        });
    }

    grid.addEventListener('click', function (event) {
        var btn = event.target.closest('[data-action]');
        if (!btn) {
            return;
        }

        var action = btn.getAttribute('data-action');
        var userId = btn.getAttribute('data-user-id');
        var card = userId ? findCard(userId) : null;

        if (action === 'suspend') {
            performAction(baseUrl + '/api/admin/user_suspend.php', { user_id: userId }, card, 'User suspended.', true);
            return;
        }

        if (action === 'activate') {
            performAction(baseUrl + '/api/admin/user_activate.php', { user_id: userId }, card, 'User activated.', true);
            return;
        }

        if (action === 'unflag') {
            performAction(baseUrl + '/api/admin/user_unflag.php', { user_id: userId }, card, 'Flag cleared.', true);
            return;
        }

        if (action === 'verify') {
            LuxAdmin.confirm({
                title: 'Verify this account?',
                message: 'The user will receive an email confirming their account has been verified.',
                onConfirm: function () {
                    performAction(baseUrl + '/api/admin/user_verify.php', { user_id: userId }, card, 'Account verified.', true);
                }
            });
            return;
        }

        if (action === 'flag') {
            LuxAdmin.confirm({
                title: 'Flag this user?',
                message: 'Optionally explain why this account is being flagged for review.',
                onConfirm: function (reason) {
                    performAction(baseUrl + '/api/admin/user_flag.php', { user_id: userId, reason: reason || '' }, card, 'User flagged.', true);
                }
            });
            return;
        }

        if (action === 'delete') {
            LuxAdmin.confirm({
                title: 'Permanently delete this account?',
                message: 'This cannot be undone. The user and all associated data will be permanently removed.',
                onConfirm: function (reason) {
                    LuxAdmin.request(baseUrl + '/api/admin/user_delete.php', {
                        body: { user_id: userId, reason: reason || '' }
                    }).then(function () {
                        LuxAdmin.toast('User deleted.', 'success');
                        if (card) { card.remove(); }
                    }).catch(function (err) {
                        LuxAdmin.toast(err.message, 'error');
                    });
                }
            });
            return;
        }

        if (action === 'reveal-identity') {
            var panel = document.getElementById('luxIdentityPanel-' + userId);

            if (!panel) { return; }

            // Toggle closed if already open and populated.
            if (!panel.hidden) {
                panel.hidden = true;
                panel.innerHTML = '';
                btn.textContent = 'Reveal Identity';
                return;
            }

            btn.disabled = true;
            btn.textContent = 'Loading...';

            LuxAdmin.request(baseUrl + '/api/admin/user_reveal_identity.php', { body: { user_id: userId } })
                .then(function (data) {
                    panel.innerHTML =
                        '<div class="lux-identity-panel-label">' + data.identity.type + '</div>' +
                        '<div class="lux-identity-panel-value"></div>';
                    panel.querySelector('.lux-identity-panel-value').textContent = data.identity.value;
                    panel.hidden = false;
                    btn.disabled = false;
                    btn.textContent = 'Hide Identity';
                })
                .catch(function (err) {
                    LuxAdmin.toast(err.message, 'error');
                    btn.disabled = false;
                    btn.textContent = 'Reveal Identity';
                });
            return;
        }

        if (action === 'view-listings') {
            openLandlordListings(btn.getAttribute('data-landlord-id'), btn.getAttribute('data-landlord-name'));
        }
    });

    var dmModal = document.getElementById('luxDirectMessageModal');
    var dmSubject = document.getElementById('luxDmSubject');
    var dmBody = document.getElementById('luxDmBody');
    var dmTargetUserId = null;

    var dmIdempotencyKey = null;

    grid.addEventListener('click', function (event) {
        var btn = event.target.closest('[data-action="message"]');
        if (!btn) { return; }

        dmTargetUserId = btn.getAttribute('data-user-id');
        dmSubject.value = '';
        dmBody.value = '';
        dmIdempotencyKey = LuxIdempotency.generate(); // fresh key per opened attempt
        dmModal.classList.add('is-open');
        dmModal.setAttribute('aria-hidden', 'false');
    });

    document.querySelectorAll('[data-dm-close]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            dmModal.classList.remove('is-open');
            dmModal.setAttribute('aria-hidden', 'true');
        });
    });

    var luxDmSendBtn = document.getElementById('luxDmSend');

    luxDmSendBtn.addEventListener('click', function () {
        var subject = dmSubject.value.trim();
        var body = dmBody.value.trim();

        if (!subject || !body || !dmTargetUserId) {
            LuxAdmin.toast('Subject and message are required.', 'error');
            return;
        }

        if (luxDmSendBtn.disabled) {
            // Already sending this exact attempt — ignore further
            // clicks until it resolves.
            return;
        }

        luxDmSendBtn.disabled = true;
        var originalText = luxDmSendBtn.textContent;
        luxDmSendBtn.textContent = 'Sending...';

        LuxAdmin.request(baseUrl + '/api/admin/direct_message_send.php', {
            body: {
                user_id: dmTargetUserId,
                subject: subject,
                body: body,
                idempotency_key: dmIdempotencyKey
            }
        }).then(function () {
            LuxAdmin.toast('Message sent.', 'success');
            dmModal.classList.remove('is-open');
            dmModal.setAttribute('aria-hidden', 'true');
        }).catch(function (err) {
            LuxAdmin.toast(err.message, 'error');
            // Keep the same key on failure — if this attempt
            // actually succeeded server-side despite the client
            // seeing an error, a retry with the same key replays
            // that result instead of sending the message again.
        }).finally(function () {
            luxDmSendBtn.disabled = false;
            luxDmSendBtn.textContent = originalText;
        });
    });

    function openLandlordListings(landlordId, landlordName) {
        landlordModalTitle.textContent = 'Listings — ' + landlordName;
        landlordModalGrid.innerHTML = '<p class="lux-entity-meta">Loading...</p>';
        landlordModal.classList.add('is-open');
        landlordModal.setAttribute('aria-hidden', 'false');

        LuxAdmin.request(baseUrl + '/api/admin/user_listings.php?landlord_id=' + encodeURIComponent(landlordId), { method: 'GET' })
            .then(function (data) { renderLandlordListings(data.listings || []); })
            .catch(function (err) {
                landlordModalGrid.innerHTML = '<p class="lux-entity-meta">' + err.message + '</p>';
            });
    }

    function escapeAttr(value) {
        return String(value || '').replace(/"/g, '&quot;');
    }

    function buildMediaFrameHtml(media, caption) {
        media = media || { video: null, images: [] };

        if (media.video) {
            return '<div class="media-frame" data-video="' + escapeAttr(media.video) + '" data-caption="' + escapeAttr(caption) + '">' +
                       '<video class="media-video" src="' + escapeAttr(media.video) + '" muted playsinline></video>' +
                       '<button class="media-enlarge-btn" type="button">&#128470;</button>' +
                   '</div>';
        }

        if (media.images && media.images.length) {
            var slidesHtml = media.images.map(function (src, i) {
                return '<img class="media-slide' + (i === 0 ? ' is-active' : '') + '" data-index="' + i + '" src="' + escapeAttr(src) + '" alt="">';
            }).join('');

            var dotsHtml = media.images.length > 1 ? media.images.map(function (src, i) {
                return '<span class="media-dot' + (i === 0 ? ' is-active' : '') + '" data-index="' + i + '"></span>';
            }).join('') : '';

            var controls = media.images.length > 1
                ? '<button class="media-carousel-btn media-carousel-prev" type="button">&#8249;</button>' +
                  '<button class="media-carousel-btn media-carousel-next" type="button">&#8250;</button>' +
                  '<div class="media-carousel-dots">' + dotsHtml + '</div>'
                : '';

            return '<div class="media-frame" data-images=\'' + JSON.stringify(media.images) + '\' data-caption="' + escapeAttr(caption) + '" data-current-index="0">' +
                       '<div class="media-carousel"><div class="media-carousel-track">' + slidesHtml + '</div>' + controls + '</div>' +
                       '<button class="media-enlarge-btn" type="button">&#128470;</button>' +
                   '</div>';
        }

        return '<div class="house-placeholder">No Media</div>';
    }

    function renderLandlordListings(listings) {
        if (!listings.length) {
            landlordModalGrid.innerHTML = '<p class="lux-entity-meta">This landlord has no listings.</p>';
            return;
        }

        landlordModalGrid.innerHTML = '';

        listings.forEach(function (listing) {
            var card = document.createElement('div');
            card.className = 'lux-entity-card';
            card.setAttribute('data-listing-card', listing.id);

            var flaggedBadge = listing.is_flagged ? '<span class="lux-badge lux-badge-flagged">Flagged</span>' : '';
            var verifiedBadge = listing.verified_at ? '<span class="lux-badge lux-badge-verified">Verified</span>' : '';
            var hiddenBadge = Number(listing.is_hidden) === 1 ? '<span class="lux-badge lux-badge-suspended">Hidden</span>' : '';

            card.innerHTML =
                '<div class="lux-listing-card-media">' + buildMediaFrameHtml(listing.media, listing.title) + '</div>' +
                '<div class="lux-entity-name"></div>' +
                '<div class="lux-entity-meta"></div>' +
                '<div>' + flaggedBadge + verifiedBadge + hiddenBadge + '</div>' +
                '<div class="lux-entity-actions">' +
                    '<button class="lux-btn lux-btn-ghost" data-listing-action="toggle-hidden" data-listing-id="' + listing.id + '">' +
                        (Number(listing.is_hidden) === 1 ? 'Restore' : 'Hide') +
                    '</button>' +
                    (listing.verified_at ? '' :
                        '<button class="lux-btn lux-btn-info" data-listing-action="verify" data-listing-id="' + listing.id + '">Verify</button>') +
                    (Number(listing.is_flagged) === 1 ?
                        '<button class="lux-btn lux-btn-ghost" data-listing-action="unflag" data-listing-id="' + listing.id + '">Unmark Suspicious</button>' :
                        '<button class="lux-btn lux-btn-warning" data-listing-action="flag" data-listing-id="' + listing.id + '">Mark Suspicious</button>') +
                    '<button class="lux-btn lux-btn-outline-danger" data-listing-action="delete" data-listing-id="' + listing.id + '">Delete</button>' +
                '</div>';

            card.querySelector('.lux-entity-name').textContent = listing.title;
            card.querySelector('.lux-entity-meta').textContent = listing.location + ' — KES ' + Number(listing.price).toLocaleString();

            landlordModalGrid.appendChild(card);
        });
    }

    landlordModalGrid.addEventListener('click', function (event) {
        var btn = event.target.closest('[data-listing-action]');
        if (!btn) { return; }

        var action = btn.getAttribute('data-listing-action');
        var listingId = btn.getAttribute('data-listing-id');
        var card = landlordModalGrid.querySelector('[data-listing-card="' + listingId + '"]');

        if (action === 'toggle-hidden') {
            LuxAdmin.request(baseUrl + '/api/admin/listing_toggle_hidden.php', { body: { house_id: listingId } })
                .then(function (data) {
                    LuxAdmin.toast(data.hidden ? 'Listing hidden.' : 'Listing restored.', 'success');
                    btn.textContent = data.hidden ? 'Restore' : 'Hide';
                }).catch(function (err) { LuxAdmin.toast(err.message, 'error'); });
            return;
        }

        if (action === 'verify') {
            LuxAdmin.request(baseUrl + '/api/admin/listing_verify.php', { body: { house_id: listingId } })
                .then(function () { LuxAdmin.toast('Listing verified.', 'success'); btn.remove(); })
                .catch(function (err) { LuxAdmin.toast(err.message, 'error'); });
            return;
        }

        if (action === 'flag') {
            LuxAdmin.confirm({
                title: 'Mark this listing as suspicious?',
                message: 'Optionally explain why.',
                onConfirm: function (reason) {
                    LuxAdmin.request(baseUrl + '/api/admin/listing_flag.php', { body: { house_id: listingId, reason: reason || '' } })
                        .then(function () {
                            LuxAdmin.toast('Listing flagged.', 'success');
                            btn.textContent = 'Unmark Suspicious';
                            btn.setAttribute('data-listing-action', 'unflag');
                        }).catch(function (err) { LuxAdmin.toast(err.message, 'error'); });
                }
            });
            return;
        }

        if (action === 'unflag') {
            LuxAdmin.request(baseUrl + '/api/admin/listing_unflag.php', { body: { house_id: listingId } })
                .then(function () {
                    LuxAdmin.toast('Flag cleared.', 'success');
                    btn.textContent = 'Mark Suspicious';
                    btn.setAttribute('data-listing-action', 'flag');
                }).catch(function (err) { LuxAdmin.toast(err.message, 'error'); });
            return;
        }

        if (action === 'delete') {
            LuxAdmin.confirm({
                title: 'Permanently delete this listing?',
                message: 'This cannot be undone. All images/videos will be permanently removed.',
                requireReason: true,
                onConfirm: function (reason) {
                    LuxAdmin.request(baseUrl + '/api/admin/listing_delete.php', { body: { house_id: listingId, reason: reason } })
                        .then(function () {
                            LuxAdmin.toast('Listing deleted.', 'success');
                            if (card) { card.remove(); }
                        }).catch(function (err) { LuxAdmin.toast(err.message, 'error'); });
                }
            });
        }
    });

    document.querySelectorAll('[data-landlord-modal-close]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            landlordModal.classList.remove('is-open');
            landlordModal.setAttribute('aria-hidden', 'true');
        });
    });

}());
