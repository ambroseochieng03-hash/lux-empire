<?php
require_once '../../includes/auth_check.php';
requireRoleAccess('tenant');

require_once '../../classes/House.php';

$houseModel = new House();

$id = $_GET['id'] ?? null;

if (!$id) {
    header("Location: search_houses.php?error=House not found");
    exit();
}

$house = $houseModel->getHouseById($id);

if (!$house) {
    header("Location: search_houses.php?error=House not found");
    exit();
}

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
require_once '../../includes/sidebar.php';
?>

<style>
.house-container{
    margin-left:280px;
    padding:40px;
    color:white;
}

.house-card{
    background:rgba(255,255,255,0.05);
    border-radius:28px;
    overflow:hidden;
}

.house-image{
    width:100%;
    height:70vh;
    min-height:500px;
    object-fit:cover;
    object-position:center;
}

.house-content{
    padding:30px;
}

.house-title{
    font-size:2.5rem;
    color:var(--gold);
    margin-bottom:10px;
    font-family:'Cinzel', serif;
}

.house-price{
    font-size:1.4rem;
    color:var(--gold);
    margin-bottom:20px;
}

.house-grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:20px;
    margin-top:20px;
}

.house-box{
    background:rgba(255,255,255,0.06);
    padding:15px;
    border-radius:16px;
}

.landlord-box{
    margin-top:25px;
    padding:20px;
    border-radius:16px;
    background:rgba(255,255,255,0.06);
}

.actions{
    margin-top:30px;
    display:flex;
    gap:15px;
    flex-wrap:wrap;
}

@media(max-width:768px){
    .house-container{
        margin-left:0;
        padding:20px;
    }

    .house-grid{
        grid-template-columns:1fr;
    }

    .house-image{
        height:300px;
    }
}
</style>

<div class="house-container">

    <div class="house-card">

        <!-- IMAGE -->
        <?php if (!empty($house['image'])): ?>

            <img class="house-image"
                src="../../assets/uploads/house_images/<?php echo htmlspecialchars($house['image']); ?>"
                alt="House Image">

        <?php else: ?>

            <div style="
                width:100%;
                height:450px;
                display:flex;
                align-items:center;
                justify-content:center;
                background:rgba(255,255,255,0.05);
                color:var(--gold);
                font-size:2rem;
            ">
                🏛 No Image Available
            </div>

        <?php endif; ?>

        <div class="house-content">

            <!-- TITLE -->
            <h1 class="house-title">
                <?php echo htmlspecialchars($house['title']); ?>
            </h1>

            <!-- PRICE -->
            <div class="house-price">
                💰 KES <?php echo number_format($house['price']); ?> / month
            </div>

            <!-- DESCRIPTION -->
            <p style="color:rgba(255,255,255,0.75); line-height:1.8;">
                <?php echo nl2br(htmlspecialchars($house['description'])); ?>
            </p>

            <!-- DETAILS GRID -->
            <div class="house-grid">

                <div class="house-box">
                    🛏 Bedrooms: <?php echo $house['bedrooms']; ?>
                </div>

                <div class="house-box">
                    🛁 Bathrooms: <?php echo $house['bathrooms']; ?>
                </div>

                <div class="house-box">
                    📍 Location: <?php echo htmlspecialchars($house['location']); ?>
                </div>

                <div class="house-box">
                    ⭐ Rating:
                    <?php
                        $rating = (int)($house['rating'] ?? 0);
                        for ($i = 1; $i <= 5; $i++) {
                            echo ($i <= $rating) ? '★ ' : '☆ ';
                        }
                    ?>
                </div>

            </div>

            <!-- LANDLORD INFO -->
            <div class="landlord-box">

                <h3 style="color:var(--gold); margin-bottom:15px;">
                    👑 Contact Landlord
                </h3>

                <p style="margin-bottom:15px;">
                    👤 <?php echo htmlspecialchars($house['landlord_name']); ?>
                </p>

                <div style="display:flex; gap:12px; flex-wrap:wrap;">

                    <!-- CALL BUTTON -->
                    <?php if (!empty($house['landlord_phone'])): ?>
                        <a href="tel:<?php echo htmlspecialchars($house['landlord_phone']); ?>"
                        style="
                                flex:1;
                                text-align:center;
                                padding:14px;
                                border-radius:14px;
                                background:linear-gradient(135deg,#d4af37,#f5d76e);
                                color:black;
                                font-weight:bold;
                                text-decoration:none;
                        ">
                            📞 Call
                        </a>
                    <?php endif; ?>

                    <!-- EMAIL BUTTON -->
                    <?php if (!empty($house['landlord_email'])): ?>
                        <a href="mailto:<?php echo htmlspecialchars($house['landlord_email']); ?>"
                        style="
                                flex:1;
                                text-align:center;
                                padding:14px;
                                border-radius:14px;
                                background:rgba(255,255,255,0.08);
                                border:1px solid rgba(212,175,55,0.5);
                                color:var(--gold);
                                text-decoration:none;
                        ">
                            Email
                        </a>
                    <?php endif; ?>

                </div>

            </div>

            <!-- ACTIONS -->
            <div class="actions">

                <a href="../../api/houses/book_house.php?id=<?php echo $house['id']; ?>"
                   class="lux-btn"
                   style="flex:1; text-align:center; text-decoration:none;">
                    Book Now
                </a>

                <a href="search_houses.php"
                   class="lux-btn"
                   style="flex:1; text-align:center; text-decoration:none;">
                    ← Back to Listings
                </a>

            </div>

        </div>

    </div>

</div>

<?php require_once '../../includes/footer.php'; ?>