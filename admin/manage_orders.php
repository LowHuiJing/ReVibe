<?php
require_once '../includes/db.php';
require_once 'includes/admin_auth.php';
requireAdminLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = (int)($_POST['order_id'] ?? 0);
    $status   = $_POST['status'] ?? '';
    $allowed  = ['pending','confirmed','cancelled','refunded','delivered','completed'];
    if ($order_id && in_array($status, $allowed)) {
        mysqli_query($conn, "UPDATE orders SET status='$status' WHERE id=$order_id");
    }
    header('Location: /revibe/admin/manage_orders.php'); exit;
}

$orders = mysqli_query($conn,
    "SELECT o.id, u.username, o.total_amount, o.status, o.created_at,
            COUNT(oi.id) AS items_count
     FROM orders o
     JOIN users u ON u.id = o.user_id
     LEFT JOIN order_items oi ON oi.order_id = o.id
     GROUP BY o.id
     ORDER BY o.created_at DESC");

$total_orders = $orders ? mysqli_num_rows($orders) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Manage Orders — REVIBE Admin</title>
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
        <li><a href="/revibe/admin/manage_orders.php"   class="sidebar-menu-link active"><span class="menu-icon">🛍️</span> Orders</a></li>
        <li><a href="/revibe/admin/manage_products.php" class="sidebar-menu-link"><span class="menu-icon">📦</span> Products</a></li>
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
            <h1>Manage Orders</h1>
            <p><?= $total_orders ?> total orders</p>
        </div>
        <div class="topbar-right">
            <div class="admin-chip">
                <div class="admin-chip-avatar"><?= strtoupper(substr($_SESSION['username'],0,1)) ?></div>
                <span class="admin-chip-name"><?= htmlspecialchars($_SESSION['username']) ?></span>
            </div>
            <a href="/revibe/admin/logout.php" class="logout-btn">Logout</a>
        </div>
    </div>

    <div class="table-card">
        <div class="table-header">
            <div class="table-title">All Orders</div>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Order</th>
                    <th>Customer</th>
                    <th>Items</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Change Status</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($orders && mysqli_num_rows($orders)): ?>
            <?php while ($o = mysqli_fetch_assoc($orders)): ?>
            <tr>
                <td><strong>#<?= $o['id'] ?></strong></td>
                <td><?= htmlspecialchars($o['username']) ?></td>
                <td><?= (int)$o['items_count'] ?> item<?= $o['items_count'] != 1 ? 's' : '' ?></td>
                <td><strong>RM <?= number_format($o['total_amount'], 2) ?></strong></td>
                <td><span class="badge <?= strtolower($o['status']) ?>"><?= ucfirst($o['status']) ?></span></td>
                <td><?= date('d M Y', strtotime($o['created_at'])) ?></td>
                <td>
                    <form method="POST">
                        <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                        <select name="status" class="status-select" onchange="this.form.submit()">
                            <option value="pending"   <?= $o['status']==='pending'    ? 'selected':'' ?>>Pending</option>
                            <option value="confirmed" <?= $o['status']==='confirmed'  ? 'selected':'' ?>>Confirmed</option>
                            <option value="delivered" <?= $o['status']==='delivered'  ? 'selected':'' ?>>Delivered</option>
                            <option value="completed" <?= $o['status']==='completed'  ? 'selected':'' ?>>Completed</option>
                            <option value="cancelled" <?= $o['status']==='cancelled'  ? 'selected':'' ?>>Cancelled</option>
                            <option value="refunded"  <?= $o['status']==='refunded'   ? 'selected':'' ?>>Refunded</option>
                        </select>
                    </form>
                </td>
            </tr>
            <?php endwhile; ?>
            <?php else: ?>
            <tr class="empty-row"><td colspan="7">No orders found</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</div>
</body>
</html>
