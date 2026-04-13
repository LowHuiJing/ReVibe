<?php
require_once 'includes/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = mysqli_prepare($conn, 
    "SELECT p.*, u.username AS seller_name,
        (SELECT image_url 
            FROM product_images pi 
            WHERE pi.product_id = p.id AND pi.is_primary = 1 
            LIMIT 1) AS image
    FROM products p
    JOIN users u ON p.seller_id = u.id
    WHERE p.id = ?"
    );
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$p = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$p) {
    header("Location: products.php");
    exit;
}

$is_own_product = isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === (int)$p['seller_id'];

// Calculate live seller rating from reviews
$seller_rating_data = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT ROUND(AVG(r.rating), 1) AS avg_rating, COUNT(*) AS total
     FROM reviews r
     WHERE r.product_id IN (SELECT id FROM products WHERE seller_id = " . (int)$p['seller_id'] . ")"
));
$seller_avg_rating   = (float)($seller_rating_data['avg_rating'] ?? 0);
$seller_review_count = (int)($seller_rating_data['total'] ?? 0);

$condition = strtolower($p['condition']);
$cat_class = $condition === 'new' ? 'new-item' : 'preloved';
$cat_color = $condition === 'new' ? '#4ade80' : '#c084fc';
$cat_label = $condition === 'new' ? 'New' : 'PreLoved';
$sizes = !empty($p['sizes']) ? array_map('trim', explode(',', $p['sizes'])) : [];

$page_title = 'REVIBE – ' . $p['name'];
require_once 'includes/header.php';
?>

<link rel="stylesheet" href="/revibe/css/seller.css">
<main class="detail-page">

    <!-- LEFT: Image -->
    <div class="detail-img-wrap">
        <img src="<?= htmlspecialchars($p['image']) ?>"
             alt="<?= htmlspecialchars($p['name']) ?>"
             onerror="this.src='images/placeholder.jpg'">
        <button class="detail-img-wrap__wishlist" id="wishlist-btn"
                data-product-id="<?= $p['id'] ?>"
                title="Save to wishlist"
                onclick="toggleWishlist(<?= $p['id'] ?>)">
            <svg id="wishlist-icon" viewBox="0 0 24 24" fill="none" stroke="#333" stroke-width="2">
                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
            </svg>
        </button>
    </div>

    <!-- RIGHT: Info -->
    <div class="detail-info">
        <h1 class="detail-info__name">
            <?= htmlspecialchars($p['name']) ?> -<br>
            <span style="color:<?= $cat_color ?>;"><?= $cat_label ?></span>
        </h1>

        <p class="detail-info__desc"><?= nl2br(htmlspecialchars($p['description'])) ?></p>

        <p class="detail-info__price">RM <?= number_format($p['price'], 2) ?></p>

        <?php if ($is_own_product): ?>
        <div style="padding:.8rem 1rem;background:#fffbeb;border:1.5px solid #f59e0b;border-radius:10px;font-size:.88rem;color:#92400e;font-weight:600;text-align:center;">
            This is your listing — you cannot purchase your own product.
        </div>
        <?php else: ?>
        <form method="POST" action="cart.php" id="detail-form">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
            <input type="hidden" name="redirect" value="detail">
            <button type="submit" class="btn-cart">Add to Cart</button>
        </form>
        <?php endif; ?>

        <!-- Seller Info -->
        <div style="margin-top: 32px; padding-top: 24px; border-top: 1px solid var(--border);">
            <p style="font-size: 12px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: var(--gray-text); margin-bottom: 14px;">Seller</p>

            <div style="display: flex; align-items: center; gap: 14px;">
                <div style="width: 48px; height: 48px; border-radius: 50%; background: #f0f0f0; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#888" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                </div>

                <div>
                    <p style="font-size: 15px; font-weight: 700; margin-bottom: 4px;">@<?= htmlspecialchars($p['seller_name']) ?></p>
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <div style="display: flex; gap: 2px;">
                            <?php
                            for ($i = 1; $i <= 5; $i++):
                                if ($seller_avg_rating > 0 && $i <= floor($seller_avg_rating)):
                            ?>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="#f59e0b" stroke="#f59e0b" stroke-width="1.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                            <?php elseif ($seller_avg_rating > 0 && $i - $seller_avg_rating < 1): ?>
                                <svg width="14" height="14" viewBox="0 0 24 24" stroke="#f59e0b" stroke-width="1.5"><defs><linearGradient id="half"><stop offset="50%" stop-color="#f59e0b"/><stop offset="50%" stop-color="none"/></linearGradient></defs><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" fill="url(#half)"/></svg>
                            <?php else: ?>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="1.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                            <?php endif; endfor; ?>
                        </div>
                        <p style="font-size: 13px; color: var(--gray-text); font-weight: 500;">
                            <?= $seller_avg_rating > 0 ? $seller_avg_rating . ' / 5.0 · ' . $seller_review_count . ' review' . ($seller_review_count !== 1 ? 's' : '') : 'No reviews yet' ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chat with Seller -->
        <?php if (!$is_own_product): ?>
        <a href="/revibe/messaging.php?product_id=<?= $p['id'] ?>&seller_id=<?= $p['seller_id'] ?>"
           style="display:flex; align-items:center; justify-content:center; gap:10px;
                  width:100%; padding:16px; border:2px solid var(--green);
                  color:var(--green); background:transparent; border-radius:40px;
                  font-family:var(--font-body); font-size:16px; font-weight:700;
                  text-decoration:none; margin-top:16px; margin-bottom:12px;
                  transition:background 0.2s, color 0.2s;"
           onmouseover="this.style.background='var(--green)';this.style.color='#111';"
           onmouseout="this.style.background='transparent';this.style.color='var(--green)';">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
            </svg>
            Chat with Seller
        </a>
        <?php endif; ?>

        <a href="/revibe/reviews.php?product_id=<?= $p['id'] ?>"
   style="display:flex; align-items:center; justify-content:center; gap:10px;
          width:100%; padding:16px; border:2px solid #f59e0b;
          color:#f59e0b; background:transparent; border-radius:40px;
          font-family:var(--font-body); font-size:16px; font-weight:700;
          text-decoration:none; margin-bottom:12px;
          transition:background 0.2s, color 0.2s;"
   onmouseover="this.style.background='#f59e0b';this.style.color='#111';"
   onmouseout="this.style.background='transparent';this.style.color='#f59e0b';">
    ⭐ Reviews & Ratings
</a>

        <a href="products.php" class="back-link">← Back to Collection</a>
    </div>

</main>

<?php require_once 'includes/footer.php'; ?>