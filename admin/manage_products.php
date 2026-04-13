<?php
require_once '../includes/db.php';
require_once 'includes/admin_auth.php';
requireAdminLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = (int)($_POST['product_id'] ?? 0);
    $action     = $_POST['action'] ?? '';
    if ($action === 'approve') mysqli_query($conn, "UPDATE products SET status='approved'  WHERE id=$product_id");
    if ($action === 'reject')  mysqli_query($conn, "UPDATE products SET status='rejected'  WHERE id=$product_id");
    if ($action === 'suspend') mysqli_query($conn, "UPDATE products SET status='suspended' WHERE id=$product_id");
    if ($action === 'unlist')  mysqli_query($conn, "UPDATE products SET status='unlisted'  WHERE id=$product_id");
    header('Location: /revibe/admin/manage_products.php'); exit;
}

$products = mysqli_query($conn,
    "SELECT p.*, c.name AS category_name, u.username AS seller_name,
            (SELECT image_url FROM product_images pi WHERE pi.product_id=p.id AND pi.is_primary=1 LIMIT 1) AS img
     FROM products p
     LEFT JOIN categories c ON c.id = p.category_id
     LEFT JOIN users u ON u.id = p.seller_id
     ORDER BY
         FIELD(p.status,'pending','approved','rejected','suspended','unlisted','sold_out'),
         p.created_at DESC");

$total    = $products ? mysqli_num_rows($products) : 0;
$pending  = (int)(mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) v FROM products WHERE status='pending'"))['v']  ?? 0);
$approved = (int)(mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) v FROM products WHERE status='approved'"))['v'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Manage Products — REVIBE Admin</title>
<link rel="stylesheet" href="css/styles.css">
</head>
<body>
<div class="admin-container">

<aside class="sidebar">
    <div class="sidebar-logo">
        <div class="sidebar-logo-text">RE<span>VIBE</span></div>
        <div class="sidebar-logo-badge">Admin</div>
    </div>
    <div class="sidebar-section-label">Main</div>
    <ul class="sidebar-menu">
        <li><a href="/revibe/admin/dashboard.php"       class="sidebar-menu-link"><span class="menu-icon">📈</span> Dashboard</a></li>
        <li><a href="/revibe/admin/manage_users.php"    class="sidebar-menu-link"><span class="menu-icon">👥</span> Users</a></li>
        <li><a href="/revibe/admin/manage_orders.php"   class="sidebar-menu-link"><span class="menu-icon">🛍️</span> Orders</a></li>
        <li><a href="/revibe/admin/manage_products.php" class="sidebar-menu-link active"><span class="menu-icon">📦</span> Products</a></li>
    </ul>
    <div class="sidebar-section-label">System</div>
    <ul class="sidebar-menu">
        <li><a href="/revibe/admin/system_settings.php" class="sidebar-menu-link"><span class="menu-icon">⚙️</span> Settings</a></li>
    </ul>
    <div class="sidebar-footer">
        <ul class="sidebar-menu">
            <li><a href="/revibe/admin/logout.php" class="sidebar-menu-link"><span class="menu-icon">🚪</span> Logout</a></li>
        </ul>
    </div>
</aside>

<div class="main-content">
    <div class="topbar">
        <div class="topbar-left">
            <h1>Manage Products</h1>
            <p><?= $total ?> products · <?= $pending ?> pending approval</p>
        </div>
        <div class="topbar-right">
            <div class="admin-chip">
                <div class="admin-chip-avatar"><?= strtoupper(substr($_SESSION['username'],0,1)) ?></div>
                <span class="admin-chip-name"><?= htmlspecialchars($_SESSION['username']) ?></span>
            </div>
            <a href="/revibe/admin/logout.php" class="logout-btn">Logout</a>
        </div>
    </div>

    <!-- Mini stats -->
    <div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:1.4rem;">
        <div class="stat-card">
            <div class="stat-icon blue">📦</div>
            <div class="stat-body"><div class="stat-label">Total Products</div><div class="stat-value"><?= $total ?></div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon yellow">⏳</div>
            <div class="stat-body">
                <div class="stat-label">Pending Review</div>
                <div class="stat-value"><?= $pending ?></div>
                <?php if ($pending > 0): ?>
                <div class="stat-sub" style="color:var(--yellow-text);font-weight:600;">Action required</div>
                <?php endif; ?>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green">✅</div>
            <div class="stat-body"><div class="stat-label">Approved & Live</div><div class="stat-value"><?= $approved ?></div></div>
        </div>
    </div>

    <div class="table-card">
        <div class="table-header">
            <div class="table-title">All Products</div>
            <?php if ($pending > 0): ?>
            <span style="font-size:.78rem;font-weight:700;background:var(--yellow-bg);color:var(--yellow-text);padding:.3rem .75rem;border-radius:999px;">
                ⚠️ <?= $pending ?> pending
            </span>
            <?php endif; ?>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Seller</th>
                    <th>Status</th>
                    <th>Listed</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($products && mysqli_num_rows($products)): ?>
            <?php while ($p = mysqli_fetch_assoc($products)): ?>
            <tr>
                <td>
                    <div style="display:flex;align-items:center;gap:.7rem;">
                        <?php if ($p['img']): ?>
                        <img src="/revibe/<?= htmlspecialchars($p['img']) ?>" alt=""
                             style="width:38px;height:38px;object-fit:cover;border-radius:8px;border:1px solid var(--border);flex-shrink:0;"
                             onerror="this.style.display='none'">
                        <?php else: ?>
                        <div style="width:38px;height:38px;background:var(--bg);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;">📦</div>
                        <?php endif; ?>
                        <div>
                            <div style="font-weight:600;color:var(--text-1);font-size:.85rem;"><?= htmlspecialchars($p['name']) ?></div>
                            <div style="font-size:.72rem;color:var(--text-3);">#<?= $p['id'] ?></div>
                        </div>
                    </div>
                </td>
                <td><?= htmlspecialchars($p['category_name'] ?? '—') ?></td>
                <td><strong>RM <?= number_format($p['price'] ?? 0, 2) ?></strong></td>
                <td>
                    <div style="display:flex;align-items:center;gap:5px;">
                        <div style="width:24px;height:24px;border-radius:50%;background:#c8f04a;color:#111;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:.7rem;flex-shrink:0;">
                            <?= strtoupper(substr($p['seller_name'] ?? '?', 0, 1)) ?>
                        </div>
                        <?= htmlspecialchars($p['seller_name'] ?? 'Unknown') ?>
                    </div>
                </td>
                <td><span class="badge <?= strtolower($p['status']) ?>"><?= ucfirst($p['status']) ?></span></td>
                <td><?= date('d M Y', strtotime($p['created_at'])) ?></td>
                <td>
                    <form method="POST" style="display:flex;gap:5px;flex-wrap:wrap;">
                        <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                        <?php if ($p['status'] === 'pending'): ?>
                            <button type="submit" name="action" value="approve" class="btn btn-success btn-sm">✓ Approve</button>
                            <button type="submit" name="action" value="reject"  class="btn btn-danger  btn-sm">✕ Reject</button>
                        <?php elseif ($p['status'] === 'approved'): ?>
                            <button type="submit" name="action" value="suspend" class="btn btn-danger  btn-sm">Suspend</button>
                        <?php elseif (in_array($p['status'], ['suspended','rejected'])): ?>
                            <button type="submit" name="action" value="approve" class="btn btn-success btn-sm">Reinstate</button>
                        <?php else: ?>
                            <span style="color:var(--text-3);font-size:.78rem;">—</span>
                        <?php endif; ?>
                    </form>
                </td>
            </tr>
            <?php endwhile; ?>
            <?php else: ?>
            <tr class="empty-row"><td colspan="7">No products found</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</div>
</body>
</html>
