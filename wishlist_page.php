<?php
require_once 'includes/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['cart_session'])) {
    $_SESSION['cart_session'] = session_id();
}
$sid = $_SESSION['cart_session'];
$page_title = 'REVIBE – Wishlist';
require_once 'includes/header.php';

$stmt = mysqli_prepare($conn, 
        "SELECT p.*, 
            (SELECT image_url 
                FROM product_images pi 
                WHERE pi.product_id = p.id AND pi.is_primary = 1 
                LIMIT 1) AS image
        FROM wishlist w
        JOIN products p ON w.product_id = p.id 
        WHERE w.session_id=? AND p.status = 'approved'
        ORDER BY w.created_at DESC"
    );
mysqli_stmt_bind_param($stmt, "s", $sid);
mysqli_stmt_execute($stmt);
$items = mysqli_stmt_get_result($stmt);
$count = mysqli_num_rows($items);
mysqli_stmt_close($stmt);
?>

<main class="products-page" style="min-height: calc(100vh - 64px); position: relative;">
    <p class="products-page__title">My Wishlist</p>

    <?php if ($count === 0): ?>
    <div style="position:absolute; top:50%; left:50%; transform:translate(-50%, -50%);
                text-align:center; color:#888;">
        <p style="font-size:18px; font-weight:600; margin-bottom:12px;">Your wishlist is empty.</p>
        <a href="products.php" style="color:var(--black); font-weight:700;">Browse products →</a>
    </div>

    <?php else: ?>
    <div class="products-grid" id="wishlist-grid">
        <?php while ($p = mysqli_fetch_assoc($items)):
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
                <button class="product-card__wishlist" title="Remove from wishlist"
        onclick="toggleWishlist(<?= $p['id'] ?>, this)">
    <svg class="wishlisted" viewBox="0 0 24 24" stroke-width="2"
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
    <?php endif; ?>
</main>

<?php require_once 'includes/footer.php'; ?>