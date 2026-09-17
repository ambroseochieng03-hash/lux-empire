<?php

declare(strict_types=1);

require_once '../../includes/init.php';
require_once '../../includes/auth_check.php';
requireRoleAccess('admin');

require_once '../../config/csrf.php';
require_once '../../classes/AdminListingService.php';

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
require_once '../../includes/sidebar.php';

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 30; // lower than other admin lists — each card renders media
$offset = ($page - 1) * $perPage;

$listingService = new AdminListingService();
$listResult = $listingService->listListings($perPage, $offset);
$listings = $listResult['listings'];
$totalListings = $listResult['total'];
$totalPages = max(1, (int) ceil($totalListings / $perPage));

$mediaByListing = $listingService->getMediaForListingIds(array_map(static fn ($l) => (int) $l['id'], $listings));

foreach ($listings as &$listing) {
    $listing['media'] = $mediaByListing[(int) $listing['id']] ?? ['video' => null, 'images' => []];
}
unset($listing);

$csrfToken = Csrf::token();
?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/admin.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/admin-cards.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/property-media.css">

<script>
    window.LUX_ADMIN = {
        csrfToken: "<?php echo htmlspecialchars($csrfToken, ENT_QUOTES); ?>",
        baseUrl: "<?php echo BASE_URL; ?>"
    };
</script>

<div class="lux-dashboard-layout">

<main class="lux-dashboard-main">

    <div class="lux-page-header">
        <h1 class="lux-page-title">Property Oversight</h1>
        <p class="lux-page-subtitle">
            Monitor listed properties, remove fraudulent listings,
            verify legitimate ones, and supervise housing operations
            platform-wide.
        </p>
    </div>

    <div class="lux-card-grid" id="luxListingGrid">

        <?php foreach ($listings as $listing): ?>
            <?php
                $cardClasses = 'lux-entity-card';
                if (!empty($listing['is_flagged'])) { $cardClasses .= ' is-flagged'; }
                if ((int) $listing['is_hidden'] === 1) { $cardClasses .= ' is-hidden'; }

                $mediaJson = json_encode($listing['media']);
            ?>

            <div class="<?php echo $cardClasses; ?>" data-listing-card="<?php echo (int) $listing['id']; ?>">

                <div class="lux-listing-card-media" data-media="<?php echo htmlspecialchars($mediaJson, ENT_QUOTES); ?>" data-caption="<?php echo htmlspecialchars($listing['title'], ENT_QUOTES); ?>"></div>

                <div class="lux-entity-card-header">
                    <div>
                        <div class="lux-entity-name"><?php echo htmlspecialchars($listing['title']); ?></div>
                        <div class="lux-entity-meta">
                            <?php echo htmlspecialchars($listing['location']); ?><br>
                            KES <?php echo number_format((float) $listing['price']); ?>
                        </div>
                    </div>
                </div>

                <div class="lux-entity-meta">
                    Landlord: <?php echo htmlspecialchars($listing['landlord_name']); ?><br>
                    <?php echo htmlspecialchars($listing['landlord_email']); ?>
                </div>

                <div>
                    <?php if (!empty($listing['is_flagged'])): ?>
                        <span class="lux-badge lux-badge-flagged">Flagged</span>
                    <?php endif; ?>
                    <?php if ((int) $listing['is_hidden'] === 1): ?>
                        <span class="lux-badge lux-badge-suspended">Hidden</span>
                    <?php endif; ?>
                    <?php if (!empty($listing['verified_at'])): ?>
                        <span class="lux-badge lux-badge-verified">Verified</span>
                    <?php endif; ?>
                </div>

                <div class="lux-entity-actions">
                    <button class="lux-btn lux-btn-ghost" data-action="toggle-hidden" data-listing-id="<?php echo (int) $listing['id']; ?>">
                        <?php echo (int) $listing['is_hidden'] === 1 ? 'Restore' : 'Hide'; ?>
                    </button>

                    <?php if (empty($listing['verified_at'])): ?>
                        <button class="lux-btn lux-btn-info" data-action="verify" data-listing-id="<?php echo (int) $listing['id']; ?>">Verify</button>
                    <?php endif; ?>

                    <?php if (!empty($listing['is_flagged'])): ?>
                        <button class="lux-btn lux-btn-ghost" data-action="unflag" data-listing-id="<?php echo (int) $listing['id']; ?>">Unmark Suspicious</button>
                    <?php else: ?>
                        <button class="lux-btn lux-btn-warning" data-action="flag" data-listing-id="<?php echo (int) $listing['id']; ?>">Mark Suspicious</button>
                    <?php endif; ?>

                    <button class="lux-btn lux-btn-outline-danger" data-action="delete" data-listing-id="<?php echo (int) $listing['id']; ?>">Delete</button>
                </div>

            </div>

        <?php endforeach; ?>

    </div>

    <?php if ($totalPages > 1): ?>
    <div style="display:flex; align-items:center; justify-content:center; gap:16px; margin-top:30px;">
        <?php if ($page > 1): ?>
            <a class="lux-btn lux-btn-ghost" href="?page=<?php echo $page - 1; ?>">&laquo; Previous</a>
        <?php endif; ?>

        <span style="color:var(--gray);">Page <?php echo $page; ?> of <?php echo $totalPages; ?> (<?php echo $totalListings; ?> listings)</span>

        <?php if ($page < $totalPages): ?>
            <a class="lux-btn lux-btn-ghost" href="?page=<?php echo $page + 1; ?>">Next &raquo;</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</main>

</div>

<div class="lux-modal-overlay" id="luxConfirmModal" aria-hidden="true">
    <div class="lux-modal-box">
        <h3 class="lux-confirm-title">Are you sure?</h3>
        <p class="lux-confirm-message"></p>
        <div class="lux-confirm-reason-wrap" hidden>
            <textarea class="lux-confirm-reason-input" placeholder="Reason (required)"></textarea>
        </div>
        <div class="lux-modal-actions">
            <button class="lux-btn lux-btn-ghost" data-confirm-close>Cancel</button>
            <button class="lux-btn lux-btn-danger lux-confirm-accept">Confirm</button>
        </div>
    </div>
</div>

<div id="mediaLightbox" class="media-lightbox" aria-hidden="true">
    <div class="media-lightbox-overlay" data-media-close></div>
    <div class="media-lightbox-content">
        <button class="media-lightbox-close" data-media-close>&times;</button>
        <button class="media-lightbox-nav media-lightbox-prev">&#8249;</button>
        <div class="media-lightbox-stage">
            <img class="media-lightbox-image" alt="">
        </div>
        <button class="media-lightbox-nav media-lightbox-next">&#8250;</button>
        <div class="media-lightbox-counter"></div>
    </div>
</div>

<script src="<?php echo BASE_URL; ?>/assets/js/admin/admin-core.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/admin/houses.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/property-media.js"></script>

<?php require_once '../../includes/footer.php'; ?>