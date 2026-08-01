<?php

require_once '../../includes/auth_check.php';
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

$stmt->execute([
    $id,
    $_SESSION['user_id']
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

$imageStmt = $pdo->prepare("
    SELECT image_path
    FROM house_images
    WHERE house_id = ?
    LIMIT 1
");

$imageStmt->execute([$id]);

$image = $imageStmt->fetch(PDO::FETCH_ASSOC);

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

                <!-- IMAGE -->

                <div class="input-group">

                    <label class="input-label">
                        Replace Property Image
                    </label>

                    <input
                        type="file"
                        name="image"
                        accept="image/*"
                        class="lux-input"
                    >

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

            <?php if (!empty($image['image_path'])): ?>

                <img
                    src="../../assets/uploads/house_images/<?=
                        htmlspecialchars($image['image_path'])
                    ?>"
                    class="preview-image"
                >

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
                    🏛
                </div>

            <?php endif; ?>

        </div>

    </div>

</main>

</div>

<script>

document
.getElementById('editHouseForm')

.addEventListener('submit', async function(e){

    e.preventDefault();

    const formData = new FormData(this);

    const response = await fetch(
        '../../api/houses/update_house.php',
        {
            method:'POST',
            body:formData
        }
    );

    const result = await response.json();

    if(result.success){

        window.location.href =
            'manage_houses.php?success='
            + encodeURIComponent(result.message);

    } else {

        window.location.href =
            'manage_houses.php?error='
            + encodeURIComponent(
                result.message || 'Update failed'
            );

    }

});

</script>

<?php require_once '../../includes/footer.php'; ?>