<?php
require_once 'includes/db.php';

// Flash message for "added to cart"
if (session_status() === PHP_SESSION_NONE) session_start();
$flash = '';
if (!empty($_SESSION['flash'])) { $flash = $_SESSION['flash']; unset($_SESSION['flash']); }

// Filter
$type = $_GET['type'] ?? '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : '';
$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

// Load categories that have approved products for the CURRENT type (for the dropdown)
$categories = [];
$catSql =
    "SELECT DISTINCT c.id, c.name
     FROM categories c
     JOIN products p ON p.category_id = c.id
     WHERE p.status = 'approved'";
if ($type === 'new') {
    $catSql .= " AND p.`condition` = 'new'";
} elseif ($type === 'preloved') {
    $catSql .= " AND p.`condition` != 'new'";
}
$catSql .= " ORDER BY c.name ASC";

$catRes = mysqli_query($conn, $catSql);
if ($catRes) {
    while ($r = mysqli_fetch_assoc($catRes)) $categories[] = $r;
}

// If a category was selected but doesn't exist for this type, reset it.
if ($category_id > 0) {
    $isValid = false;
    foreach ($categories as $c) {
        if ((int)$c['id'] === $category_id) { $isValid = true; break; }
    }
    if (!$isValid) $category_id = 0;
}

$whereParts = ["p.status = 'approved'"];
$params = [];
$types  = '';

$sql =
    "SELECT p.*,
        (SELECT image_url
            FROM product_images pi
            WHERE pi.product_id = p.id AND pi.is_primary = 1
            LIMIT 1) AS image
     FROM products p";

// 🔥 type filter
if ($type === 'new') {
    $whereParts[] = "p.`condition` = 'new'";
} elseif ($type === 'preloved') {
    $whereParts[] = "p.`condition` != 'new'";
}

// 🔥 category filter
if ($category_id > 0) {
    $whereParts[] = "p.category_id = ?";
    $params[] = $category_id;
    $types   .= 'i';
}

$sql .= " WHERE " . implode(" AND ", $whereParts);

// 🔥 sort
switch ($sort) {
    case 'price_asc':
        $sql .= " ORDER BY p.price ASC";
        break;
    case 'price_desc':
        $sql .= " ORDER BY p.price DESC";
        break;
    default:
        $sql .= " ORDER BY p.created_at DESC";
}

$stmt = mysqli_prepare($conn, $sql);
if ($stmt === false) {
    die("<p style='padding:40px;font-family:sans-serif;color:red;'>Query error: " . mysqli_error($conn) . "</p>");
}
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
mysqli_stmt_close($stmt);
if (!$result) {
    die("<p style='padding:40px;font-family:sans-serif;color:red;'>Query error: " . mysqli_error($conn) . "</p>");
}

$category_name = '';
if ($category_id > 0) {
    foreach ($categories as $c) {
        if ((int)$c['id'] === $category_id) { $category_name = $c['name']; break; }
    }
}
$collection_label = $category_name ?: ($type ?: 'Collection');
$page_title = 'REVIBE – ' . $collection_label;
require_once 'includes/header.php';
?>

<main class="products-page">
    <!-- Filter Bar -->
    <div style="display:flex; gap:12px; flex-wrap:wrap; margin-bottom:36px; align-items:center;">

        <?php $is_all = ($type === '' && $sort === '' && $category_id === 0); ?>

        <a href="products.php"
           style="padding:8px 20px; border-radius:30px; font-size:13px; font-weight:600;
                  border:1.5px solid <?= $is_all ? '#111' : '#ddd' ?>;
                  background:<?= $is_all ? '#111' : '#fff' ?>;
                  color:<?= $is_all ? '#fff' : '#111' ?>;
                  text-decoration:none;">
            All
        </a>

        <a href="products.php?<?= http_build_query(array_merge($_GET, ['type' => 'new', 'category_id' => 0, 'page' => null])) ?>"
           style="padding:8px 20px; border-radius:30px; font-size:13px; font-weight:600;
                  border:1.5px solid <?= ($_GET['type'] ?? '') === 'new' ? '#111' : '#ddd' ?>;
                  background:<?= ($_GET['type'] ?? '') === 'new' ? '#111' : '#fff' ?>;
                  color:<?= ($_GET['type'] ?? '') === 'new' ? '#fff' : '#111' ?>;
                  text-decoration:none;">
            New
        </a>

        <a href="products.php?<?= http_build_query(array_merge($_GET, ['type' => 'preloved', 'category_id' => 0, 'page' => null])) ?>"
           style="padding:8px 20px; border-radius:30px; font-size:13px; font-weight:600;
                  border:1.5px solid <?= ($_GET['type'] ?? '') === 'preloved' ? '#111' : '#ddd' ?>;
                  background:<?= ($_GET['type'] ?? '') === 'preloved' ? '#111' : '#fff' ?>;
                  color:<?= ($_GET['type'] ?? '') === 'preloved' ? '#fff' : '#111' ?>;
                  text-decoration:none;">
            Pre-Loved
        </a>

        <!-- Category dropdown -->
        <form method="GET" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            <?php if ($type !== ''): ?><input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>"><?php endif; ?>
            <?php if ($sort !== ''): ?><input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>"><?php endif; ?>
            <select name="category_id"
                    style="padding:8px 18px;border-radius:30px;font-size:13px;font-weight:600;border:1.5px solid <?= $category_id>0 ? '#111' : '#ddd' ?>;background:#fff;color:#111;outline:none;cursor:pointer;min-width:210px;"
                    onchange="this.form.submit()">
                <option value="0">All Categories</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= $category_id === (int)$c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <noscript>
                <button type="submit" class="btn-outline-dark">Filter</button>
            </noscript>
        </form>

        <div style="width:1px; height:28px; background:#ddd; margin:0 4px;"></div>

        <a href="products.php?<?= http_build_query(array_merge($_GET, ['sort'=>'price_asc'])) ?>"
           style="padding:8px 20px; border-radius:30px; font-size:13px; font-weight:600;
                  border:1.5px solid <?= ($_GET['sort']??'')==='price_asc' ? '#111' : '#ddd' ?>;
                  background:<?= ($_GET['sort']??'')==='price_asc' ? '#111' : '#fff' ?>;
                  color:<?= ($_GET['sort']??'')==='price_asc' ? '#fff' : '#111' ?>;
                  text-decoration:none;">
            Price: Low to High
        </a>

        <a href="products.php?<?= http_build_query(array_merge($_GET, ['sort'=>'price_desc'])) ?>"
           style="padding:8px 20px; border-radius:30px; font-size:13px; font-weight:600;
                  border:1.5px solid <?= ($_GET['sort']??'')==='price_desc' ? '#111' : '#ddd' ?>;
                  background:<?= ($_GET['sort']??'')==='price_desc' ? '#111' : '#fff' ?>;
                  color:<?= ($_GET['sort']??'')==='price_desc' ? '#fff' : '#111' ?>;
                  text-decoration:none;">
            Price: High to Low
        </a>
    </div>

    <p class="products-page__title"><?= htmlspecialchars($collection_label) ?></p>

    <div class="products-grid">
        <?php while ($p = mysqli_fetch_assoc($result)):
            $condition = strtolower($p['condition']);
            $cat_class = $condition === 'new' ? 'new-item' : 'preloved';
            $item_label = $condition === 'new' ? 'New Item' : 'PreLoved';
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
                <p class="product-card__category <?= $cat_class ?>"><?= $item_label ?></p>
                <p class="product-card__desc"><?= htmlspecialchars($p['description']) ?></p>
                <p class="product-card__price">RM <?= number_format($p['price'], 2) ?></p>

                <form method="POST" action="cart.php">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                    <input type="hidden" name="redirect" value="products">

                    <button type="submit" class="btn-outline-dark">Add to Cart</button>
                </form>
            </div>
        </div>
        <?php endwhile; ?>
    </div>

    <?php if (mysqli_num_rows($result) === 0): ?>
        <div style="text-align:center; padding:80px 0; color:#888;">
            <p style="font-size:18px; font-weight:600;">No products found.</p>
            <a href="products.php" style="color:var(--black); font-weight:700; margin-top:12px; display:inline-block;">
                View all →
            </a>
        </div>
    <?php endif; ?>
</main>

<?php if ($flash): ?>
<div class="flash"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
