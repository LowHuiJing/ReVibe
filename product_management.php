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
$page_title = 'My Products';



// ─── Flash message (set before any redirect) ──────────────────────────────────
$flash = $_SESSION['pm_flash'] ?? null;
unset($_SESSION['pm_flash']);

// ─── POST handler — Delete or Toggle listing ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action     = trim($_POST['action']     ?? '');
    $product_id = (int)($_POST['product_id'] ?? 0);

    // ── Delete ────────────────────────────────────────────────────────────────
    if ($action === 'delete' && $product_id) {

        // ownership check
        $chk = $conn->prepare("SELECT id FROM products WHERE id = ? AND seller_id = ?");
        $chk->bind_param("ii", $product_id, $seller_id);
        $chk->execute();

        if (!$chk->get_result()->fetch_assoc()) {
            $_SESSION['pm_flash'] = ['type' => 'error', 'msg' => 'Product not found.'];
        } else {
            $chk->close();

            // get image paths before deleting
            $fi = $conn->prepare("SELECT image_url FROM product_images WHERE product_id = ?");
            $fi->bind_param("i", $product_id);
            $fi->execute();
            $img_rows = $fi->get_result();
            $img_paths = [];
            while ($r = $img_rows->fetch_assoc()) $img_paths[] = $r['image_url'];
            $fi->close();

            // delete image records
            $di = $conn->prepare("DELETE FROM product_images WHERE product_id = ?");
            $di->bind_param("i", $product_id);
            $di->execute();
            $di->close();

            // delete image files from disk
            foreach ($img_paths as $img_url) {
                $file = __DIR__ . '/' . $img_url;
                if (file_exists($file)) unlink($file);
            }

            // delete product
            $dp = $conn->prepare("DELETE FROM products WHERE id = ? AND seller_id = ?");
            $dp->bind_param("ii", $product_id, $seller_id);
            $dp->execute()
                ? $_SESSION['pm_flash'] = ['type' => 'success', 'msg' => 'Product deleted successfully.']
                : $_SESSION['pm_flash'] = ['type' => 'error',   'msg' => 'Delete failed: ' . $dp->error];
            $dp->close();
        }
    }

    // ── Toggle (unlist / list) ────────────────────────────────────────────────
    elseif ($action === 'toggle' && $product_id) {

        $toggle = trim($_POST['toggle_action'] ?? ''); // "unlist" or "list"

        $chk = $conn->prepare("SELECT status, stock_quantity FROM products WHERE id = ? AND seller_id = ?");
        $chk->bind_param("ii", $product_id, $seller_id);
        $chk->execute();
        $prod = $chk->get_result()->fetch_assoc();
        $chk->close();

        if (!$prod) {
            $_SESSION['pm_flash'] = ['type' => 'error', 'msg' => 'Product not found.'];

        } elseif ($toggle === 'unlist') {
            if ($prod['status'] !== 'approved') {
                $_SESSION['pm_flash'] = ['type' => 'error', 'msg' => 'Only active listings can be unlisted.'];
            } else {
                $upd = $conn->prepare("UPDATE products SET status = 'unlisted' WHERE id = ? AND seller_id = ?");
                $upd->bind_param("ii", $product_id, $seller_id);
                $upd->execute()
                    ? $_SESSION['pm_flash'] = ['type' => 'success', 'msg' => 'Product unlisted. It is no longer visible to buyers.']
                    : $_SESSION['pm_flash'] = ['type' => 'error',   'msg' => 'Update failed.'];
                $upd->close();
            }

        } elseif ($toggle === 'list') {
            if ($prod['status'] !== 'unlisted') {
                $_SESSION['pm_flash'] = ['type' => 'error', 'msg' => 'Only unlisted products can be listed again.'];
            } elseif ((int)$prod['stock_quantity'] <= 0) {
                $_SESSION['pm_flash'] = ['type' => 'error', 'msg' => 'Cannot list — stock is 0. Edit the product to update stock first.'];
            } else {
                $upd = $conn->prepare("UPDATE products SET status = 'approved' WHERE id = ? AND seller_id = ?");
                $upd->bind_param("ii", $product_id, $seller_id);
                $upd->execute()
                    ? $_SESSION['pm_flash'] = ['type' => 'success', 'msg' => 'Product listed. It is now visible to buyers.']
                    : $_SESSION['pm_flash'] = ['type' => 'error',   'msg' => 'Update failed.'];
                $upd->close();
            }
        }
    }

    // PRG — redirect back, preserving the user's current tab/search/page
    $qs = http_build_query([
        'status' => $_POST['_status'] ?? '',
        'search' => $_POST['_search'] ?? '',
        'page'   => $_POST['_page']   ?? 1,
    ]);
    header("Location: product_management.php?" . $qs);
    exit;
}

// ─── GET parameters ───────────────────────────────────────────────────────────
$allowed_statuses = ['pending', 'approved', 'rejected', 'sold_out', 'unlisted'];
$status_filter    = in_array($_GET['status'] ?? '', $allowed_statuses) ? $_GET['status'] : '';
$search           = trim($_GET['search'] ?? '');
$page             = max(1, (int)($_GET['page'] ?? 1));
$per_page         = 5;
$offset           = ($page - 1) * $per_page;

// ─── Build WHERE clause ───────────────────────────────────────────────────────
$where_parts = ["p.seller_id = ?"];
$params      = [$seller_id];
$types       = "i";

if ($status_filter) {
    $where_parts[] = "p.status = ?";
    $params[]      = $status_filter;
    $types        .= "s";
}
if ($search !== '') {
    $where_parts[] = "(p.name LIKE ? OR c.name LIKE ?)";
    $keyword = "%" . $search . "%";
    $params[] = $keyword;
    $params[] = $keyword;
    $types        .= "ss";
}

$where_sql = "WHERE " . implode(" AND ", $where_parts);
$order_sql = "ORDER BY
    CASE p.status
        WHEN 'approved'  THEN 1
        WHEN 'unlisted'  THEN 2
        WHEN 'pending'   THEN 3
        WHEN 'rejected'  THEN 4
        WHEN 'sold_out'  THEN 5
        ELSE 6
    END ASC,
    p.created_at DESC";

// ─── Total count ──────────────────────────────────────────────────────────────
$count_stmt = $conn->prepare(
    "SELECT COUNT(*) AS total 
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    $where_sql
    ");
$count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_items = (int)$count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = max(1, (int)ceil($total_items / $per_page));
$count_stmt->close();

if ($page > $total_pages) { $page = $total_pages; $offset = ($page - 1) * $per_page; }

// ─── Fetch products ───────────────────────────────────────────────────────────
$sql = "SELECT p.*, c.name AS category_name,
               (SELECT image_url FROM product_images pi
                WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1) AS cover_image
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        $where_sql $order_sql
        LIMIT ? OFFSET ?";

$pparams = array_merge($params, [$per_page, $offset]);
$ptypes  = $types . "ii";
$stmt    = $conn->prepare($sql);
$stmt->bind_param($ptypes, ...$pparams);
$stmt->execute();
$products_result = $stmt->get_result();
$products = [];
while ($row = $products_result->fetch_assoc()) $products[] = $row;
$stmt->close();

// ─── Tab counts ───────────────────────────────────────────────────────────────
if ($search !== '') {
    $sp       = "%" . $search . "%";
    $tab_stmt = $conn->prepare(
        "SELECT p.status, COUNT(*) AS total
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.seller_id = ? AND (p.name LIKE ? OR c.name LIKE ?)
        GROUP BY p.status");
    $tab_stmt->bind_param("iss", $seller_id, $sp, $sp);
} else {
    $tab_stmt = $conn->prepare(
        "SELECT p.status, COUNT(*) AS total
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.seller_id = ?
        GROUP BY p.status");
    $tab_stmt->bind_param("i", $seller_id);
}
$tab_stmt->execute();
$tab_result = $tab_stmt->get_result();
$counts = ['all' => 0, 'approved' => 0, 'unlisted' => 0, 'pending' => 0, 'rejected' => 0, 'sold_out' => 0];
while ($row = $tab_result->fetch_assoc()) {
    if (isset($counts[$row['status']])) $counts[$row['status']] = (int)$row['total'];
    $counts['all'] += (int)$row['total'];
}
$tab_stmt->close();


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Dashboard — ReVibe</title>
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

        .wrap{max-width:1200px;margin:2rem auto;padding:0 1.5rem;}

        .pm-toast{position:fixed;top:18px;right:18px;z-index:1000;display:flex;align-items:center;gap:10px;padding:.75rem 1rem;border-radius:12px;background:#fff;box-shadow:0 10px 30px rgba(0,0,0,.12);border:1px solid #eee;min-width:280px;}
        .pm-toast-success{border-left:4px solid #22c55e;}
        .pm-toast-error{border-left:4px solid #ef4444;}
        .pm-toast-close{margin-left:auto;background:none;border:none;cursor:pointer;color:#888;font-size:16px;line-height:1;}

        .pm-modal-overlay{display:flex;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:100;align-items:center;justify-content:center;padding:16px;}
        .pm-modal{background:#fff;border-radius:20px;width:100%;max-width:540px;max-height:90vh;overflow-y:auto;box-shadow:0 8px 40px rgba(0,0,0,.2);padding:1.4rem 1.5rem;text-align:center;}
        .pm-modal h3{font-size:1rem;font-weight:900;margin:.6rem 0 .4rem;}
        .pm-modal p{color:#64748b;font-size:.86rem;line-height:1.45;margin:0 0 1rem;}
        .pm-modal-actions{display:flex;gap:.8rem;justify-content:center;flex-wrap:wrap;margin-top:1rem;}
        .pm-modal-cancel{padding:.65rem 1.2rem;border-radius:10px;border:1.5px solid #ddd;background:#fff;font-weight:900;cursor:pointer;}
        .pm-modal-confirm{padding:.65rem 1.2rem;border-radius:10px;border:none;background:#1a1a1a;color:#c8f04a;font-weight:900;cursor:pointer;}
        .pm-modal-confirm-delete{background:#ef4444;color:#fff;}
        .pm-modal-icon{font-size:40px;color:#1a1a1a;}

        .pm-search-form{display:flex;gap:.8rem;flex-wrap:wrap;align-items:center;margin-bottom:1.2rem;}
        .pm-search-wrap{position:relative;flex:1;min-width:240px;}
        .pm-search-icon{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#94a3b8;}
        .pm-search-input{width:100%;padding:.55rem .9rem .55rem 40px;border:1.5px solid #ddd;border-radius:999px;font-size:.85rem;outline:none;background:#fff;}
        .pm-search-input:focus{border-color:#c8f04a;}
        .pm-search-clear{position:absolute;right:10px;top:50%;transform:translateY(-50%);color:#64748b;text-decoration:none;display:flex;align-items:center;justify-content:center;}
        .pm-result-label{color:#64748b;font-size:.82rem;margin:-.2rem 0 1rem;}

        .pm-tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:1.2rem;}
        .pm-tab{padding:.45rem 1rem;border-radius:999px;border:1.5px solid #ddd;background:#fff;cursor:pointer;font-size:.78rem;font-weight:900;color:#555;text-decoration:none;display:inline-flex;align-items:center;gap:8px;}
        .pm-tab.active{background:#1a1a1a;color:#c8f04a;border-color:#1a1a1a;}
        .pm-tab-count{display:inline-flex;align-items:center;justify-content:center;min-width:24px;padding:0 .45rem;height:20px;border-radius:999px;background:#eef2ff;color:#1a1a1a;font-size:.72rem;font-weight:900;}
        .pm-tab.active .pm-tab-count{background:#c8f04a;color:#1a1a1a;}

        .pm-list{display:grid;grid-template-columns:repeat(2, minmax(0, 1fr));gap:1rem;}
        .pm-card{background:#fff;border-radius:16px;box-shadow:0 2px 10px rgba(0,0,0,.06);overflow:hidden;border:1px solid #eef2f7;display:flex;gap:12px;padding:12px;}
        .pm-img-wrap{width:92px;height:92px;border-radius:12px;overflow:hidden;background:#f1f5f9;flex-shrink:0;position:relative;}
        .pm-img{width:100%;height:100%;object-fit:cover;display:block;}
        .pm-img-dim{filter:saturate(.7) contrast(.9) brightness(.9);}
        .pm-img-ph{width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:#94a3b8;}
        .pm-overlay{position:absolute;inset:auto 8px 8px 8px;background:rgba(0,0,0,.75);color:#fff;border-radius:10px;padding:.25rem .45rem;font-size:.7rem;font-weight:900;text-align:center;}
        .pm-info{flex:1;min-width:0;display:flex;flex-direction:column;gap:6px;padding-top:2px;}
        .pm-status-row{display:flex;gap:8px;align-items:center;flex-wrap:wrap;}
        .pm-badge{display:inline-flex;align-items:center;padding:.2rem .65rem;border-radius:999px;font-size:.72rem;font-weight:900;}
        .badge-approved{background:#dcfce7;color:#166534;}
        .badge-unlisted{background:#f1f5f9;color:#334155;}
        .badge-pending{background:#fef3c7;color:#92400e;}
        .badge-rejected{background:#fee2e2;color:#991b1b;}
        .badge-soldout{background:#ede9fe;color:#5b21b6;}
        .pm-reject-reason{color:#991b1b;font-size:.72rem;font-weight:900;}
        .pm-sublabel{color:#64748b;font-size:.72rem;font-weight:800;}
        .pm-title{font-size:.92rem;font-weight:900;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
        .pm-price{font-size:.88rem;font-weight:900;color:#1a1a1a;}
        .pm-price-muted{color:#94a3b8;}
        .pm-meta{font-size:.78rem;color:#64748b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
        .pm-actions{display:flex;gap:6px;flex-wrap:wrap;margin-top:auto;}
        .pm-btn{display:inline-flex;align-items:center;gap:6px;padding:.4rem .7rem;border-radius:10px;border:1.5px solid #ddd;background:#fff;font-size:.78rem;font-weight:900;cursor:pointer;text-decoration:none;color:#1a1a1a;}
        .pm-btn:hover{border-color:#c8f04a;}
        .pm-btn-delete{border-color:#fecaca;color:#991b1b;}
        .pm-btn-delete:hover{border-color:#ef4444;}
        .pm-locked{display:inline-flex;align-items:center;gap:6px;color:#64748b;font-weight:800;font-size:.8rem;}

        .pm-empty{background:#fff;border-radius:16px;padding:3rem 1.5rem;box-shadow:0 2px 10px rgba(0,0,0,.06);text-align:center;color:#94a3b8;border:1px solid #eef2f7;}
        .pm-empty-icon{font-size:42px;display:block;margin-bottom:10px;color:#1a1a1a;}

        .pm-pagination{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-top:1.5rem;}
        .pm-pag-info{color:#64748b;font-size:.82rem;}
        .pm-pag-controls{display:flex;gap:6px;flex-wrap:wrap;}
        .pm-pag-btn{width:34px;height:34px;border-radius:10px;border:1.5px solid #ddd;background:#fff;display:flex;align-items:center;justify-content:center;text-decoration:none;color:#1a1a1a;}
        .pm-pag-btn.active{background:#1a1a1a;border-color:#1a1a1a;color:#c8f04a;}
        .pm-pag-btn.disabled{pointer-events:none;opacity:.45;}

        @media(max-width:900px){.pm-list{grid-template-columns:1fr;}.topbar{padding:.9rem 1rem;}.wrap{padding:0 1rem;}}
    </style>
</head>
<body>

<div class="topbar">
    <div class="topbar-left">
        <a href="/revibe/dashboard.php" class="back-btn" aria-label="Back">
            <span class="material-symbols-outlined">arrow_back</span>
        </a>
        <div>
            <div class="logo">Seller Dashboard — ReVibe</div>
            <div class="sub">Product Management</div>
        </div>
    </div>
    <div class="topbar-actions">
        <a href="seller_product_detail.php" class="btn-teal">
            <span class="material-symbols-outlined">add</span>
            Add Product
        </a>
        <a href="seller_orders.php" class="btn-teal">
            <span class="material-symbols-outlined">receipt_long</span>
            Orders
        </a>
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

<div class="pm-page">

    <?php /* ── Flash toast ── */ if ($flash): ?>
    <div id="pm-toast" class="pm-toast pm-toast-<?= htmlspecialchars($flash['type']) ?>">
        <span class="material-symbols-outlined">
            <?= $flash['type'] === 'success' ? 'check_circle' : 'error' ?>
        </span>
        <span><?= htmlspecialchars($flash['msg']) ?></span>
        <button class="pm-toast-close" onclick="this.parentElement.remove()">✕</button>
    </div>
    <script>setTimeout(() => { const t = document.getElementById('pm-toast'); if (t) t.remove(); }, 4000);</script>
    <?php endif; ?>

    <?php /* ── Delete confirm modal ── */ ?>
    <div id="modal-delete" class="pm-modal-overlay" style="display:none;"
         onclick="if(event.target===this) closeModal('modal-delete')">
        <div class="pm-modal">
            <span class="material-symbols-outlined pm-modal-icon pm-modal-delete">delete_forever</span>
            <h3>Delete Product?</h3>
            <p>Are you sure you want to delete <strong id="del-title"></strong>? This cannot be undone.</p>
            <div class="pm-modal-actions">
                <button class="pm-modal-cancel" onclick="closeModal('modal-delete')">Cancel</button>
                <form id="del-form" method="POST" action="product_management.php" style="margin:0;">
                    <input type="hidden" name="action"     value="delete">
                    <input type="hidden" name="product_id" id="del-id">
                    <input type="hidden" name="_status"    value="<?= htmlspecialchars($status_filter) ?>">
                    <input type="hidden" name="_search"    value="<?= htmlspecialchars($search) ?>">
                    <input type="hidden" name="_page"      value="<?= $page ?>">
                    <button type="submit" class="pm-modal-confirm pm-modal-confirm-delete">Yes, Delete</button>
                </form>
            </div>
        </div>
    </div>

    <?php /* ── Toggle (unlist/list) confirm modal ── */ ?>
    <div id="modal-toggle" class="pm-modal-overlay" style="display:none;"
         onclick="if(event.target===this) closeModal('modal-toggle')">
        <div class="pm-modal">
            <span class="material-symbols-outlined pm-modal-icon" id="toggle-icon"></span>
            <h3 id="toggle-title"></h3>
            <p  id="toggle-body"></p>
            <div class="pm-modal-actions">
                <button class="pm-modal-cancel" onclick="closeModal('modal-toggle')">Cancel</button>
                <form id="toggle-form" method="POST" action="product_management.php" style="margin:0;">
                    <input type="hidden" name="action"        value="toggle">
                    <input type="hidden" name="product_id"    id="toggle-id">
                    <input type="hidden" name="toggle_action" id="toggle-action">
                    <input type="hidden" name="_status"       value="<?= htmlspecialchars($status_filter) ?>">
                    <input type="hidden" name="_search"       value="<?= htmlspecialchars($search) ?>">
                    <input type="hidden" name="_page"         value="<?= $page ?>">
                    <button type="submit" id="toggle-btn" class="pm-modal-confirm">Confirm</button>
                </form>
            </div>
        </div>
    </div>

    <script>
    (function() {
    function checkUnread() {
        fetch('/revibe/api/messages_api.php?action=get_unread_count')
            .then(r => r.json())
            .then(data => {
                const badge = document.getElementById('msgBadge');
                if (data.unread > 0) {
                    badge.textContent = data.unread;
                    badge.style.display = 'flex';
                } else {
                    badge.style.display = 'none';
                }
            });
    }
    checkUnread();
    setInterval(checkUnread, 5000);
})();


    </script>

    <div class="wrap">
    <main class="pm-container">

        <?php /* ── Search bar ── */ ?>
        <form class="pm-search-form" method="GET" action="product_management.php">
            <?php if ($status_filter): ?>
                <input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>">
            <?php endif; ?>
            <div class="pm-search-wrap">
                <span class="material-symbols-outlined pm-search-icon">search</span>
                <input type="text" class="pm-search-input" name="search"
                       placeholder="Search your products…"
                       value="<?= htmlspecialchars($search) ?>"
                       autocomplete="off">
                <?php if ($search): ?>
                    <a class="pm-search-clear"
                       href="product_management.php?status=<?= htmlspecialchars($status_filter) ?>"
                       title="Clear search">
                        <span class="material-symbols-outlined">close</span>
                    </a>
                <?php endif; ?>
            </div>
            <button type="submit" class="btn-teal">Search</button>
        </form>

        <?php if ($search): ?>
            <p class="pm-result-label">
                Results for "<strong><?= htmlspecialchars($search) ?></strong>"
                — <?= $total_items ?> item(s) found
            </p>
        <?php endif; ?>

        <?php /* ── Tabs ── */ ?>
        <div class="pm-tabs">
            <?php
            $tabs = [
                ['key' => 'all',      'label' => 'All'],
                ['key' => 'approved', 'label' => 'Active'],
                ['key' => 'unlisted', 'label' => 'Unlisted'],
                ['key' => 'pending',  'label' => 'Pending'],
                ['key' => 'rejected', 'label' => 'Rejected'],
                ['key' => 'sold_out', 'label' => 'Sold Out'],
            ];
            foreach ($tabs as $tab):
                $cnt    = $tab['key'] === 'all' ? $counts['all'] : ($counts[$tab['key']] ?? 0);
                $active = ($status_filter === $tab['key']) || ($tab['key'] === 'all' && $status_filter === '');
                $url    = 'product_management.php?' . http_build_query([
                    'status' => $tab['key'] === 'all' ? '' : $tab['key'],
                    'search' => $search,
                    'page'   => 1,
                ]);
            ?>
            <a class="pm-tab <?= $active ? 'active' : '' ?>" href="<?= htmlspecialchars($url) ?>">
                <?= htmlspecialchars($tab['label']) ?>
                <span class="pm-tab-count"><?= $cnt ?></span>
            </a>
            <?php endforeach; ?>
        </div>

        <?php /* ── Product list ── */ ?>
        <?php if (empty($products)): ?>
            <div class="pm-empty">
                <span class="material-symbols-outlined pm-empty-icon">inventory_2</span>
                <p><?= $search ? 'No products match your search.' : 'No products found.' ?></p>
                <?php if (!$search && !$status_filter): ?>
                    <a href="seller_product_detail.php" class="btn-teal" style="margin-top:8px;">
                        List Your First Product
                    </a>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <div class="pm-list">
                <?php foreach ($products as $p):
                    $status = $p['status'];
                    $pid    = (int)$p['id'];
                    $ptitle = htmlspecialchars($p['name'] ?? 'Unnamed Product');
                    $ptitle_js = addslashes($p['name'] ?? 'Unnamed Product');
                ?>
                <div class="pm-card <?= card_class($status) ?>">

                    <?php /* Product image */ ?>
                    <div class="pm-img-wrap">
                        <?php if ($p['cover_image']): ?>
                            <img class="pm-img <?= $status === 'unlisted' ? 'pm-img-dim' : '' ?>"
                                 src="<?= htmlspecialchars($p['cover_image']) ?>"
                                 alt="<?= $ptitle ?>"
                                 onerror="this.src='images/placeholder.jpg'">
                        <?php else: ?>
                            <div class="pm-img-ph">
                                <span class="material-symbols-outlined">image_not_supported</span>
                            </div>
                        <?php endif; ?>

                        <?php if ($status === 'sold_out'): ?>
                            <div class="pm-overlay pm-overlay-soldout">SOLD OUT</div>
                        <?php elseif ($status === 'unlisted'): ?>
                            <div class="pm-overlay pm-overlay-unlisted">
                                <span class="material-symbols-outlined">visibility_off</span>
                                UNLISTED
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php /* Product info */ ?>
                    <div class="pm-info">
                        <div class="pm-status-row">
                            <span class="pm-badge <?= badge_class($status) ?>"><?= badge_label($status) ?></span>
                            <?php if ($status === 'rejected' && !empty($p['rejection_reason'])): ?>
                                <span class="pm-reject-reason">Reason: <?= htmlspecialchars($p['rejection_reason']) ?></span>
                            <?php elseif (sublabel($status)): ?>
                                <span class="pm-sublabel"><?= sublabel($status) ?></span>
                            <?php endif; ?>
                        </div>
                        <h3 class="pm-title"><?= $ptitle ?></h3>
                        <p class="pm-price <?= in_array($status, ['sold_out', 'unlisted']) ? 'pm-price-muted' : '' ?>">
                            RM <?= number_format((float)$p['price'], 2) ?>
                        </p>
                        <p class="pm-meta">
                            <?= htmlspecialchars($p['category_name'] ?? '') ?>
                            &middot; <?= htmlspecialchars(str_replace('_', ' ', $p['condition'] ?? '')) ?>
                            &middot; Stock: <?= (int)$p['stock_quantity'] ?>
                        </p>
                    </div>

                    <?php /* Action buttons */ ?>
                    <div class="pm-actions">
                        <?php if ($status === 'approved'): ?>
                            <a class="pm-btn pm-btn-edit" href="seller_product_detail.php?edit=<?= $pid ?>">
                                <span class="material-symbols-outlined">edit</span> Edit
                            </a>
                            <button class="pm-btn pm-btn-unlist"
                                    onclick="openToggle(<?= $pid ?>, '<?= $ptitle_js ?>', 'unlist')">
                                <span class="material-symbols-outlined">visibility_off</span> Unlist
                            </button>
                            <button class="pm-btn pm-btn-delete"
                                    onclick="openDelete(<?= $pid ?>, '<?= $ptitle_js ?>')">
                                <span class="material-symbols-outlined">delete</span> Delete
                            </button>

                        <?php elseif ($status === 'unlisted'): ?>
                            <button class="pm-btn pm-btn-list"
                                    onclick="openToggle(<?= $pid ?>, '<?= $ptitle_js ?>', 'list')">
                                <span class="material-symbols-outlined">visibility</span> List
                            </button>
                            <button class="pm-btn pm-btn-delete"
                                    onclick="openDelete(<?= $pid ?>, '<?= $ptitle_js ?>')">
                                <span class="material-symbols-outlined">delete</span> Delete
                            </button>

                        <?php elseif ($status === 'pending'): ?>
                            <div class="pm-locked">
                                <span class="material-symbols-outlined">lock</span> Under Review
                            </div>

                        <?php elseif ($status === 'rejected'): ?>
                            <a class="pm-btn pm-btn-resubmit" href="seller_product_detail.php?edit=<?= $pid ?>">
                                <span class="material-symbols-outlined">refresh</span> Resubmit
                            </a>
                            <button class="pm-btn pm-btn-delete"
                                    onclick="openDelete(<?= $pid ?>, '<?= $ptitle_js ?>')">
                                <span class="material-symbols-outlined">delete</span> Delete
                            </button>

                        <?php elseif ($status === 'sold_out'): ?>
                            <a class="pm-btn pm-btn-edit" href="seller_product_detail.php?view=<?= $pid ?>">
                                <span class="material-symbols-outlined">visibility</span> View
                            </a>
                        <?php endif; ?>
                    </div>

                </div>
                <?php endforeach; ?>
            </div>

            <?php /* ── Pagination ── */ ?>
            <?php if ($total_pages > 1): ?>
            <div class="pm-pagination">
                <p class="pm-pag-info">
                    Showing
                    <strong><?= min(($page - 1) * $per_page + 1, $total_items) ?>–<?= min($page * $per_page, $total_items) ?></strong>
                    of <strong><?= $total_items ?></strong> items
                </p>
                <div class="pm-pag-controls">
                    <a class="pm-pag-btn <?= $page <= 1 ? 'disabled' : '' ?>"
                       href="product_management.php?<?= http_build_query(['status'=>$status_filter,'search'=>$search,'page'=>$page-1]) ?>">
                        <span class="material-symbols-outlined">chevron_left</span>
                    </a>
                    <?php for ($n = 1; $n <= $total_pages; $n++): ?>
                        <a class="pm-pag-btn <?= $n === $page ? 'active' : '' ?>"
                           href="product_management.php?<?= http_build_query(['status'=>$status_filter,'search'=>$search,'page'=>$n]) ?>">
                            <?= $n ?>
                        </a>
                    <?php endfor; ?>
                    <a class="pm-pag-btn <?= $page >= $total_pages ? 'disabled' : '' ?>"
                       href="product_management.php?<?= http_build_query(['status'=>$status_filter,'search'=>$search,'page'=>$page+1]) ?>">
                        <span class="material-symbols-outlined">chevron_right</span>
                    </a>
                </div>
            </div>
            <?php endif; ?>

        <?php endif; /* end product list */ ?>

    </main>
</div>
</div>

<?php /* ── Helper functions (used above in HTML — declared here for PHP 8 compatibility) ── */ ?>
<?php

function badge_class(string $status): string {
    $map = ['approved'=>'badge-approved','unlisted'=>'badge-unlisted',
            'pending'=>'badge-pending','rejected'=>'badge-rejected','sold_out'=>'badge-soldout'];
    return $map[$status] ?? '';
}
function badge_label(string $status): string {
    $map = ['approved'=>'Active','unlisted'=>'Unlisted','pending'=>'Waiting for Approval',
            'rejected'=>'Rejected','sold_out'=>'Sold Out'];
    return $map[$status] ?? $status;
}
function card_class(string $status): string {
    $map = ['unlisted'=>'pm-card-unlisted','pending'=>'pm-card-pending',
            'rejected'=>'pm-card-rejected','sold_out'=>'pm-card-soldout'];
    return $map[$status] ?? '';
}
function sublabel(string $status): string {
    $map = ['approved'=>'Visible &amp; Searchable','unlisted'=>'Hidden from buyers',
            'pending'=>'Being reviewed by admin','sold_out'=>'Item no longer available'];
    return $map[$status] ?? '';
}
?>

<script>
function openDelete(id, title) {
    document.getElementById('del-title').textContent = '"' + title + '"';
    document.getElementById('del-id').value = id;
    document.getElementById('modal-delete').style.display = 'flex';
}

function openToggle(id, title, action) {
    const isUnlist = action === 'unlist';
    document.getElementById('toggle-icon').textContent  = isUnlist ? 'visibility_off' : 'visibility';
    document.getElementById('toggle-icon').className    = 'material-symbols-outlined pm-modal-icon ' + (isUnlist ? 'pm-modal-unlist' : 'pm-modal-list');
    document.getElementById('toggle-title').textContent = isUnlist ? 'Unlist Product?' : 'List Product?';
    document.getElementById('toggle-body').innerHTML    = isUnlist
        ? '<strong>"' + title + '"</strong> will be hidden from all buyers. You can list it again at any time.'
        : '<strong>"' + title + '"</strong> will become visible and searchable to all buyers again.';
    document.getElementById('toggle-id').value          = id;
    document.getElementById('toggle-action').value      = action;
    const btn = document.getElementById('toggle-btn');
    btn.textContent = isUnlist ? 'Yes, Unlist' : 'Yes, List';
    btn.className   = 'pm-modal-confirm ' + (isUnlist ? 'pm-modal-confirm-unlist' : 'pm-modal-confirm-list');
    document.getElementById('modal-toggle').style.display = 'flex';
}

function closeModal(id) {
    document.getElementById(id).style.display = 'none';
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { closeModal('modal-delete'); closeModal('modal-toggle'); }
});
</script>

</body>
</html>
