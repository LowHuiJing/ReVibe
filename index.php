<?php
require_once 'includes/db.php';

if (session_status() === PHP_SESSION_NONE) session_start(); 

$page_title = 'REVIBE – Sustainable Shopping Starts Here';
require_once 'includes/header.php';
?>


<!-- HERO SECTION -->
<section class="hero">
    <div class="hero__slideshow">
        <img src="images/hero.jpg"  alt="Slide 1" class="hero__slide active">
        <img src="images/hero2.jpg" alt="Slide 2" class="hero__slide">
        <img src="images/hero3.jpg" alt="Slide 3" class="hero__slide">

        <div class="hero__dots">
            <span class="hero__dot active" data-index="0"></span>
            <span class="hero__dot" data-index="1"></span>
            <span class="hero__dot" data-index="2"></span>
        </div>
    </div>

    <div class="hero__content">
        <h1 class="hero__title">
            <span class="line-green">SUSTAINABLE</span>
            <span class="line-green">SHOPPING</span>
            <span class="line-white">STARTS</span>
            <span class="line-white">HERE</span>
        </h1>
        <p class="hero__subtitle">
            Find <strong>Great</strong> Deals on
            <span class="preloved"><strong>PreLoved</strong></span> &amp;
            <span class="new-item"><strong>New</strong></span> Items
        </p>
        <a href="products.php" class="btn-outline">View Collection</a>
    </div>
</section>

<!-- FEATURED PRODUCTS -->
<section style="padding: 64px 56px;">
    <p style="font-size:12px; font-weight:700; letter-spacing:3px; text-transform:uppercase; color:#888; margin-bottom:32px;">Featured Items</p>
    <div class="products-grid">
        <?php
            $result = mysqli_query($conn,
            "SELECT p.*,
                (SELECT image_url
                    FROM product_images pi
                    WHERE pi.product_id = p.id AND pi.is_primary = 1
                    LIMIT 1) AS image
                FROM products p
                WHERE p.status = 'approved'
                ORDER BY p.created_at DESC
                LIMIT 3
            ");
            if (!$result) die("Query error: " . mysqli_error($conn));
            while ($p = mysqli_fetch_assoc($result)):
                    $condition = strtolower($p['condition']);
                    $cat_class = $condition === 'new' ? 'new-item' : 'preloved';
                    $cat_label = $condition === 'new' ? 'New' : 'PreLoved';
        ?>
        <div class="product-card">
            <div class="product-card__img-wrap">
                <a href="product_detail.php?id=<?= $p['id'] ?>">
                    <img src="<?= htmlspecialchars($p['image']) ?>"
                         alt="<?= htmlspecialchars($p['name']) ?>"
                         onerror="this.src='images/placeholder.jpg'">
                </a>
                <button class="product-card__wishlist" title="Save to wishlist"
                        onclick="toggleWishlist(<?= $p['id'] ?>, this)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="#333" stroke-width="2"
                         style="width:16px;height:16px;">
                        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                    </svg>
                </button>
            </div>

            <div>
                <p class="product-card__name"><?= htmlspecialchars($p['name']) ?> -</p>
                <p class="product-card__category <?= $cat_class ?>"><?= $cat_label ?></p>
                <p class="product-card__desc"><?= htmlspecialchars($p['description']) ?></p>
                <p class="product-card__price">RM <?= number_format($p['price'], 2) ?></p>

                <form method="POST" action="/revibe/cart.php">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                    <input type="hidden" name="redirect" value="index">

                    <button type="submit" class="btn-outline-dark">Add to Cart</button>
                </form>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>