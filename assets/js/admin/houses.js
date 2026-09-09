(function () {
    'use strict';

    var baseUrl = (window.LUX_ADMIN || {}).baseUrl || '';
    var grid = document.getElementById('luxListingGrid');

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

    // Hydrate each card's media area from its data-media attribute.
    // property-media.js's autoplay/lightbox listeners are attached
    // via event delegation on document, so injecting this markup
    // after its own script has run is still fully wired up.
    document.querySelectorAll('.lux-listing-card-media').forEach(function (el) {
        var media = {};
        try { media = JSON.parse(el.getAttribute('data-media') || '{}'); } catch (e) { media = {}; }
        el.innerHTML = buildMediaFrameHtml(media, el.getAttribute('data-caption'));
    });

    grid.addEventListener('click', function (event) {
        var btn = event.target.closest('[data-action]');
        if (!btn) { return; }

        var action = btn.getAttribute('data-action');
        var listingId = btn.getAttribute('data-listing-id');
        var card = grid.querySelector('[data-listing-card="' + listingId + '"]');

        if (action === 'toggle-hidden') {
            LuxAdmin.request(baseUrl + '/api/admin/listing_toggle_hidden.php', { body: { house_id: listingId } })
                .then(function (data) {
                    LuxAdmin.toast(data.hidden ? 'Listing hidden.' : 'Listing restored.', 'success');
                    btn.textContent = data.hidden ? 'Restore' : 'Hide';
                    card.classList.toggle('is-hidden', data.hidden);
                }).catch(function (err) { LuxAdmin.toast(err.message, 'error'); });
            return;
        }

        if (action === 'verify') {
            LuxAdmin.request(baseUrl + '/api/admin/listing_verify.php', { body: { house_id: listingId } })
                .then(function () { LuxAdmin.toast('Listing verified.', 'success'); setTimeout(function () { window.location.reload(); }, 500); })
                .catch(function (err) { LuxAdmin.toast(err.message, 'error'); });
            return;
        }

        if (action === 'flag') {
            LuxAdmin.confirm({
                title: 'Mark this listing as suspicious?',
                message: 'Optionally explain why.',
                onConfirm: function (reason) {
                    LuxAdmin.request(baseUrl + '/api/admin/listing_flag.php', { body: { house_id: listingId, reason: reason || '' } })
                        .then(function () { LuxAdmin.toast('Listing flagged.', 'success'); setTimeout(function () { window.location.reload(); }, 500); })
                        .catch(function (err) { LuxAdmin.toast(err.message, 'error'); });
                }
            });
            return;
        }

        if (action === 'unflag') {
            LuxAdmin.request(baseUrl + '/api/admin/listing_unflag.php', { body: { house_id: listingId } })
                .then(function () { LuxAdmin.toast('Flag cleared.', 'success'); setTimeout(function () { window.location.reload(); }, 500); })
                .catch(function (err) { LuxAdmin.toast(err.message, 'error'); });
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

}());
