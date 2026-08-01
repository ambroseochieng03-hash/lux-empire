<?php
require_once '../../includes/auth_check.php';
requireRoleAccess('landlord');

require_once '../../classes/House.php';

$houseModel = new House();

$houses = $houseModel->getHousesByLandlord($_SESSION['user_id']);

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
require_once '../../includes/sidebar.php';
?>

<style>

    .manage-main{
        flex:1;
        padding:40px;
        margin-left:280px;
        width:calc(100% - 280px);
        overflow-x:hidden;
    }

    .manage-header{
        display:flex;
        justify-content:space-between;
        align-items:center;
        margin-bottom:35px;
        flex-wrap:wrap;
        gap:20px;
    }

    .manage-grid{
        display:grid;
        grid-template-columns:repeat(auto-fit,minmax(320px,1fr));
        gap:30px;
    }

    .house-card{
        overflow:hidden;
        border-radius:24px;
        width:100%;
    }

    .house-image{
        height:240px;
        overflow:hidden;
        background:rgba(255,255,255,0.04);
        display:flex;
        align-items:center;
        justify-content:center;
    }

    .house-image img{
        width:100%;
        height:100%;
        object-fit:cover;
        display:block;
    }

    .house-placeholder{
        color:var(--gold);
        font-size:2rem;
    }

    .house-content{
        padding:25px;
    }

    .house-title{
        color:white;
        margin-bottom:10px;
        font-size:1.5rem;
        line-height:1.4;
    }

    .house-description{
        color:var(--gray);
        margin-bottom:18px;
        line-height:1.7;
        word-break:break-word;
    }

    .house-details{
        display:flex;
        justify-content:space-between;
        margin-bottom:18px;
        flex-wrap:wrap;
        gap:10px;
    }

    .house-meta{
        display:flex;
        justify-content:space-between;
        margin-bottom:22px;
        color:var(--gray);
        font-size:0.95rem;
        gap:10px;
        flex-wrap:wrap;
    }

    .house-actions{
        display:flex;
        gap:12px;
        flex-wrap:wrap;
    }

    .action-btn{
        flex:1;
        min-width:140px;
        text-align:center;
        text-decoration:none;
        padding:14px;
        border-radius:14px;
        font-weight:bold;
        transition:0.3s;
        box-sizing:border-box;
    }

    .edit-btn{
        background:linear-gradient(135deg,#d4af37,#f5d76e);
        color:black;
    }

    .edit-btn:hover{
        transform:translateY(-2px);
    }

    .delete-btn{
        background:#ff3b3b;
        color:white;
    }

    .delete-btn:hover{
        opacity:0.9;
    }

    /* MOBILE RESPONSIVE */
    @media (max-width: 992px){

        .manage-main{
            margin-left:0 !important;
            width:100%;
            padding:25px;
        }

    }

    @media (max-width: 768px){

        .manage-main{
            padding:20px 16px;
        }

        .manage-header h1{
            font-size:2rem !important;
            line-height:1.3;
        }

        .manage-grid{
            grid-template-columns:1fr;
            gap:22px;
        }

        .house-image{
            height:220px;
        }

        .house-content{
            padding:20px;
        }

        .house-title{
            font-size:1.25rem;
        }

        .house-details{
            flex-direction:column;
            align-items:flex-start;
        }

        .house-meta{
            flex-direction:column;
            align-items:flex-start;
        }

        .house-actions{
            flex-direction:column;
        }

        .action-btn{
            width:100%;
        }

    }

</style>

<div style="
    display:flex;
    min-height:100vh;
">

    <!-- MAIN -->
    <main class="manage-main">

        <!-- HEADER -->
        <div class="manage-header">

            <div>

                <h1 style="
                    font-family:'Cinzel', serif;
                    color:var(--gold);
                    font-size:3rem;
                    margin-bottom:10px;
                ">
                    🏛 My Luxury Properties
                </h1>

                <p style="
                    color:var(--gray);
                ">
                    Manage your premium Empire listings.
                </p>

            </div>

            <a href="add_house.php"
               class="lux-btn"
               style="
                    text-decoration:none;
                    white-space:nowrap;
               ">
               ➕ Add Property
            </a>

        </div>

        <!-- SUCCESS MESSAGE -->
        <?php if (isset($_GET['success'])): ?>

            <div style="
                background: rgba(0,255,100,0.08);
                border: 1px solid rgba(0,255,100,0.25);
                padding: 14px;
                border-radius: 14px;
                margin-bottom: 25px;
                color: #b8ffd2;
            ">
                <?php echo htmlspecialchars($_GET['success']); ?>
            </div>

        <?php endif; ?>

        <!-- ERROR MESSAGE -->
        <?php if (isset($_GET['error'])): ?>

            <div style="
                background: rgba(255,0,0,0.08);
                border: 1px solid rgba(255,0,0,0.25);
                padding: 14px;
                border-radius: 14px;
                margin-bottom: 25px;
                color: #ffb3b3;
            ">
                <?php echo htmlspecialchars($_GET['error']); ?>
            </div>

        <?php endif; ?>

        <!-- GRID -->
        <div class="manage-grid">

            <?php if (count($houses) > 0): ?>

                <?php foreach ($houses as $house): ?>

                    <div class="lux-card house-card">

                        <!-- IMAGE -->
                        <div class="house-image">

                            <?php if (!empty($house['image'])): ?>

                                <img
                                    src="../../assets/uploads/house_images/<?php echo htmlspecialchars($house['image']); ?>"
                                    alt="House Image"
                                >

                            <?php else: ?>

                                <div class="house-placeholder">
                                    🏛
                                </div>

                            <?php endif; ?>

                        </div>

                        <!-- CONTENT -->
                        <div class="house-content">

                            <h2 class="house-title">
                                <?php echo htmlspecialchars($house['title']); ?>
                            </h2>

                            <!-- RATING -->
                            <div style="
                                margin-bottom:16px;
                                display:flex;
                                align-items:center;
                                gap:10px;
                                flex-wrap:wrap;
                            ">

                                <?php
                                    $rating = (int)($house['rating'] ?? 0);
                                ?>

                                <div style="
                                    display:flex;
                                    gap:3px;
                                    font-size:1.1rem;
                                ">

                                    <?php for($i = 1; $i <= 5; $i++): ?>

                                        <span style="
                                            color: <?php echo ($i <= $rating) ? '#d4af37' : 'rgba(255,255,255,0.2)'; ?>;
                                            text-shadow: <?php echo ($i <= $rating) ? '0 0 8px rgba(212,175,55,0.4)' : 'none'; ?>;
                                            transition:0.3s;
                                        ">
                                            ★
                                        </span>

                                    <?php endfor; ?>

                                </div>

                                <span style="
                                    color:var(--gray);
                                    font-size:0.9rem;
                                ">
                                    <?php echo $rating; ?>/5
                                </span>

                            </div>

                            <p class="house-description">
                                <?php
                                    echo htmlspecialchars(
                                        substr($house['description'], 0, 120)
                                    );
                                ?>...
                            </p>

                            <!-- DETAILS -->
                            <div class="house-details">

                                <span style="color:var(--gold);">
                                    KES <?php echo number_format($house['price']); ?>
                                </span>

                                <span style="color:var(--gray);">
                                    <?php echo htmlspecialchars($house['location']); ?>
                                </span>

                            </div>

                            <!-- META -->
                            <div class="house-meta">

                                <span>
                                    <?php echo $house['bedrooms']; ?> Bedrooms
                                </span>

                                <span>
                                    <?php echo $house['bathrooms']; ?> Bathrooms
                                </span>

                            </div>

                            <!-- ACTIONS -->
                            <div class="house-actions">

                                <!-- FIXED EDIT BUTTON -->
                                <a
                                    href="edit_house.php?id=<?php echo $house['id']; ?>"
                                    class="action-btn edit-btn"
                                >
                                    ✏ Edit
                                </a>

                                <!-- DELETE -->
                                <a
                                    href="../../api/houses/delete_house.php?id=<?php echo $house['id']; ?>"
                                    onclick="return confirm('Delete this property?')"
                                    class="action-btn delete-btn"
                                >
                                    Delete
                                </a>

                            </div>

                        </div>

                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <div class="lux-card" style="
                    padding:40px;
                    text-align:center;
                    grid-column:1/-1;
                    border-radius:28px;
                ">

                    <h2 style="
                        color:var(--gold);
                        margin-bottom:15px;
                    ">
                        No Luxury Properties Yet
                    </h2>

                    <p style="
                        color:var(--gray);
                        margin-bottom:25px;
                        line-height:1.7;
                    ">
                        Start building your Empire portfolio now.
                    </p>

                    <a href="add_house.php"
                       class="lux-btn"
                       style="text-decoration:none;">
                       👑 Add First Property
                    </a>

                </div>

            <?php endif; ?>

        </div>

    </main>

</div>

<?php require_once '../../includes/footer.php'; ?>