<?php
require_once 'includes/db.php';
require_once 'includes/session.php';
require_once 'includes/auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();

checkSessionTimeout();

if (!isset($_SESSION['user_id'])) {
  header('Location: /revibe/signin.php');
  exit;
}

$seller_id = (int)$_SESSION['user_id'];


// ── Handle status update ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_return'])) {
    $rid        = (int)$_POST['return_id'];
    $new_status = mysqli_real_escape_string($conn, $_POST['new_status']);
    $notes      = mysqli_real_escape_string($conn, trim($_POST['admin_notes'] ?? ''));
    $valid = ['pending','reviewing','approved','rejected','completed'];
    if (in_array($new_status, $valid)) {
        $rid   = (int)$_POST['return_id'];
        $notes = trim($_POST['admin_notes'] ?? '');

        $new_status = $_POST['new_status'] ?? '';
        $valid = ['approved','rejected']; // seller only needs approve/reject
        if (!in_array($new_status, $valid, true)) {
        header('Location: seller_returns.php'); exit;
        }

        $stmt = mysqli_prepare($conn,
        "UPDATE return_requests rr
        JOIN order_items oi ON oi.id = rr.order_item_id
        JOIN products p ON p.id = oi.product_id
        SET rr.status = ?, rr.admin_notes = ?
        WHERE rr.id = ? AND p.seller_id = ?"
        );
        mysqli_stmt_bind_param($stmt, 'ssii', $new_status, $notes, $rid, $seller_id);
        mysqli_stmt_execute($stmt);
        $ok = (mysqli_stmt_affected_rows($stmt) === 1);
        mysqli_stmt_close($stmt);

        header('Location: seller_returns.php');
        exit;


        // Notify buyer and seller
        require_once 'includes/notify.php';
        $rrow = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT o.user_id AS buyer_id, p.seller_id, oi.product_name
             FROM return_requests rr
             JOIN orders o ON o.id = rr.order_id
             LEFT JOIN order_items oi ON oi.id = rr.order_item_id
             LEFT JOIN products p ON p.id = oi.product_id
             WHERE rr.id = $rid LIMIT 1"
        ));
        if ($rrow) {
            $label = ucfirst($new_status);
            $pname = $rrow['product_name'] ?? 'your item';
            // Notify buyer
            if ($rrow['buyer_id']) {
                sendNotification($conn, $rrow['buyer_id'], 'order',
                    'Return Request ' . $label,
                    'Your return request for "' . $pname . '" has been ' . strtolower($label) . '.' . ($notes ? ' Note: ' . $notes : ''),
                    '/revibe/order_status.php'
                );
            }
            // Notify seller too if approved/rejected
            if ($rrow['seller_id'] && in_array($new_status, ['approved','rejected','completed'])) {
                sendNotification($conn, $rrow['seller_id'], 'order',
                    'Return Request ' . $label . ' by Admin',
                    'Admin has ' . strtolower($label) . ' the return request for "' . $pname . '".',
                    '/revibe/product_management.php'
                );
            }
        }
    }
    header('Location: seller_returns.php'); exit;
}

// ── Filters ───────────────────────────────────────────────────
$filter  = $_GET['status'] ?? 'all';
$search  = trim($_GET['q'] ?? '');
$where   = [];
$where[] = "p.seller_id = " . (int)$seller_id;
if ($filter !== 'all') $where[] = "rr.status='" . mysqli_real_escape_string($conn, $filter) . "'";
if ($search !== '')    $where[] = "(buyer.username LIKE '%" . mysqli_real_escape_string($conn,$search) . "%'
                                   OR seller.username LIKE '%" . mysqli_real_escape_string($conn,$search) . "%'
                                   OR p.name LIKE '%" . mysqli_real_escape_string($conn,$search) . "%'
                                   OR rr.id LIKE '%" . mysqli_real_escape_string($conn,$search) . "%')";
$sql_where = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// ── Load return requests ──────────────────────────────────────
// Join: return_requests → orders (buyer) → order_items → products → seller
$returns = mysqli_query($conn,
    "SELECT rr.id, rr.reason, rr.details, rr.refund_method, rr.status, rr.admin_notes,
            rr.created_at, rr.updated_at,
            o.id AS order_id, o.full_name AS buyer_fullname, o.email AS buyer_email,
            buyer.id AS buyer_id, buyer.username AS buyer_username,
            oi.product_name, oi.quantity, oi.unit_price,
            p.id AS product_id, p.name AS product_name_current,
            seller.id AS seller_id, seller.username AS seller_username,
            seller_p.first_name AS seller_firstname, seller_p.last_name AS seller_lastname
     FROM return_requests rr
     JOIN orders o ON o.id = rr.order_id
     LEFT JOIN users buyer ON buyer.id = o.user_id
     LEFT JOIN user_profiles buyer_p ON buyer_p.user_id = buyer.id
     LEFT JOIN order_items oi ON oi.id = rr.order_item_id
     LEFT JOIN products p ON p.id = oi.product_id
     LEFT JOIN users seller ON seller.id = p.seller_id
     LEFT JOIN user_profiles seller_p ON seller_p.user_id = seller.id
     $sql_where
     ORDER BY rr.created_at DESC"
);

// ── Stats ─────────────────────────────────────────────────────
$counts = [];
foreach (['all','pending','reviewing','approved','rejected','completed'] as $s) {
    $w = $s === 'all' ? '' : "WHERE status='$s'";
    $counts[$s] = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM return_requests $w"))['c'];
}

function statusColor($s) {
    return ['pending'=>'#f59e0b','reviewing'=>'#3b82f6','approved'=>'#22c55e',
            'rejected'=>'#ef4444','completed'=>'#6366f1'][$s] ?? '#888';
}
function statusBadge($s) {
    $c = statusColor($s);
    return "<span style='display:inline-block;padding:.2rem .65rem;border-radius:20px;background:{$c}22;color:{$c};font-size:.72rem;font-weight:700;'>".ucfirst($s)."</span>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
<title>Seller Returns Management — ReVibe</title>
<style>
    *{box-sizing:border-box;margin:0;padding:0;}
    body{font-family:'Montserrat',sans-serif;background:#f4f6f8;color:#1a1a1a;font-size:.9rem;}

    .pm-header{background:#1a1a1a;color:#fff;padding:.9rem 1.2rem;border-radius:18px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:1.2rem;}
    .pm-header-left{display:flex;align-items:center;gap:10px;}
    .pm-icon{color:#c8f04a;}
    .pm-header h1{font-size:1.05rem;font-weight:900;margin:0;color:#c8f04a;letter-spacing:.5px;}
    .pm-subtitle{font-size:.78rem;color:#94a3b8;margin-top:2px;}

    .pm-back-btn{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;border:1.5px solid #444;color:#ccc;text-decoration:none;}
    .pm-back-btn:hover{border-color:#c8f04a;color:#c8f04a;}
    .btn-teal{display:inline-flex;align-items:center;gap:8px;padding:.55rem 1rem;border-radius:10px;background:#fff;color:#1a1a1a;border:1.5px solid #ddd;text-decoration:none;font-weight:900;font-size:.82rem;cursor:pointer;white-space:nowrap;}
    .btn-teal:hover{border-color:#c8f04a;}
    .wrap{max-width:1200px;margin:2rem auto;padding:0 1.5rem;}
    .stats-row{display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:1.5rem;}
    .stat-card{background:#fff;border-radius:14px;padding:1rem 1.4rem;flex:1;min-width:120px;box-shadow:0 2px 8px rgba(0,0,0,.05);text-align:center;}
    .stat-card .num{font-size:1.6rem;font-weight:900;}
    .stat-card .lbl{font-size:.68rem;color:#888;text-transform:uppercase;letter-spacing:.5px;margin-top:2px;}
    .toolbar{display:flex;gap:.8rem;flex-wrap:wrap;align-items:center;margin-bottom:1.2rem;}
    .tab-btn{padding:.45rem 1rem;border-radius:999px;border:1.5px solid #ddd;background:#fff;cursor:pointer;font-size:.78rem;font-weight:900;color:#555;text-decoration:none;display:inline-flex;align-items:center;gap:8px;white-space:nowrap;}
    .tab-btn.active{background:#1a1a1a;color:#c8f04a;border-color:#1a1a1a;}
    .tab-count{display:inline-flex;align-items:center;justify-content:center;min-width:24px;padding:0 .45rem;height:20px;border-radius:999px;background:#eef2ff;color:#1a1a1a;font-size:.72rem;font-weight:900;}
    .tab-btn.active .tab-count{background:#c8f04a;color:#1a1a1a;}
    .search-box{padding:.5rem .9rem;border:1.5px solid #ddd;border-radius:20px;font-size:.82rem;outline:none;min-width:200px;}
    .search-box:focus{border-color:#c8f04a;}
    table{width:100%;border-collapse:collapse;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.06);}
    thead tr{background:#1a1a1a;color:#c8f04a;}
    th{padding:.8rem 1rem;text-align:left;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;white-space:nowrap;}
    td{padding:.85rem 1rem;border-bottom:1px solid #f5f5f5;vertical-align:top;font-size:.83rem;}
    tr:last-child td{border-bottom:none;}
    tr:hover td{background:#fafafa;}
    .user-pill{display:inline-flex;align-items:center;gap:.4rem;padding:.2rem .6rem;border-radius:20px;font-size:.75rem;font-weight:700;}
    .buyer-pill{background:#dbeafe;color:#1d4ed8;}
    .seller-pill{background:#fef3c7;color:#92400e;}
    .action-btn{padding:.35rem .8rem;border:none;border-radius:8px;font-size:.75rem;font-weight:700;cursor:pointer;}
    .review-btn{background:#c8f04a;color:#1a1a1a;}
    .empty{text-align:center;padding:3rem;color:#aaa;}

    /* Modal */
    .overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:100;align-items:center;justify-content:center;}
    .overlay.open{display:flex;}
    .modal{background:#fff;border-radius:20px;width:100%;max-width:540px;max-height:90vh;overflow-y:auto;box-shadow:0 8px 40px rgba(0,0,0,.2);}
    .modal-hdr{padding:1.2rem 1.5rem;border-bottom:2px solid #c8f04a;display:flex;align-items:center;justify-content:space-between;}
    .modal-hdr h2{font-size:1rem;font-weight:800;}
    .close-btn{background:none;border:none;font-size:1.4rem;cursor:pointer;color:#888;line-height:1;}
    .modal-body{padding:1.4rem 1.5rem;}
    .info-row{display:flex;gap:.5rem;margin-bottom:.55rem;align-items:flex-start;}
    .info-lbl{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:#888;min-width:110px;padding-top:.15rem;}
    .info-val{font-size:.85rem;color:#1a1a1a;}
    .divider{border:none;border-top:1.5px solid #f0f0f0;margin:1rem 0;}
    select,textarea{width:100%;padding:.6rem .85rem;border:1.5px solid #ddd;border-radius:8px;font-size:.87rem;font-family:inherit;outline:none;}
    select:focus,textarea:focus{border-color:#c8f04a;}
    .save-btn{padding:.7rem 1.6rem;background:#1a1a1a;color:#c8f04a;border:none;border-radius:10px;font-weight:700;font-size:.88rem;cursor:pointer;margin-top:1rem;}
    @media(max-width:700px){.stats-row{flex-wrap:wrap;}.stat-card{min-width:calc(50% - .5rem);}}
</style>
</head>
<body>

<div class="wrap">
    <header class="pm-header">
        <div class="pm-header-left">
            <a href="/revibe/product_management.php" class="pm-back-btn" aria-label="Back">
                <span class="material-symbols-outlined">arrow_back</span>
            </a>
            <span class="material-symbols-outlined pm-icon">storefront</span>
            <div>
                <h1>Return & Refund Management</h1>
                <div class="pm-subtitle">Return & Refund Requested</div>
            </div>
        </div>
        <a href="/revibe/dashboard.php" class="btn-teal">
            <span class="material-symbols-outlined">dashboard</span>
            Back to Dashboard
        </a>
    </header>

    <!-- Stats -->
    <div class="stats-row">
        <?php 
            $counts = [];
            foreach (['all','pending','reviewing','approved','rejected','completed'] as $s) {
            $statusWhere = $s === 'all' ? "" : "AND rr.status='" . mysqli_real_escape_string($conn, $s) . "'";
            $row = mysqli_fetch_assoc(mysqli_query($conn,
                "SELECT COUNT(*) c
                FROM return_requests rr
                JOIN order_items oi ON oi.id = rr.order_item_id
                JOIN products p ON p.id = oi.product_id
                WHERE p.seller_id = $seller_id
                $statusWhere"
            ));
            $counts[$s] = $row['c'] ?? 0;
            }
        ?>
    </div>

    <!-- Toolbar -->
    <form method="GET" class="toolbar">
        <?php foreach (['all'=>'All','pending'=>'Pending','reviewing'=>'Reviewing','approved'=>'Approved','rejected'=>'Rejected','completed'=>'Completed'] as $k=>$l): ?>
        <a href="?status=<?= $k ?><?= $search?'&q='.urlencode($search):'' ?>"
           class="tab-btn <?= $filter===$k?'active':'' ?>"><?= $l ?> <span class="tab-count"><?= (int)$counts[$k] ?></span></a>
        <?php endforeach; ?>
        <input type="hidden" name="status" value="<?= htmlspecialchars($filter) ?>">
        <input class="search-box" type="text" name="q" placeholder="Search buyer, product…" value="<?= htmlspecialchars($search) ?>">
        <button type="submit" style="padding:.5rem 1rem;background:#1a1a1a;color:#c8f04a;border:none;border-radius:20px;cursor:pointer;font-size:.78rem;font-weight:700;">Search</button>
    </form>

    <!-- Table -->
    <?php if (mysqli_num_rows($returns) === 0): ?>
    <div class="empty">No return requests found.</div>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Product</th>
                <th>Buyer</th>
                <th>Seller</th>
                <th>Reason</th>
                <th>Refund Method</th>
                <th>Status</th>
                <th>Date</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($r = mysqli_fetch_assoc($returns)):
            $buyer_name  = htmlspecialchars($r['buyer_username'] ?? $r['buyer_fullname'] ?? 'Guest');
            $seller_name = htmlspecialchars($r['seller_username'] ?? '—');
            $product     = htmlspecialchars($r['product_name'] ?? $r['product_name_current'] ?? '—');
            $reason      = ucwords(str_replace('_',' ',$r['reason']));
            $refund      = ucwords(str_replace('_',' ',$r['refund_method']));
            $notes_esc   = htmlspecialchars($r['admin_notes'] ?? '');
            $details_esc = htmlspecialchars($r['details'] ?? '');
        ?>
        <tr>
            <td><strong>#<?= $r['id'] ?></strong><br><span style="color:#aaa;font-size:.72rem;">Order #<?= $r['order_id'] ?></span></td>
            <td>
                <div style="font-weight:700;font-size:.82rem;"><?= $product ?></div>
                <?php if ($r['quantity']): ?>
                <div style="color:#888;font-size:.75rem;">Qty: <?= $r['quantity'] ?> · RM <?= number_format($r['unit_price'],2) ?></div>
                <?php endif; ?>
            </td>
            <td>
                <span class="user-pill buyer-pill">🛍 <?= $buyer_name ?></span>
                <?php if ($r['buyer_email']): ?>
                <div style="color:#888;font-size:.72rem;margin-top:3px;"><?= htmlspecialchars($r['buyer_email']) ?></div>
                <?php endif; ?>
            </td>
            <td>
                <?php if ($r['seller_id']): ?>
                <span class="user-pill seller-pill">🏷 <?= $seller_name ?></span>
                <?php if ($r['seller_firstname'] || $r['seller_lastname']): ?>
                <div style="color:#888;font-size:.72rem;margin-top:3px;"><?= htmlspecialchars(trim($r['seller_firstname'].' '.$r['seller_lastname'])) ?></div>
                <?php endif; ?>
                <?php else: ?>
                <span style="color:#ccc;">—</span>
                <?php endif; ?>
            </td>
            <td><?= $reason ?></td>
            <td style="font-size:.78rem;"><?= $refund ?></td>
            <td><?= statusBadge($r['status']) ?></td>
            <td style="color:#888;font-size:.75rem;white-space:nowrap;"><?= date('d M Y', strtotime($r['created_at'])) ?></td>
            <td>
                <button class="action-btn review-btn"
                    onclick="openModal(
                        <?= $r['id'] ?>,
                        '<?= addslashes($product) ?>',
                        '<?= addslashes($buyer_name) ?>',
                        '<?= addslashes($seller_name) ?>',
                        '<?= addslashes($reason) ?>',
                        '<?= addslashes($refund) ?>',
                        '<?= $r['status'] ?>',
                        '<?= addslashes($details_esc) ?>',
                        '<?= addslashes($notes_esc) ?>'
                    )">Review</button>
            </td>
        </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<!-- Modal -->
<div class="overlay" id="modal-overlay">
    <div class="modal">
        <div class="modal-hdr">
            <h2>Return Request <span id="m-id"></span></h2>
            <button class="close-btn" onclick="closeModal()">×</button>
        </div>
        <div class="modal-body">
            <div class="info-row"><span class="info-lbl">Product</span><span class="info-val" id="m-product"></span></div>
            <div class="info-row">
                <span class="info-lbl">Buyer</span>
                <span class="info-val"><span class="user-pill buyer-pill" id="m-buyer"></span></span>
            </div>
            <div class="info-row">
                <span class="info-lbl">Seller</span>
                <span class="info-val"><span class="user-pill seller-pill" id="m-seller"></span></span>
            </div>
            <div class="info-row"><span class="info-lbl">Reason</span><span class="info-val" id="m-reason"></span></div>
            <div class="info-row"><span class="info-lbl">Refund Method</span><span class="info-val" id="m-refund"></span></div>
            <div class="info-row" id="m-details-row">
                <span class="info-lbl">Details</span>
                <span class="info-val" id="m-details" style="color:#555;font-size:.82rem;line-height:1.5;"></span>
            </div>

            <hr class="divider">

            <form method="POST">
                <input type="hidden" name="update_return" value="1">
                <input type="hidden" name="return_id" id="m-return-id">

                <div style="margin-bottom:.9rem;">
                    <label style="display:block;font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#444;margin-bottom:.35rem;">Update Status</label>
                    <select name="new_status" id="m-status-select">
                        <option value="pending">Pending</option>
                        <option value="reviewing">Reviewing</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>
                <div style="margin-bottom:.5rem;">
                    <label style="display:block;font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#444;margin-bottom:.35rem;">Admin Notes</label>
                    <textarea name="admin_notes" id="m-notes" rows="3" placeholder="Internal notes or message to buyer…"></textarea>
                </div>
                <button type="submit" class="save-btn">Save Changes</button>
            </form>
        </div>
    </div>
</div>

<script>
function openModal(id, product, buyer, seller, reason, refund, status, details, notes) {
    document.getElementById('m-id').textContent      = '#' + id;
    document.getElementById('m-return-id').value     = id;
    document.getElementById('m-product').textContent = product;
    document.getElementById('m-buyer').textContent   = '🛍 ' + buyer;
    document.getElementById('m-seller').textContent  = '🏷 ' + seller;
    document.getElementById('m-reason').textContent  = reason;
    document.getElementById('m-refund').textContent  = refund;
    document.getElementById('m-details').textContent = details || '—';
    document.getElementById('m-notes').value         = notes;
    document.getElementById('m-status-select').value = status;
    document.getElementById('modal-overlay').classList.add('open');
}
function closeModal() {
    document.getElementById('modal-overlay').classList.remove('open');
}
document.getElementById('modal-overlay').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
</script>

</body>
</html>
