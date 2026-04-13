<?php
require_once '../includes/db.php';
require_once 'includes/admin_auth.php';

// ── Stats ──────────────────────────────────────────────────────────────
$total_revenue    = (float)(mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COALESCE(SUM(total_amount),0) v FROM orders WHERE status NOT IN ('cancelled','refunded')"))['v'] ?? 0);

$total_orders     = (int)(mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) v FROM orders"))['v'] ?? 0);

$total_users      = (int)(mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) v FROM users WHERE role='user'"))['v'] ?? 0);

$pending_products = (int)(mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) v FROM products WHERE status='pending'"))['v'] ?? 0);

// Month-over-month revenue
$rev_this  = (float)(mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COALESCE(SUM(total_amount),0) v FROM orders
     WHERE status NOT IN ('cancelled','refunded')
       AND created_at >= DATE_FORMAT(NOW(),'%Y-%m-01')"))['v'] ?? 0);
$rev_last  = (float)(mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COALESCE(SUM(total_amount),0) v FROM orders
     WHERE status NOT IN ('cancelled','refunded')
       AND created_at >= DATE_FORMAT(NOW() - INTERVAL 1 MONTH,'%Y-%m-01')
       AND created_at <  DATE_FORMAT(NOW(),'%Y-%m-01')"))['v'] ?? 0);

// ── Monthly revenue for chart (last 12 months) ─────────────────────────
$monthly = [];
for ($i = 11; $i >= 0; $i--) {
    $row = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT DATE_FORMAT(DATE_SUB(NOW(), INTERVAL $i MONTH),'%b') lbl,
                COALESCE(SUM(total_amount),0) rev
         FROM orders
         WHERE status NOT IN ('cancelled','refunded')
           AND DATE_FORMAT(created_at,'%Y-%m') = DATE_FORMAT(DATE_SUB(NOW(), INTERVAL $i MONTH),'%Y-%m')"
    ));
    $monthly[] = $row;
}

// ── Recent orders ──────────────────────────────────────────────────────
$recent_orders = mysqli_query($conn,
    "SELECT o.id, u.username, o.total_amount, o.status, o.created_at
     FROM orders o
     JOIN users u ON o.user_id = u.id
     ORDER BY o.created_at DESC
     LIMIT 7");

// ── Top products ───────────────────────────────────────────────────────
$top_products = mysqli_query($conn,
    "SELECT p.name, SUM(oi.quantity) AS total_sold,
            SUM(oi.unit_price * oi.quantity) AS revenue
     FROM products p
     JOIN order_items oi ON p.id = oi.product_id
     GROUP BY p.id
     ORDER BY total_sold DESC
     LIMIT 5");

// ── Category sales ─────────────────────────────────────────────────────
$cat_rows = [];
$cat_q = mysqli_query($conn,
    "SELECT c.name, COUNT(oi.id) AS cnt, COALESCE(SUM(oi.unit_price*oi.quantity),0) AS rev
     FROM order_items oi
     JOIN products p ON p.id = oi.product_id
     JOIN categories c ON c.id = p.category_id
     GROUP BY c.id ORDER BY cnt DESC LIMIT 6");
while ($r = mysqli_fetch_assoc($cat_q)) $cat_rows[] = $r;
$cat_total = array_sum(array_column($cat_rows, 'cnt')) ?: 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Dashboard — REVIBE Admin</title>
<link rel="stylesheet" href="css/styles.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
<div class="admin-container">

<!-- SIDEBAR -->
<aside class="sidebar">
    <div class="sidebar-logo">
        <div class="sidebar-logo-text">RE<span>VIBE</span></div>
        <div class="sidebar-logo-badge">Admin</div>
    </div>
    <div class="sidebar-section-label">Main</div>
    <ul class="sidebar-menu">
        <li><a href="/revibe/admin/dashboard.php"     class="sidebar-menu-link active"><span class="menu-icon">📈</span> Dashboard</a></li>
        <li><a href="/revibe/admin/manage_users.php"  class="sidebar-menu-link"><span class="menu-icon">👥</span> Users</a></li>
        <li><a href="/revibe/admin/manage_orders.php" class="sidebar-menu-link"><span class="menu-icon">🛍️</span> Orders</a></li>
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

<!-- MAIN -->
<div class="main-content">

    <!-- Top bar -->
    <div class="topbar">
        <div class="topbar-left">
            <h1>Dashboard</h1>
            <p>Welcome back — here's what's happening today.</p>
        </div>
        <div class="topbar-right">
            <div class="admin-chip">
                <div class="admin-chip-avatar"><?= strtoupper(substr($_SESSION['username'],0,1)) ?></div>
                <span class="admin-chip-name"><?= htmlspecialchars($_SESSION['username']) ?></span>
            </div>
            <a href="/revibe/admin/logout.php" class="logout-btn">Logout</a>
        </div>
    </div>

    <!-- Stat cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon green">💰</div>
            <div class="stat-body">
                <div class="stat-label">Total Sales</div>
                <div class="stat-value">RM <?= number_format($total_revenue, 2) ?></div>
                <div class="stat-sub">This month: RM <?= number_format($rev_this, 2) ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon blue">🛍️</div>
            <div class="stat-body">
                <div class="stat-label">Total Orders</div>
                <div class="stat-value"><?= number_format($total_orders) ?></div>
                <div class="stat-sub">All time</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon purple">👥</div>
            <div class="stat-body">
                <div class="stat-label">Registered Users</div>
                <div class="stat-value"><?= number_format($total_users) ?></div>
                <div class="stat-sub">Active accounts</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon yellow">⏳</div>
            <div class="stat-body">
                <div class="stat-label">Pending Products</div>
                <div class="stat-value"><?= $pending_products ?></div>
                <div class="stat-sub"><a href="/revibe/admin/manage_products.php" style="color:inherit;font-weight:700;">Review now →</a></div>
            </div>
        </div>
    </div>

    <!-- Charts -->
    <div class="chart-grid">
        <div class="chart-card" style="grid-column: span 1;">
            <div class="chart-card-title">Monthly Sales</div>
            <div class="chart-card-sub">Actual sales from orders (last 12 months)</div>
            <canvas id="revenueChart" height="120"></canvas>
        </div>
        <div class="chart-card">
            <div class="chart-card-title">Sales by Category</div>
            <div class="chart-card-sub">Order item distribution</div>
            <div style="display:flex;align-items:center;gap:1.5rem;flex-wrap:wrap;">
                <canvas id="categoryChart" style="max-width:130px;max-height:130px;"></canvas>
                <div id="catLegend" style="font-size:.8rem;flex:1;min-width:120px;"></div>
            </div>
        </div>
    </div>

    <!-- Recent orders -->
    <div class="table-card" style="margin-bottom:1.4rem;">
        <div class="table-header">
            <div>
                <div class="table-title">Recent Orders</div>
                <div style="font-size:.75rem;color:var(--text-3);margin-top:2px;">Latest 7 orders placed</div>
            </div>
            <a href="/revibe/admin/manage_orders.php" class="table-view-all">View all →</a>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Order</th>
                    <th>Customer</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($recent_orders && mysqli_num_rows($recent_orders)): ?>
            <?php while ($o = mysqli_fetch_assoc($recent_orders)): ?>
            <tr>
                <td><strong>#<?= $o['id'] ?></strong></td>
                <td><?= htmlspecialchars($o['username']) ?></td>
                <td><strong>RM <?= number_format($o['total_amount'], 2) ?></strong></td>
                <td><span class="badge <?= strtolower($o['status']) ?>"><?= ucfirst($o['status']) ?></span></td>
                <td><?= date('d M Y', strtotime($o['created_at'])) ?></td>
            </tr>
            <?php endwhile; ?>
            <?php else: ?>
            <tr class="empty-row"><td colspan="5">No orders yet</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Top products -->
    <div class="table-card">
        <div class="table-header">
            <div class="table-title">Top Products by Sales</div>
            <a href="/revibe/admin/manage_products.php" class="table-view-all">View all →</a>
        </div>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Product Name</th>
                    <th>Units Sold</th>
                    <th>Sales</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($top_products && mysqli_num_rows($top_products)): ?>
            <?php $rank = 1; while ($p = mysqli_fetch_assoc($top_products)): ?>
            <tr>
                <td><strong><?= $rank++ ?></strong></td>
                <td><?= htmlspecialchars($p['name']) ?></td>
                <td><?= (int)$p['total_sold'] ?> sold</td>
                <td><strong>RM <?= number_format($p['revenue'] ?? 0, 2) ?></strong></td>
            </tr>
            <?php endwhile; ?>
            <?php else: ?>
            <tr class="empty-row"><td colspan="4">No sales data yet</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

</div><!-- /main-content -->
</div><!-- /admin-container -->

<script>
// Revenue chart — real data
const revLabels = <?= json_encode(array_column($monthly, 'lbl')) ?>;
const revData   = <?= json_encode(array_map(fn($r) => round((float)$r['rev'], 2), $monthly)) ?>;

new Chart(document.getElementById('revenueChart'), {
    type: 'line',
    data: {
        labels: revLabels,
        datasets: [{
            label: 'Revenue (RM)',
            data: revData,
            borderColor: '#c8f04a',
            backgroundColor: 'rgba(200,240,74,.08)',
            borderWidth: 2.5,
            fill: true,
            tension: .4,
            pointRadius: 4,
            pointBackgroundColor: '#c8f04a',
            pointBorderColor: '#fff',
            pointBorderWidth: 2
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { callback: v => 'RM' + (v >= 1000 ? (v/1000).toFixed(1)+'k' : v) },
                grid: { color: '#f1f5f9' }
            },
            x: { grid: { display: false } }
        }
    }
});

// Category chart — real data
const catLabels = <?= json_encode(array_column($cat_rows, 'name')) ?>;
const catData   = <?= json_encode(array_column($cat_rows, 'cnt')) ?>;
const catColors = ['#c8f04a','#3b82f6','#f59e0b','#ef4444','#8b5cf6','#22c55e'];

new Chart(document.getElementById('categoryChart'), {
    type: 'doughnut',
    data: {
        labels: catLabels,
        datasets: [{ data: catData, backgroundColor: catColors, borderWidth: 2, borderColor: '#fff' }]
    },
    options: {
        responsive: true,
        cutout: '65%',
        plugins: { legend: { display: false } }
    }
});

// Legend
const leg = document.getElementById('catLegend');
catLabels.forEach((lbl, i) => {
    const pct = catData[i] ? Math.round(catData[i] / <?= $cat_total ?> * 100) : 0;
    leg.innerHTML += `<div style="display:flex;align-items:center;gap:6px;margin-bottom:7px;">
        <span style="width:10px;height:10px;border-radius:3px;background:${catColors[i]};flex-shrink:0;display:inline-block;"></span>
        <span style="flex:1;color:#475569;">${lbl}</span>
        <span style="font-weight:700;color:#0f172a;">${pct}%</span>
    </div>`;
});
</script>
</body>
</html>
