<?php
require_once '../includes/db.php';
require_once 'includes/admin_auth.php';
requireAdminLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = (int)($_POST['user_id'] ?? 0);
    $action  = $_POST['action'] ?? '';
    if ($action === 'suspend')  mysqli_query($conn, "UPDATE users SET is_active=0 WHERE id=$user_id");
    if ($action === 'activate') mysqli_query($conn, "UPDATE users SET is_active=1 WHERE id=$user_id");
    header('Location: /revibe/admin/manage_users.php'); exit;
}

$users       = mysqli_query($conn, "SELECT * FROM users WHERE role='user' ORDER BY created_at DESC");
$total_users = $users ? mysqli_num_rows($users) : 0;
$active      = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) v FROM users WHERE role='user' AND is_active=1"))['v'] ?? 0);
$suspended   = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) v FROM users WHERE role='user' AND is_active=0"))['v'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Manage Users — REVIBE Admin</title>
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
        <li><a href="/revibe/admin/manage_users.php"    class="sidebar-menu-link active"><span class="menu-icon">👥</span> Users</a></li>
        <li><a href="/revibe/admin/manage_orders.php"   class="sidebar-menu-link"><span class="menu-icon">🛍️</span> Orders</a></li>
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
            <h1>Manage Users</h1>
            <p><?= $total_users ?> registered users · <?= $active ?> active · <?= $suspended ?> suspended</p>
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
            <div class="stat-icon blue">👥</div>
            <div class="stat-body">
                <div class="stat-label">Total Users</div>
                <div class="stat-value"><?= $total_users ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green">✅</div>
            <div class="stat-body">
                <div class="stat-label">Active</div>
                <div class="stat-value"><?= $active ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon red">🚫</div>
            <div class="stat-body">
                <div class="stat-label">Suspended</div>
                <div class="stat-value"><?= $suspended ?></div>
            </div>
        </div>
    </div>

    <div class="table-card">
        <div class="table-header">
            <div class="table-title">All Users</div>
        </div>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($users && mysqli_num_rows($users)): ?>
            <?php while ($u = mysqli_fetch_assoc($users)): ?>
            <tr>
                <td style="color:var(--text-3);">#<?= $u['id'] ?></td>
                <td>
                    <div style="display:flex;align-items:center;gap:.6rem;">
                        <div style="width:32px;height:32px;border-radius:50%;background:#c8f04a;color:#111;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:.8rem;flex-shrink:0;">
                            <?= strtoupper(substr($u['username'],0,1)) ?>
                        </div>
                        <strong><?= htmlspecialchars($u['username']) ?></strong>
                    </div>
                </td>
                <td><?= htmlspecialchars($u['email']) ?></td>
                <td><span class="badge <?= $u['role'] === 'admin' ? 'confirmed' : 'pending' ?>"><?= ucfirst($u['role']) ?></span></td>
                <td><span class="badge <?= $u['is_active'] ? 'approved' : 'suspended' ?>"><?= $u['is_active'] ? 'Active' : 'Suspended' ?></span></td>
                <td><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                <td>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                        <?php if ($u['is_active']): ?>
                        <button type="submit" name="action" value="suspend"
                                class="btn btn-danger btn-sm"
                                onclick="return confirm('Suspend <?= htmlspecialchars($u['username']) ?>?')">
                            Suspend
                        </button>
                        <?php else: ?>
                        <button type="submit" name="action" value="activate"
                                class="btn btn-success btn-sm">
                            Activate
                        </button>
                        <?php endif; ?>
                    </form>
                </td>
            </tr>
            <?php endwhile; ?>
            <?php else: ?>
            <tr class="empty-row"><td colspan="7">No users found</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</div>
</body>
</html>
