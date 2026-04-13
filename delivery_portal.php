<?php
require_once 'includes/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// ─── Predefined delivery account ───────────────────────────────────────────
define('PORTAL_USER', 'delivery_agent');
define('PORTAL_PASS', 'revibe2024');

// ─── Handle login / logout ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['portal_login'])) {
    if ($_POST['username'] === PORTAL_USER && $_POST['password'] === PORTAL_PASS) {
        $_SESSION['delivery_logged_in'] = true;
    } else {
        $login_error = 'Invalid username or password.';
    }
}
if (isset($_GET['logout'])) {
    unset($_SESSION['delivery_logged_in']);
    header('Location: delivery_portal.php');
    exit;
}

// ─── Handle status update ───────────────────────────────────────────────────
$update_msg = '';
if ($_SESSION['delivery_logged_in'] ?? false) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_delivery'])) {
        $delivery_id    = (int)($_POST['delivery_id'] ?? 0);
        $new_status     = $_POST['new_status'] ?? '';
        $est_date       = trim($_POST['estimated_date'] ?? '');
        $valid_statuses = ['shipped','out_for_delivery','delivered'];

        if ($delivery_id && in_array($new_status, $valid_statuses)) {
            // Only allow update if order has been packed first
            $cur = mysqli_prepare($conn, "SELECT status FROM deliveries WHERE id = ? LIMIT 1");
            mysqli_stmt_bind_param($cur, 'i', $delivery_id);
            mysqli_stmt_execute($cur);
            $cur_row = mysqli_fetch_assoc(mysqli_stmt_get_result($cur));
            mysqli_stmt_close($cur);

            if (($cur_row['status'] ?? '') === 'pending') {
                $update_msg = '<span style="color:#ef4444;">⚠️ Cannot update — order has not been packed yet.</span>';
            } else {
                $est_param = $est_date ?: null;
                $stmt = mysqli_prepare($conn,
                    "UPDATE deliveries SET status=?, estimated_date=? WHERE id=?"
                );
                mysqli_stmt_bind_param($stmt, 'ssi', $new_status, $est_param, $delivery_id);
                if (mysqli_stmt_execute($stmt)) {
                    $update_msg = 'Delivery #' . $delivery_id . ' updated to <strong>' . htmlspecialchars($new_status) . '</strong>.';
                    // Sync order status when delivered
                    if ($new_status === 'delivered') {
                        mysqli_query($conn,
                            "UPDATE orders o
                             JOIN deliveries d ON d.order_id = o.id
                             SET o.status = 'delivered'
                             WHERE d.id = $delivery_id"
                        );
                    }
                }
                mysqli_stmt_close($stmt);
            }
        }
    }
}

// ─── Fetch deliveries ────────────────────────────────────────────────────────
$deliveries = [];
$filter_status = $_GET['status'] ?? '';
$valid_statuses = ['pending','packed','shipped','out_for_delivery','delivered'];
$updatable_statuses = ['shipped','out_for_delivery','delivered'];

if ($_SESSION['delivery_logged_in'] ?? false) {
    $where = '';
    if ($filter_status && in_array($filter_status, $valid_statuses)) {
        $fs = mysqli_real_escape_string($conn, $filter_status);
        $where = "WHERE d.status = '$fs'";
    }
    $result = mysqli_query($conn,
        "SELECT d.*, o.id as order_id, o.full_name, o.address, o.city, o.postcode,
                o.state, o.phone, o.total_amount, o.created_at as order_date
         FROM deliveries d
         JOIN orders o ON o.id = d.order_id
         $where
         ORDER BY d.id DESC"
    );
    while ($row = mysqli_fetch_assoc($result)) $deliveries[] = $row;
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
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Portal — REVIBE</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { margin: 0; font-family: 'Montserrat', sans-serif; background: #f4f4f4; color: #1a1a1a; }
        a { text-decoration: none; }

        /* ── Nav ── */
        .portal-nav {
            background: #1a1a1a;
            padding: 0 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 60px;
        }
        .portal-nav .logo { font-size: 1.4rem; font-weight: 900; color: #c8f04a; letter-spacing: 2px; }
        .portal-nav .nav-right { display: flex; align-items: center; gap: 1.2rem; }
        .portal-nav .nav-right span { color: #94a3b8; font-size: .82rem; }
        .portal-nav .logout-btn {
            padding: .4rem 1rem;
            background: #c8f04a;
            color: #1a1a1a;
            border: none;
            border-radius: 8px;
            font-size: .82rem;
            font-weight: 700;
            cursor: pointer;
        }

        /* ── Login card ── */
        .login-wrap { display: flex; align-items: center; justify-content: center; min-height: calc(100vh - 60px); }
        .login-card {
            background: #fff;
            border-radius: 20px;
            padding: 2.5rem 2.8rem;
            box-shadow: 0 4px 24px rgba(0,0,0,.10);
            width: 100%;
            max-width: 420px;
        }
        .login-card h1 { font-size: 1.3rem; font-weight: 900; margin: 0 0 .3rem; }
        .login-card p { color: #888; font-size: .85rem; margin: 0 0 1.8rem; }
        .form-group { margin-bottom: 1.1rem; }
        .form-group label { display: block; font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #444; margin-bottom: .3rem; }
        .form-group input {
            width: 100%; padding: .7rem 1rem;
            border: 1.5px solid #ddd; border-radius: 8px;
            font-size: .95rem; font-family: inherit; outline: none;
        }
        .form-group input:focus { border-color: #c8f04a; }
        .btn-primary {
            width: 100%; padding: .9rem;
            background: #1a1a1a; color: #c8f04a;
            border: none; border-radius: 12px;
            font-size: .95rem; font-weight: 700;
            cursor: pointer; font-family: inherit;
        }
        .alert-error {
            background: #fdecea; color: #c62828;
            padding: .75rem 1rem; border-radius: 8px;
            font-size: .85rem; margin-bottom: 1.2rem;
        }
        .alert-success {
            background: #dcfce7; color: #166534;
            padding: .75rem 1rem; border-radius: 8px;
            font-size: .85rem; margin-bottom: 1.2rem;
        }

        /* ── Dashboard ── */
        .dash-wrap { max-width: 1200px; margin: 0 auto; padding: 2rem 1.5rem; }
        .dash-header { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.8rem; }
        .dash-header h1 { font-size: 1.4rem; font-weight: 900; margin: 0; }

        /* filter tabs */
        .filter-tabs { display: flex; gap: .5rem; flex-wrap: wrap; }
        .filter-tabs a {
            padding: .4rem .9rem;
            border-radius: 20px;
            font-size: .78rem;
            font-weight: 700;
            border: 1.5px solid #ddd;
            color: #555;
        }
        .filter-tabs a.active, .filter-tabs a:hover {
            background: #1a1a1a; color: #c8f04a; border-color: #1a1a1a;
        }

        /* stats row */
        .stats-row { display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 1.8rem; }
        .stat-card {
            flex: 1; min-width: 130px;
            background: #fff; border-radius: 14px;
            padding: 1.1rem 1.3rem;
            box-shadow: 0 2px 10px rgba(0,0,0,.05);
            text-align: center;
        }
        .stat-card .num { font-size: 1.6rem; font-weight: 900; }
        .stat-card .lbl { font-size: .7rem; color: #888; text-transform: uppercase; letter-spacing: .5px; margin-top: 2px; }

        /* table */
        .table-card {
            background: #fff; border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,.06);
            overflow: hidden;
        }
        table { width: 100%; border-collapse: collapse; }
        thead th {
            padding: .85rem 1rem;
            background: #1a1a1a; color: #c8f04a;
            font-size: .72rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: .5px;
            text-align: left;
        }
        tbody tr { border-bottom: 1px solid #f5f5f5; }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: #fafafa; }
        tbody td { padding: .85rem 1rem; font-size: .88rem; vertical-align: middle; }

        .status-badge {
            display: inline-block;
            padding: .25rem .65rem;
            border-radius: 20px;
            font-size: .72rem;
            font-weight: 700;
            color: #fff;
        }

        /* inline update form */
        .update-form { display: flex; gap: .5rem; align-items: center; flex-wrap: wrap; }
        .update-form select, .update-form input[type=date] {
            padding: .38rem .6rem;
            border: 1.5px solid #ddd; border-radius: 7px;
            font-size: .78rem; font-family: inherit; outline: none;
        }
        .update-form select:focus, .update-form input[type=date]:focus { border-color: #c8f04a; }
        .update-btn {
            padding: .38rem .9rem;
            background: #c8f04a; color: #1a1a1a;
            border: none; border-radius: 7px;
            font-size: .78rem; font-weight: 700;
            cursor: pointer; font-family: inherit;
        }
        .update-btn:hover { background: #b8e03a; }

        @media (max-width: 768px) {
            .update-form { flex-direction: column; align-items: flex-start; }
            thead th:nth-child(4), tbody td:nth-child(4) { display: none; }
        }
    </style>
</head>
<body>

<!-- ── Navigation ─────────────────────────────────────── -->
<nav class="portal-nav">
    <span class="logo">REVIBE</span>
    <?php if ($_SESSION['delivery_logged_in'] ?? false): ?>
    <div class="nav-right">
        <span>Delivery Portal · <?= htmlspecialchars(PORTAL_USER) ?></span>
        <a href="delivery_portal.php?logout=1"><button class="logout-btn">Log Out</button></a>
    </div>
    <?php else: ?>
    <div class="nav-right"><span>Delivery Staff Portal</span></div>
    <?php endif; ?>
</nav>

<?php if (!($_SESSION['delivery_logged_in'] ?? false)): ?>
<!-- ══════════════════════════ LOGIN ══════════════════════════ -->
<div class="login-wrap">
    <div class="login-card">
        <h1>Delivery Portal Login</h1>
        <p>Sign in with your delivery staff credentials.</p>

        <?php if (!empty($login_error)): ?>
        <div class="alert-error"><?= htmlspecialchars($login_error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" autocomplete="username" required
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" autocomplete="current-password" required>
            </div>
            <button type="submit" name="portal_login" class="btn-primary">Sign In</button>
        </form>
    </div>
</div>

<?php else: ?>
<!-- ══════════════════════════ DASHBOARD ══════════════════════════ -->
<div class="dash-wrap">

    <div class="dash-header">
        <div>
            <h1>Delivery Dashboard</h1>
            <p style="color:#888;font-size:.85rem;margin:4px 0 0;">Manage and update order deliveries</p>
        </div>
        <div class="filter-tabs">
            <a href="delivery_portal.php" class="<?= !$filter_status ? 'active' : '' ?>">All</a>
            <?php foreach ($status_labels as $k => $l): ?>
            <a href="delivery_portal.php?status=<?= $k ?>"
               class="<?= $filter_status === $k ? 'active' : '' ?>"><?= $l ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($update_msg): ?>
    <div class="alert-success"><?= $update_msg ?></div>
    <?php endif; ?>

    <!-- Stats -->
    <?php
    $counts = [];
    foreach ($valid_statuses as $s) {
        $r = mysqli_query($conn, "SELECT COUNT(*) as c FROM deliveries WHERE status='$s'");
        $counts[$s] = (int)(mysqli_fetch_assoc($r)['c'] ?? 0);
    }
    $total = array_sum($counts);
    ?>
    <div class="stats-row">
        <div class="stat-card">
            <div class="num"><?= $total ?></div>
            <div class="lbl">Total Orders</div>
        </div>
        <?php foreach ($status_labels as $k => $l): ?>
        <div class="stat-card">
            <div class="num" style="color:<?= $status_colors[$k] ?>;"><?= $counts[$k] ?></div>
            <div class="lbl"><?= $l ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Table -->
    <div class="table-card">
        <?php if (empty($deliveries)): ?>
        <div style="padding:3rem;text-align:center;color:#888;font-size:.9rem;">No deliveries found.</div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Order #</th>
                    <th>Customer</th>
                    <th>Address</th>
                    <th>Tracking</th>
                    <th>Current Status</th>
                    <th>Est. Delivery</th>
                    <th>Update</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($deliveries as $d): ?>
            <tr>
                <td>
                    <strong>#<?= $d['order_id'] ?></strong>
                    <br><span style="font-size:.72rem;color:#94a3b8;"><?= date('d M Y', strtotime($d['order_date'])) ?></span>
                </td>
                <td>
                    <strong><?= htmlspecialchars($d['full_name']) ?></strong>
                    <br><span style="font-size:.72rem;color:#888;"><?= htmlspecialchars($d['phone']) ?></span>
                </td>
                <td style="font-size:.8rem;color:#555;max-width:160px;">
                    <?= htmlspecialchars($d['address']) ?>,
                    <?= htmlspecialchars($d['city']) ?> <?= htmlspecialchars($d['postcode']) ?>,
                    <?= htmlspecialchars($d['state']) ?>
                </td>
                <td style="font-family:monospace;font-size:.8rem;">
                    <?= htmlspecialchars($d['tracking_number']) ?>
                    <?php if ($d['courier']): ?>
                    <br><span style="font-size:.7rem;color:#888;font-family:inherit;"><?= htmlspecialchars($d['courier']) ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="status-badge" style="background:<?= $status_colors[$d['status']] ?? '#888' ?>;">
                        <?= htmlspecialchars($status_labels[$d['status']] ?? $d['status']) ?>
                    </span>
                </td>
                <td style="font-size:.82rem;color:#555;">
                    <?= $d['estimated_date'] ? date('d M Y', strtotime($d['estimated_date'])) : '—' ?>
                </td>
                <td>
                    <?php if ($d['status'] === 'pending'): ?>
                    <span style="font-size:.75rem;color:#f59e0b;font-weight:700;">⏳ Awaiting packing</span>
                    <?php else: ?>
                    <form method="POST" class="update-form">
                        <input type="hidden" name="delivery_id" value="<?= $d['id'] ?>">
                        <select name="new_status">
                            <?php foreach ($status_labels as $k => $l):
                                if (!in_array($k, $updatable_statuses)) continue; ?>
                            <option value="<?= $k ?>" <?= $d['status'] === $k ? 'selected' : '' ?>><?= $l ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="date" name="estimated_date"
                               value="<?= htmlspecialchars($d['estimated_date'] ?? '') ?>"
                               title="Estimated delivery date">
                        <button type="submit" name="update_delivery" class="update-btn">Save</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <p style="text-align:center;color:#bbb;font-size:.75rem;margin-top:2rem;">
        REVIBE Delivery Portal · Staff use only
    </p>
</div>
<?php endif; ?>

</body>
</html>
