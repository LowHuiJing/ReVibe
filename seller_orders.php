<?php
require_once 'includes/db.php';
require_once 'includes/session.php';
require_once 'includes/auth.php';

checkSessionTimeout();

if (!isset($_SESSION['user_id'])) {
    header('Location: /revibe/signin.php');
    exit;
}

$seller_id = (int)$_SESSION['user_id'];
$page_title = 'Seller Orders — ReVibe';
$flash = null;

// ─── Handle mark as packed ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_packed'])) {
    $order_id = (int)($_POST['order_id'] ?? 0);
    if ($order_id) {
        // Verify seller owns at least one item in this order
        $chk = mysqli_prepare($conn,
            "SELECT oi.id FROM order_items oi
             JOIN products p ON p.id = oi.product_id
             WHERE oi.order_id = ? AND p.seller_id = ? LIMIT 1"
        );
        mysqli_stmt_bind_param($chk, 'ii', $order_id, $seller_id);
        mysqli_stmt_execute($chk);
        $ok = mysqli_fetch_assoc(mysqli_stmt_get_result($chk));
        mysqli_stmt_close($chk);

        if ($ok) {
            $del = mysqli_prepare($conn, "SELECT id, status FROM deliveries WHERE order_id = ? LIMIT 1");
            mysqli_stmt_bind_param($del, 'i', $order_id);
            mysqli_stmt_execute($del);
            $del_row = mysqli_fetch_assoc(mysqli_stmt_get_result($del));
            mysqli_stmt_close($del);

            if ($del_row && $del_row['status'] === 'pending') {
                mysqli_query($conn, "UPDATE deliveries SET status='packed' WHERE id=" . (int)$del_row['id']);
                mysqli_query($conn, "UPDATE orders SET status='confirmed' WHERE id=$order_id");
                $flash = ['type' => 'success', 'msg' => "Order #$order_id marked as packed."];
            } else {
                $flash = ['type' => 'error', 'msg' => 'Order cannot be packed at its current status.'];
            }
        }
    }
    $_SESSION['so_flash'] = $flash;
    header('Location: seller_orders.php?status=' . urlencode($_POST['_status'] ?? ''));
    exit;
}

$flash = $_SESSION['so_flash'] ?? null;
unset($_SESSION['so_flash']);

// ─── Filters ──────────────────────────────────────────────────────────────────
$allowed = ['pending', 'packed', 'shipped', 'out_for_delivery', 'delivered', 'cancelled'];
$status_filter = in_array($_GET['status'] ?? '', $allowed) ? $_GET['status'] : '';

// ─── Fetch orders that contain this seller's products ─────────────────────────
$where_delivery = $status_filter
    ? "AND COALESCE(d.status,'pending') = '" . mysqli_real_escape_string($conn, $status_filter) . "'"
    : '';

$orders_result = mysqli_query($conn,
    "SELECT o.id, o.status AS order_status, o.total_amount, o.created_at,
            o.full_name, o.phone,
            d.status AS delivery_status,
            COUNT(DISTINCT oi.id) AS item_count
     FROM orders o
     JOIN order_items oi ON oi.order_id = o.id
     JOIN products p ON p.id = oi.product_id AND p.seller_id = $seller_id
     LEFT JOIN deliveries d ON d.order_id = o.id
     WHERE o.status NOT IN ('cancelled')
     $where_delivery
     GROUP BY o.id
     ORDER BY o.created_at DESC"
);
$orders = [];
while ($row = mysqli_fetch_assoc($orders_result)) $orders[] = $row;

// ─── Count by delivery status ─────────────────────────────────────────────────
$counts_result = mysqli_query($conn,
    "SELECT COALESCE(d.status,'pending') AS ds, COUNT(DISTINCT o.id) AS c
     FROM orders o
     JOIN order_items oi ON oi.order_id = o.id
     JOIN products p ON p.id = oi.product_id AND p.seller_id = $seller_id
     LEFT JOIN deliveries d ON d.order_id = o.id
     WHERE o.status NOT IN ('cancelled')
     GROUP BY ds"
);
$counts = ['all' => 0, 'pending' => 0, 'packed' => 0, 'shipped' => 0, 'out_for_delivery' => 0, 'delivered' => 0];
while ($r = mysqli_fetch_assoc($counts_result)) {
    $k = $r['ds'];
    if (isset($counts[$k])) $counts[$k] = (int)$r['c'];
    $counts['all'] += (int)$r['c'];
}

$status_labels = [
    'pending'          => 'Pending',
    'packed'           => 'Packed',
    'shipped'          => 'Shipped',
    'out_for_delivery' => 'Out for Delivery',
    'delivered'        => 'Delivered',
];
$status_colors = [
    'pending'          => '#f59e0b',
    'packed'           => '#3b82f6',
    'shipped'          => '#8b5cf6',
    'out_for_delivery' => '#f97316',
    'delivered'        => '#22c55e',
    'cancelled'        => '#ef4444',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Orders — ReVibe</title>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
    <style>
        *{box-sizing:border-box;margin:0;padding:0;}
        body{font-family:'Montserrat',sans-serif;background:#f4f6f8;color:#1a1a1a;font-size:.9rem;}

        .topbar{background:#1a1a1a;color:#fff;padding:.9rem 2rem;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;}
        .topbar-left{display:flex;align-items:center;gap:12px;}
        .logo{font-size:1.1rem;font-weight:900;color:#c8f04a;letter-spacing:1px;}
        .sub{font-size:.78rem;color:#94a3b8;margin-top:2px;}
        .back-btn{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;border:1.5px solid #444;color:#ccc;text-decoration:none;line-height:1;}
        .back-btn:hover{border-color:#c8f04a;color:#c8f04a;}
        .topbar-actions{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
        .btn-teal{display:inline-flex;align-items:center;gap:8px;padding:.55rem 1rem;border-radius:10px;background:#fff;color:#1a1a1a;border:1.5px solid #ddd;text-decoration:none;font-weight:900;font-size:.82rem;cursor:pointer;white-space:nowrap;}
        .btn-teal:hover{border-color:#c8f04a;}
        .logout-btn{background:none;border:1.5px solid #444;color:#ccc;padding:.4rem .9rem;border-radius:8px;cursor:pointer;font-size:.78rem;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;}
        .logout-btn:hover{border-color:#c8f04a;color:#c8f04a;}

        .wrap{max-width:1100px;margin:2rem auto;padding:0 1.5rem;}

        .pm-toast{position:fixed;top:18px;right:18px;z-index:1000;display:flex;align-items:center;gap:10px;padding:.75rem 1rem;border-radius:12px;background:#fff;box-shadow:0 10px 30px rgba(0,0,0,.12);border:1px solid #eee;min-width:280px;}
        .pm-toast-success{border-left:4px solid #22c55e;}
        .pm-toast-error{border-left:4px solid #ef4444;}
        .pm-toast-close{margin-left:auto;background:none;border:none;cursor:pointer;color:#888;font-size:16px;line-height:1;}

        .pm-tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:1.4rem;}
        .pm-tab{padding:.45rem 1rem;border-radius:999px;border:1.5px solid #ddd;background:#fff;cursor:pointer;font-size:.78rem;font-weight:900;color:#555;text-decoration:none;display:inline-flex;align-items:center;gap:8px;}
        .pm-tab.active{background:#1a1a1a;color:#c8f04a;border-color:#1a1a1a;}
        .pm-tab-count{display:inline-flex;align-items:center;justify-content:center;min-width:24px;padding:0 .45rem;height:20px;border-radius:999px;background:#eef2ff;color:#1a1a1a;font-size:.72rem;font-weight:900;}
        .pm-tab.active .pm-tab-count{background:#c8f04a;color:#1a1a1a;}

        .orders-table{width:100%;border-collapse:collapse;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.06);}
        .orders-table thead th{padding:.85rem 1rem;background:#1a1a1a;color:#c8f04a;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;text-align:left;}
        .orders-table tbody tr{border-bottom:1px solid #f5f5f5;}
        .orders-table tbody tr:last-child{border-bottom:none;}
        .orders-table tbody tr:hover{background:#fafafa;}
        .orders-table tbody td{padding:.85rem 1rem;font-size:.86rem;vertical-align:middle;}

        .status-badge{display:inline-block;padding:.25rem .65rem;border-radius:20px;font-size:.72rem;font-weight:700;}

        .pack-btn{display:inline-flex;align-items:center;gap:6px;padding:.4rem .85rem;background:#1a1a1a;color:#c8f04a;border:none;border-radius:10px;font-size:.78rem;font-weight:900;cursor:pointer;font-family:inherit;}
        .pack-btn:hover{background:#333;}
        .view-btn{display:inline-flex;align-items:center;gap:5px;padding:.4rem .85rem;background:#fff;color:#1a1a1a;border:1.5px solid #ddd;border-radius:10px;font-size:.78rem;font-weight:900;text-decoration:none;}
        .view-btn:hover{border-color:#c8f04a;}

        .pm-empty{background:#fff;border-radius:16px;padding:3rem 1.5rem;box-shadow:0 2px 10px rgba(0,0,0,.06);text-align:center;color:#94a3b8;border:1px solid #eef2f7;}
        .pm-empty-icon{font-size:42px;display:block;margin-bottom:10px;color:#1a1a1a;}

        @media(max-width:768px){
            .topbar{padding:.9rem 1rem;}.wrap{padding:0 1rem;}
            .orders-table thead th:nth-child(3),.orders-table tbody td:nth-child(3){display:none;}
        }
    </style>
</head>
<body>

<div class="topbar">
    <div class="topbar-left">
        <a href="/revibe/product_management.php" class="back-btn" aria-label="Back">
            <span class="material-symbols-outlined">arrow_back</span>
        </a>
        <div>
            <div class="logo">Seller Dashboard — ReVibe</div>
            <div class="sub">Orders</div>
        </div>
    </div>
    <div class="topbar-actions">
        <a href="seller_returns.php" class="btn-teal">
            <span class="material-symbols-outlined">replay</span>
            Returns
        </a>
        <a href="/revibe/seller_messaging.php" class="btn-teal" id="msgBtn" style="position:relative;">
            <span class="material-symbols-outlined">chat</span>
            Messages
            <span id="msgBadge" style="display:none;position:absolute;top:-6px;right:-6px;background:#ef4444;color:#fff;font-size:11px;font-weight:900;width:18px;height:18px;border-radius:50%;align-items:center;justify-content:center;">0</span>
        </a>
        <a href="/revibe/logout.php" class="logout-btn">Sign Out</a>
    </div>
</div>

<?php if ($flash): ?>
<div id="pm-toast" class="pm-toast pm-toast-<?= $flash['type'] ?>">
    <span class="material-symbols-outlined"><?= $flash['type'] === 'success' ? 'check_circle' : 'error' ?></span>
    <span><?= htmlspecialchars($flash['msg']) ?></span>
    <button class="pm-toast-close" onclick="this.parentElement.remove()">✕</button>
</div>
<script>setTimeout(() => { const t = document.getElementById('pm-toast'); if (t) t.remove(); }, 4000);</script>
<?php endif; ?>

<div class="wrap">

    <!-- Filter tabs -->
    <div class="pm-tabs">
        <a href="seller_orders.php" class="pm-tab <?= !$status_filter ? 'active' : '' ?>">
            All <span class="pm-tab-count"><?= $counts['all'] ?></span>
        </a>
        <?php foreach ($status_labels as $k => $l): ?>
        <a href="seller_orders.php?status=<?= $k ?>" class="pm-tab <?= $status_filter === $k ? 'active' : '' ?>">
            <?= $l ?> <span class="pm-tab-count"><?= $counts[$k] ?></span>
        </a>
        <?php endforeach; ?>
    </div>

    <?php if (empty($orders)): ?>
    <div class="pm-empty">
        <span class="pm-empty-icon material-symbols-outlined">inbox</span>
        <p style="font-weight:700;font-size:.95rem;color:#1a1a1a;margin-bottom:4px;">No orders found</p>
        <p style="font-size:.82rem;">Orders for your products will appear here.</p>
    </div>
    <?php else: ?>
    <table class="orders-table">
        <thead>
            <tr>
                <th>Order #</th>
                <th>Customer</th>
                <th>Items</th>
                <th>Total</th>
                <th>Status</th>
                <th>Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($orders as $o):
            $ds    = $o['delivery_status'] ?? 'pending';
            $color = $status_colors[$ds] ?? '#888';
            $label = $status_labels[$ds]  ?? ucfirst($ds);
        ?>
        <tr>
            <td><strong>#<?= $o['id'] ?></strong></td>
            <td>
                <strong><?= htmlspecialchars($o['full_name']) ?></strong>
                <br><span style="font-size:.72rem;color:#888;"><?= htmlspecialchars($o['phone']) ?></span>
            </td>
            <td><?= $o['item_count'] ?> item(s)</td>
            <td><strong>RM <?= number_format($o['total_amount'], 2) ?></strong></td>
            <td>
                <span class="status-badge" style="background:<?= $color ?>22;color:<?= $color ?>;">
                    <?= $label ?>
                </span>
            </td>
            <td style="color:#888;font-size:.82rem;"><?= date('d M Y', strtotime($o['created_at'])) ?></td>
            <td style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
                <?php if ($ds === 'pending'): ?>
                <form method="POST" onsubmit="return confirm('Mark order #<?= $o['id'] ?> as packed?');" style="margin:0;">
                    <input type="hidden" name="mark_packed" value="1">
                    <input type="hidden" name="order_id"   value="<?= $o['id'] ?>">
                    <input type="hidden" name="_status"    value="<?= htmlspecialchars($status_filter) ?>">
                    <button type="submit" class="pack-btn">
                        <span class="material-symbols-outlined" style="font-size:15px;">inventory_2</span>
                        Pack
                    </button>
                </form>
                <?php endif; ?>
                <a href="/revibe/order_status.php?order_id=<?= $o['id'] ?>" class="view-btn">
                    <span class="material-symbols-outlined" style="font-size:15px;">open_in_new</span>
                    View
                </a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

</div>

<script>
(function() {
    function checkUnread() {
        fetch('/revibe/api/messages_api.php?action=get_unread_count')
            .then(r => r.json())
            .then(data => {
                const badge = document.getElementById('msgBadge');
                if (data.unread > 0) { badge.textContent = data.unread; badge.style.display = 'flex'; }
                else badge.style.display = 'none';
            });
    }
    checkUnread();
    setInterval(checkUnread, 5000);
})();
</script>

</body>
</html>
