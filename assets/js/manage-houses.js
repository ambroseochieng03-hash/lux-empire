/*
=========================================
LUX EMPIRE — MANAGE HOUSES (landlord)
=========================================
AJAX delete for property cards — removes the card from the DOM
IMMEDIATELY on confirm (optimistic update), rather than waiting for
the server response. If the request later fails, the card is
restored and an error is shown. This keeps the UI feeling instant
regardless of network/connection-queue delays.
=========================================
*/

(function () {

    var cfg = window.LUX_MANAGE_HOUSES_CONFIG;

    if (!cfg) {
        return;
    }

    document.addEventListener('DOMContentLoaded', () => {

        document.querySelectorAll('.house-delete-btn').forEach((btn) => {

            btn.addEventListener('click', async () => {

                if (!confirm('Delete this property? This cannot be undone.')) {
                    return;
                }

                var houseId = btn.dataset.houseId;
                var card = document.getElementById('houseCard-' + houseId);

                if (!card) {
                    return;
                }

                /*
                 * OPTIMISTIC REMOVAL — take the card out of the DOM
                 * right now, before the network call even starts.
                 * We keep a reference + its original position so we
                 * can put it back if the delete actually fails.
                 */
                var placeholder = document.createComment('deleted-house-' + houseId);
                var parent = card.parentNode;
                parent.insertBefore(placeholder, card);
                card.remove();

                var grid = document.querySelector('.manage-grid');
                if (grid && grid.querySelectorAll('.house-card').length === 0) {
                    grid.innerHTML =
                        '<div class="lux-card manage-empty-card">' +
                        '<h2 class="manage-empty-title">No Luxury Properties Yet</h2>' +
                        '<p class="manage-empty-text">Start building your Empire portfolio now.</p>' +
                        '<a href="' + cfg.baseUrl + '/dashboard/landlord/add_house.php" class="lux-btn manage-empty-link">Add First Property</a>' +
                        '</div>';
                }

                try {

                    var formData = new URLSearchParams();
                    formData.append('house_id', houseId);
                    formData.append('csrf_token', cfg.csrfToken);

                    var response = await fetch(cfg.baseUrl + '/api/houses/delete_house.php', {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin'
                    });

                    var result = await response.json();

                    if (!response.ok || !result.success) {
                        throw new Error(result.message || 'Failed to delete property.');
                    }

                    // Success — card is already gone, nothing more to do.

                } catch (error) {

                    /*
                     * Roll back — put the card back exactly where it
                     * was, and let the landlord know it didn't work.
                     */
                    var emptyCard = document.querySelector('.manage-empty-card');
                    if (emptyCard) {
                        emptyCard.closest('.manage-grid').innerHTML = '';
                        document.querySelector('.manage-grid').appendChild(card);
                    } else if (placeholder.parentNode) {
                        placeholder.parentNode.insertBefore(card, placeholder);
                    }

                    if (placeholder.parentNode) {
                        placeholder.remove();
                    }

                    alert(error.message || 'Unable to delete property. The listing has been restored.');
                }
            });
        });
    });

})();