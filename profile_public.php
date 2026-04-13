<?php
require_once 'includes/db.php';
require_once 'includes/session.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: /revibe/products.php');
    exit;
}

$profile_id = (int)$_GET['id'];
$viewer_id  = $_SESSION['user_id'] ?? null;

// Fetch profile owner
$result = mysqli_query($conn,
    "SELECT u.id, u.username, u.created_at,
            p.first_name, p.last_name, p.bio,
            p.avatar_url, p.city, p.country, p.website,
            s.privacy_level
     FROM users u
     LEFT JOIN user_profiles p ON u.id = p.user_id
     LEFT JOIN user_settings s ON u.id = s.user_id
     WHERE u.id = $profile_id AND u.is_active = 1"
);
if (mysqli_num_rows($result) === 0) {
    header('Location: /revibe/products.php');
    exit;
}
$profile = mysqli_fetch_assoc($result);

// Privacy check
if (($profile['privacy_level'] ?? 'public') === 'private' && $viewer_id !== $profile_id) {
    header('Location: /revibe/index.php');
    exit;
}

$display_name = trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? ''));
if (empty($display_name)) $display_name = $profile['username'];
$location  = trim(($profile['city'] ?? '') . ($profile['country'] ? ', ' . $profile['country'] : ''));
$join_date = date('F Y', strtotime($profile['created_at']));

// Products (use revibe schema: name, product_images table, status=approved)
$products_result = mysqli_query($conn,
    "SELECT p.id, p.name, p.description, p.price, p.`condition`,
            (SELECT image_url FROM product_images pi
             WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1) AS image
     FROM products p
     WHERE p.seller_id = $profile_id AND p.status = 'approved'
     ORDER BY p.created_at DESC LIMIT 10"
);

// Ratings (from reviews table in revibe)
$ratings_result = mysqli_query($conn,
    "SELECT r.rating as score, r.review_text as comment, r.created_at, u.username as reviewer_name
     FROM reviews r
     JOIN users u ON r.user_id = u.id
     WHERE r.product_id IN (SELECT id FROM products WHERE seller_id = $profile_id)
     ORDER BY r.created_at DESC"
);
$avg_data      = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) total, ROUND(AVG(rating),1) avg FROM reviews
     WHERE product_id IN (SELECT id FROM products WHERE seller_id=$profile_id)"
));
$product_count = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) c FROM products WHERE seller_id=$profile_id AND status='approved'"
))['c'];

function renderStars($score) {
    $s = '';
    for ($i = 1; $i <= 5; $i++) $s .= $i <= $score ? '★' : '☆';
    return $s;
}

$page_title = htmlspecialchars($display_name) . '\'s Profile — REVIBE';
require_once 'includes/header.php';
?>

<style>
body.dark { background:#0f172a; }

/* Profile card */
body.dark .pub-card          { background:#1e293b !important; box-shadow:0 2px 14px rgba(0,0,0,.4) !important; }
body.dark .pub-card-border   { border-color:#334155 !important; }
body.dark .pub-name          { color:#e2e8f0 !important; }
body.dark .pub-sub           { color:#94a3b8 !important; }
body.dark .pub-stat-val      { color:#e2e8f0 !important; }
body.dark .pub-stat-lbl      { color:#64748b !important; }
body.dark .pub-bio           { color:#94a3b8 !important; border-color:#334155 !important; }

/* Section cards */
body.dark .pub-section       { background:#1e293b !important; box-shadow:0 2px 12px rgba(0,0,0,.3) !important; }
body.dark .pub-section-head  { border-color:#334155 !important; }
body.dark .pub-section-head h2 { color:#e2e8f0 !important; }
body.dark .pub-section-head span { color:#64748b !important; }

/* Product items */
body.dark .pub-product-wrap  { border-color:#334155 !important; }
body.dark .pub-product-img   { background:#0f172a !important; }
body.dark .pub-product-body  { background:#1e293b !important; }
body.dark .pub-product-name  { color:#e2e8f0 !important; }
body.dark .pub-product-price { color:#c8f04a !important; }

/* Reviews */
body.dark .pub-review-row    { border-color:#334155 !important; }
body.dark .pub-review-name   { color:#e2e8f0 !important; }
body.dark .pub-review-date   { color:#64748b !important; }
body.dark .pub-review-text   { color:#94a3b8 !important; }
body.dark .pub-empty         { color:#475569 !important; }
</style>

<main style="max-width:1000px;margin:40px auto;padding:0 1.5rem;">

    <!-- Profile Card -->
    <div class="pub-card" style="background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 2px 14px rgba(0,0,0,.07);margin-bottom:1.6rem;">

        <!-- Banner -->
        <div style="height:120px;background:linear-gradient(135deg,#1a1a1a 0%,#333 100%);position:relative;">
            <div style="position:absolute;bottom:-36px;left:2rem;
                        width:72px;height:72px;border-radius:50%;
                        background:#c8f04a;border:4px solid #fff;
                        display:flex;align-items:center;justify-content:center;
                        font-size:1.8rem;font-weight:900;color:#1a1a1a;overflow:hidden;">
                <?php if (!empty($profile['avatar_url'])): ?>
                    <img src="<?= htmlspecialchars($profile['avatar_url']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
                <?php else: ?>
                    <?= strtoupper(substr($profile['username'], 0, 1)) ?>
                <?php endif; ?>
            </div>
        </div>

        <div style="padding:2.8rem 2rem 1.6rem;display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
            <div>
                <h1 class="pub-name" style="font-size:1.3rem;font-weight:900;margin:0 0 3px;"><?= htmlspecialchars($display_name) ?></h1>
                <p class="pub-sub" style="color:#888;font-size:.85rem;margin:0 0 4px;">@<?= htmlspecialchars($profile['username']) ?></p>
                <?php if ($location): ?><p class="pub-sub" style="color:#888;font-size:.82rem;margin:0;">📍 <?= htmlspecialchars($location) ?></p><?php endif; ?>
                <p class="pub-sub" style="color:#aaa;font-size:.78rem;margin:4px 0 0;">Member since <?= $join_date ?></p>
            </div>
            <?php if ($viewer_id === $profile_id): ?>
            <a href="/revibe/account.php"
               style="padding:.55rem 1.2rem;background:#1a1a1a;color:#c8f04a;border-radius:10px;font-size:.82rem;font-weight:700;text-decoration:none;">
                Edit Profile
            </a>
            <?php endif; ?>
        </div>

        <!-- Stats bar -->
        <div class="pub-card-border" style="display:flex;border-top:1px solid #f0f0f0;">
            <?php foreach ([['Products',$product_count],['Reviews',$avg_data['total']],['Avg Rating',$avg_data['avg']??'—']] as [$l,$v]): ?>
            <div class="pub-card-border" style="flex:1;padding:1rem;text-align:center;border-right:1px solid #f0f0f0;">
                <div class="pub-stat-val" style="font-size:1.2rem;font-weight:900;color:#1a1a1a;"><?= $v ?></div>
                <div class="pub-stat-lbl" style="font-size:.72rem;color:#888;text-transform:uppercase;letter-spacing:.5px;"><?= $l ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($profile['bio'])): ?>
        <div class="pub-bio pub-card-border" style="padding:1rem 2rem;border-top:1px solid #f0f0f0;font-size:.88rem;color:#555;line-height:1.6;">
            <?= nl2br(htmlspecialchars($profile['bio'])) ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Products -->
    <div class="pub-section" style="background:#fff;border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,.06);margin-bottom:1.6rem;overflow:hidden;">
        <div class="pub-section-head" style="padding:1.1rem 1.4rem;border-bottom:2px solid #c8f04a;display:flex;align-items:center;justify-content:space-between;">
            <h2 style="font-size:1rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;margin:0;">Products Listed</h2>
            <span style="font-size:.8rem;color:#888;"><?= $product_count ?> listing(s)</span>
        </div>
        <div style="padding:1.4rem;">
            <?php if (mysqli_num_rows($products_result) > 0): ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:1rem;">
                <?php while ($p = mysqli_fetch_assoc($products_result)):
                    $cond = strtolower($p['condition'] ?? '');
                    $cLabel = $cond === 'new' ? 'New' : 'PreLoved';
                    $cColor = $cond === 'new' ? '#4ade80' : '#c084fc';
                ?>
                <a href="/revibe/product_detail.php?id=<?= $p['id'] ?>" style="text-decoration:none;">
                    <div class="pub-product-wrap" style="border:1.5px solid #eee;border-radius:12px;overflow:hidden;">
                        <div class="pub-product-img" style="height:130px;background:#f5f5f5;overflow:hidden;">
                            <?php if (!empty($p['image'])): ?>
                            <img src="<?= htmlspecialchars($p['image']) ?>" alt=""
                                 style="width:100%;height:100%;object-fit:cover;"
                                 onerror="this.parentElement.style.background='#eee'">
                            <?php else: ?>
                            <div class="pub-empty" style="height:100%;display:flex;align-items:center;justify-content:center;color:#bbb;font-size:.8rem;">No Image</div>
                            <?php endif; ?>
                        </div>
                        <div class="pub-product-body" style="padding:.7rem .8rem;">
                            <p class="pub-product-name" style="font-weight:700;font-size:.84rem;margin:0 0 3px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#1a1a1a;"><?= htmlspecialchars($p['name']) ?></p>
                            <p class="pub-product-price" style="color:#1a1a1a;font-weight:700;font-size:.88rem;margin:0 0 4px;">RM <?= number_format($p['price'],2) ?></p>
                            <span style="font-size:.68rem;font-weight:700;color:<?= $cColor ?>;background:<?= $cColor ?>22;padding:.15rem .5rem;border-radius:20px;"><?= $cLabel ?></span>
                        </div>
                    </div>
                </a>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
            <div class="pub-empty" style="text-align:center;color:#aaa;font-size:.9rem;padding:2rem 0;">No products listed yet.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Ratings -->
    <div class="pub-section" style="background:#fff;border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,.06);overflow:hidden;">
        <div class="pub-section-head" style="padding:1.1rem 1.4rem;border-bottom:2px solid #c8f04a;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem;">
            <h2 style="font-size:1rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;margin:0;">Ratings & Reviews</h2>
            <?php if ($avg_data['avg']): ?>
            <div style="font-size:.85rem;color:#888;">
                <span style="color:#f59e0b;letter-spacing:1px;"><?= renderStars(round($avg_data['avg'])) ?></span>
                <?= $avg_data['avg'] ?> / 5 · <?= $avg_data['total'] ?> review(s)
            </div>
            <?php endif; ?>
        </div>
        <div style="padding:1.4rem;">
            <?php if (mysqli_num_rows($ratings_result) > 0): ?>
            <?php while ($r = mysqli_fetch_assoc($ratings_result)): ?>
            <div class="pub-review-row" style="padding:.9rem 0;border-bottom:1px solid #f5f5f5;">
                <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:.3rem;">
                    <strong class="pub-review-name" style="font-size:.88rem;"><?= htmlspecialchars($r['reviewer_name']) ?></strong>
                    <span style="color:#f59e0b;font-size:.82rem;"><?= renderStars($r['score']) ?></span>
                    <span class="pub-review-date" style="color:#aaa;font-size:.75rem;"><?= date('d M Y', strtotime($r['created_at'])) ?></span>
                </div>
                <?php if (!empty($r['comment'])): ?>
                <p class="pub-review-text" style="font-size:.84rem;color:#555;margin:0;"><?= htmlspecialchars($r['comment']) ?></p>
                <?php endif; ?>
            </div>
            <?php endwhile; ?>
            <?php else: ?>
            <div class="pub-empty" style="text-align:center;color:#aaa;font-size:.9rem;padding:2rem 0;">No reviews yet.</div>
            <?php endif; ?>
        </div>
    </div>

</main>
<?php require_once 'includes/footer.php'; ?>
