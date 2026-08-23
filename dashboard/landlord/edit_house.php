<?php
require_once '../../includes/init.php';
require_once '../../includes/auth_check.php';
require_once '../../config/csrf.php';
requireRoleAccess('landlord');

require_once '../../config/db.php';

$db = new Database();
$pdo = $db->connect();

$id = $_GET['id'] ?? null;

if (!$id) {
    die("Invalid property ID.");
}

/*
|--------------------------------------------------------------------------
| FETCH PROPERTY
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM houses
    WHERE id = ?
    AND landlord_id = ?
    LIMIT 1
");

$user = Session::user();

if ($user === null || !isset($user['id'])) {
    die("Unable to determine authenticated landlord.");
}

$stmt->execute([
    $id,
    (int) $user['id']
]);

$house = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$house) {
    die("Property not found.");
}

/*
|--------------------------------------------------------------------------
| FETCH IMAGE
|--------------------------------------------------------------------------
*/

$mediaStmt = $pdo->prepare("
    SELECT
        id,
        image_path,
        created_at
    FROM house_images
    WHERE house_id = ?
    ORDER BY id ASC
");

$mediaStmt->execute([$id]);

$media = $mediaStmt->fetchAll(PDO::FETCH_ASSOC);

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
require_once '../../includes/sidebar.php';


?>

<style>

.edit-main{
    flex:1;
    margin-left:280px;
    padding:40px;
    width:calc(100% - 280px);
}

.edit-grid{
    display:grid;
    grid-template-columns:1fr 420px;
    gap:30px;
    align-items:start;
}

.preview-image{
    width:100%;
    height:280px;
    object-fit:cover;
    border-radius:24px;
    display:block;
}

.input-group{
    margin-bottom:24px;
}

.input-label{
    display:block;
    margin-bottom:10px;
    color:var(--gold);
    font-weight:bold;
}

.lux-input{
    width:100%;
    padding:16px;
    border:none;
    border-radius:16px;
    background:rgba(255,255,255,0.05);
    color:white;
    outline:none;
}

.lux-textarea{
    width:100%;
    padding:16px;
    border:none;
    border-radius:16px;
    background:rgba(255,255,255,0.05);
    color:white;
    resize:none;
    outline:none;
}

.form-grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:20px;
}

.action-buttons{
    display:flex;
    gap:15px;
    flex-wrap:wrap;
    margin-top:10px;
}

.secondary-btn{
    padding:16px 22px;
    border-radius:16px;
    text-decoration:none;
    background:rgba(255,255,255,0.05);
    color:white;
}

/* MOBILE */

@media (max-width: 992px){

    .edit-main{
        margin-left:0;
        width:100%;
        padding:25px;
    }

    .edit-grid{
        grid-template-columns:1fr;
    }

}

@media (max-width: 768px){

    .edit-main{
        padding:20px 15px;
    }

    .form-grid{
        grid-template-columns:1fr;
    }

    .preview-image{
        height:220px;
    }

    .edit-title{
        font-size:2rem !important;
        line-height:1.3;
    }

}

</style>

<div style="
    display:flex;
    min-height:100vh;
">

<main class="edit-main">

    <!-- HEADER -->

    <div style="margin-bottom:35px;">

        <h1 class="edit-title" style="
            font-size:3rem;
            color:var(--gold);
            font-family:'Cinzel', serif;
            margin-bottom:12px;
        ">
            ✏ Edit Property
        </h1>

        <p style="
            color:var(--gray);
            max-width:700px;
            line-height:1.8;
        ">
            Update your luxury property information,
            pricing, location and images.
        </p>

    </div>

    <div class="edit-grid">

        <!-- FORM -->

        <div class="lux-card" style="
            padding:35px;
            border-radius:30px;
        ">

            <form
                id="editHouseForm"
                enctype="multipart/form-data"
            >

                <input
                    type="hidden"
                    name="house_id"
                    value="<?= $house['id'] ?>"
                >

                <!-- TITLE -->

                <div class="input-group">

                    <label class="input-label">
                        Property Title
                    </label>

                    <input
                        type="text"
                        name="title"
                        required
                        value="<?= htmlspecialchars($house['title'] ?? '') ?>"
                        class="lux-input"
                    >

                </div>

                <!-- DESCRIPTION -->

                <div class="input-group">

                    <label class="input-label">
                        Description
                    </label>

                    <textarea
                        name="description"
                        rows="6"
                        class="lux-textarea"
                    ><?= htmlspecialchars($house['description'] ?? '') ?></textarea>

                </div>

                <!-- GRID -->

                <div class="form-grid">

                    <div class="input-group">

                        <label class="input-label">
                            Price (KES)
                        </label>

                        <input
                            type="number"
                            name="price"
                            required
                            value="<?= htmlspecialchars($house['price'] ?? '') ?>"
                            class="lux-input"
                        >

                    </div>

                    <div class="input-group">

                        <label class="input-label">
                            House Type
                        </label>

                        <input
                            type="text"
                            name="house_type"
                            value="<?= htmlspecialchars($house['house_type'] ?? '') ?>"
                            class="lux-input"
                        >

                    </div>

                </div>

                <!-- LOCATION -->

                <div class="input-group">

                    <label class="input-label">
                        Location
                    </label>

                    <input
                        type="text"
                        name="location"
                        required
                        value="<?= htmlspecialchars($house['location'] ?? '') ?>"
                        class="lux-input"
                    >

                </div>

                <!-- BEDROOMS/BATHROOMS -->

                <div class="form-grid">

                    <div class="input-group">

                        <label class="input-label">
                            Bedrooms
                        </label>

                        <input
                            type="number"
                            name="bedrooms"
                            value="<?= htmlspecialchars($house['bedrooms'] ?? 1) ?>"
                            class="lux-input"
                        >

                    </div>

                    <div class="input-group">

                        <label class="input-label">
                            Bathrooms
                        </label>

                        <input
                            type="number"
                            name="bathrooms"
                            value="<?= htmlspecialchars($house['bathrooms'] ?? 1) ?>"
                            class="lux-input"
                        >

                    </div>

                </div>

                <!-- LUXURY RATING -->

                <div class="input-group">

                    <label class="input-label">
                        Luxury Rating
                    </label>

                    <select
                        name="rating"
                        class="lux-input"
                    >

                        <?php for($i = 5; $i >= 1; $i--): ?>

                            <option
                                value="<?= $i ?>"
                                <?= ($house['rating'] == $i) ? 'selected' : '' ?>
                            >
                                <?= str_repeat('⭐', $i) ?>
                                <?= str_repeat('☆', 5 - $i) ?>
                                (<?= $i ?> Star)
                            </option>

                        <?php endfor; ?>

                    </select>

                </div>

                <!-- MEDIA -->

                <div class="input-group">

                    <label class="input-label">
                        Replace Property Media
                    </label>

                    <p style="
                        color:var(--gray);
                        line-height:1.7;
                        margin-bottom:15px;
                    ">
                        Upload multiple images or one video.
                        New media replaces the property's current media.
                        Images and video cannot be uploaded together.
                    </p>

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars(
                            Csrf::token(),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >

                    <label style="
                        display:block;
                        margin-bottom:10px;
                        color:white;
                    ">
                        Images
                    </label>

                    <input
                        type="file"
                        id="houseImages"
                        name="images[]"
                        accept="image/jpeg,image/png,image/webp"
                        multiple
                        class="lux-input"
                    >

                    <div
                        id="imageSelection"
                        style="
                            margin-top:12px;
                            color:var(--gray);
                            font-size:.9rem;
                        "
                    ></div>

                    <div style="
                        margin:22px 0;
                        text-align:center;
                        color:var(--gray);
                    ">
                        OR
                    </div>

                    <label style="
                        display:block;
                        margin-bottom:10px;
                        color:white;
                    ">
                        Video
                    </label>

                    <input
                        type="file"
                        id="houseVideo"
                        name="video"
                        accept="video/mp4,video/webm,video/quicktime,video/x-msvideo,video/x-matroska"
                        class="lux-input"
                    >

                    <div
                        id="videoSelection"
                        style="
                            margin-top:12px;
                            color:var(--gray);
                            font-size:.9rem;
                        "
                    ></div>

                </div>

                <!-- BUTTONS -->

                <div class="action-buttons">

                    <button
                        type="submit"
                        class="lux-btn"
                        style="
                            border:none;
                            cursor:pointer;
                        "
                    >
                        Save Changes
                    </button>

                    <a
                        href="manage_houses.php"
                        class="secondary-btn"
                    >
                        ← Back
                    </a>

                </div>

            </form>

        </div>

        <!-- IMAGE PREVIEW -->

        <div class="lux-card" style="
            padding:25px;
            border-radius:30px;
        ">

            <h2 style="
                color:white;
                margin-bottom:20px;
            ">
                Property Preview
            </h2>

            <?php if (!empty($media)): ?>

                <div style="
                    display:grid;
                    gap:18px;
                ">

                    <?php foreach ($media as $item): ?>

                        <?php
                        $mediaPath =
                            $item['image_path'] ?? '';

                        $mediaUrl =
                            '../../assets/uploads/house_images/'
                            . rawurlencode($mediaPath);

                        $isVideo =
                            strtolower(
                                pathinfo(
                                    $mediaPath,
                                    PATHINFO_EXTENSION
                                )
                            ) === 'mp4';
                        ?>

                        <?php if ($isVideo): ?>

                            <video
                                controls
                                preload="metadata"
                                style="
                                    width:100%;
                                    max-height:320px;
                                    border-radius:24px;
                                    display:block;
                                    background:#000;
                                "
                            >
                                <source
                                    src="<?= htmlspecialchars(
                                        $mediaUrl,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                    type="video/mp4"
                                >
                                Your browser does not support video playback.
                            </video>

                        <?php else: ?>

                            <img
                                src="<?= htmlspecialchars(
                                    $mediaUrl,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                class="preview-image"
                                loading="lazy"
                                alt="Property media"
                            >

                        <?php endif; ?>

                    <?php endforeach; ?>

                </div>

            <?php else: ?>

                <div style="
                    height:280px;
                    border-radius:24px;
                    display:flex;
                    align-items:center;
                    justify-content:center;
                    background:rgba(255,255,255,0.05);
                    color:var(--gold);
                    font-size:4rem;
                ">
                    Property media
                </div>

            <?php endif; ?>

        </div>

    </div>

</main>

</div>

<script>

const editHouseForm =
    document.getElementById('editHouseForm');

const houseImages =
    document.getElementById('houseImages');

const houseVideo =
    document.getElementById('houseVideo');

const imageSelection =
    document.getElementById('imageSelection');

const videoSelection =
    document.getElementById('videoSelection');


/*
 * ------------------------------------------------------------
 * MEDIA SELECTION RULE
 * ------------------------------------------------------------
 *
 * Images OR video.
 */

houseImages.addEventListener(
    'change',
    function()
    {
        if (this.files.length > 0) {

            houseVideo.value = '';

            imageSelection.textContent =
                `${this.files.length} image(s) selected.`;

            videoSelection.textContent = '';
        } else {

            imageSelection.textContent = '';
        }
    }
);


houseVideo.addEventListener(
    'change',
    function()
    {
        if (this.files.length > 0) {

            houseImages.value = '';

            videoSelection.textContent =
                `Video selected: ${this.files[0].name}`;

            imageSelection.textContent = '';
        } else {

            videoSelection.textContent = '';
        }
    }
);


/*
 * ------------------------------------------------------------
 * SUBMIT
 * ------------------------------------------------------------
 */

editHouseForm.addEventListener(
    'submit',
    async function(e)
    {
        e.preventDefault();

        const formData =
            new FormData(this);

        const submitButton =
            this.querySelector(
                'button[type="submit"]'
            );

        if (submitButton) {

            submitButton.disabled = true;

            submitButton.dataset.originalText =
                submitButton.textContent;

            submitButton.textContent =
                'Saving...';
        }

        try {

            const response =
                await fetch(
                    '../../api/houses/update_house.php',
                    {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin'
                    }
                );

            let result;

            try {

                result =
                    await response.json();

            } catch (jsonError) {

                throw new Error(
                    'The server returned an invalid response.'
                );
            }

            if (!response.ok || !result.success) {

                throw new Error(
                    result.message ||
                    'Update failed.'
                );
            }

            window.location.href =
                'manage_houses.php?success='
                + encodeURIComponent(
                    result.message
                );

        } catch (error) {

            alert(
                error.message ||
                'Unable to update property.'
            );

            if (submitButton) {

                submitButton.disabled = false;

                submitButton.textContent =
                    submitButton.dataset.originalText
                    || 'Save Changes';
            }
        }
    }
);

</script>

<?php require_once '../../includes/footer.php'; ?>