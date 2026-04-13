<?php
require_once '../includes/db.php';
require_once 'includes/admin_auth.php';
requireAdminLogin();

$flash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_word') {
        $word = strtolower(trim(mysqli_real_escape_string($conn, $_POST['word'] ?? '')));
        if ($word) {
            mysqli_query($conn, "INSERT IGNORE INTO banned_words (word) VALUES ('$word')");
            $flash = "Word \"$word\" added.";
        }
    } elseif ($action === 'delete_word') {
        $word_id = (int)($_POST['word_id'] ?? 0);
        mysqli_query($conn, "DELETE FROM banned_words WHERE id=$word_id");
        $flash = 'Word removed.';
    }
    header('Location: /revibe/admin/system_settings.php' . ($flash ? '?msg='.urlencode($flash) : '')); exit;
}

$flash = htmlspecialchars($_GET['msg'] ?? '');
$banned_words = mysqli_query($conn, "SELECT * FROM banned_words ORDER BY word ASC");
$total_banned = $banned_words ? mysqli_num_rows($banned_words) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>System Settings — REVIBE Admin</title>
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
        <li><a href="/revibe/admin/manage_products.php" class="sidebar-menu-link"><span class="menu-icon">📦</span> Products</a></li>
    </ul>
    <div class="sidebar-section-label">System</div>
    <ul class="sidebar-menu">
        <li><a href="/revibe/admin/system_settings.php" class="sidebar-menu-link active"><span class="menu-icon">⚙️</span> Settings</a></li>
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
            <h1>System Settings</h1>
            <p>Content moderation and platform configuration</p>
        </div>
        <div class="topbar-right">
            <div class="admin-chip">
                <div class="admin-chip-avatar"><?= strtoupper(substr($_SESSION['username'],0,1)) ?></div>
                <span class="admin-chip-name"><?= htmlspecialchars($_SESSION['username']) ?></span>
            </div>
            <a href="/revibe/admin/logout.php" class="logout-btn">Logout</a>
        </div>
    </div>

    <?php if ($flash): ?>
    <div class="alert alert-success" style="margin-bottom:1.4rem;">✓ <?= $flash ?></div>
    <?php endif; ?>

    <!-- Banned words -->
    <div class="card" style="margin-bottom:1.4rem;">
        <div class="card-header">
            <div>
                <div class="card-title">🚫 Banned Words</div>
                <div class="card-sub"><?= $total_banned ?> word<?= $total_banned !== 1 ? 's' : '' ?> on the block list</div>
            </div>
        </div>

        <!-- Add word -->
        <div style="padding:1.2rem 1.5rem;border-bottom:1px solid var(--border);">
            <form method="POST" style="display:flex;gap:.75rem;align-items:flex-end;max-width:500px;">
                <div style="flex:1;">
                    <label style="display:block;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-2);margin-bottom:.35rem;">Add a word</label>
                    <input type="text" name="word" class="form-input" placeholder="e.g. scam, spam…" required autocomplete="off">
                </div>
                <input type="hidden" name="action" value="add_word">
                <button type="submit" class="btn btn-primary" style="white-space:nowrap;">+ Add Word</button>
            </form>
        </div>

        <!-- Word list -->
        <div style="padding:1.2rem 1.5rem;">
            <?php if ($total_banned > 0): ?>
            <div style="display:flex;flex-wrap:wrap;gap:.5rem;">
                <?php while ($w = mysqli_fetch_assoc($banned_words)): ?>
                <div class="word-chip">
                    <span><?= htmlspecialchars($w['word']) ?></span>
                    <form method="POST" style="display:inline;margin:0;">
                        <input type="hidden" name="action"  value="delete_word">
                        <input type="hidden" name="word_id" value="<?= $w['id'] ?>">
                        <button type="submit" title="Remove" onclick="return confirm('Remove \'<?= htmlspecialchars($w['word']) ?>\'?')">✕</button>
                    </form>
                </div>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
            <p style="color:var(--text-3);font-size:.85rem;text-align:center;padding:1.5rem 0;">No banned words yet. Add some above.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- System info -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">ℹ️ System Information</div>
        </div>
        <table class="info-table" style="width:100%;">
            <tr style="border-bottom:1px solid var(--border);">
                <td>Application</td>
                <td>REVIBE — Sustainable Shopping Platform</td>
            </tr>
            <tr style="border-bottom:1px solid var(--border);">
                <td>PHP Version</td>
                <td><?= phpversion() ?></td>
            </tr>
            <tr style="border-bottom:1px solid var(--border);">
                <td>Server</td>
                <td><?= htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') ?></td>
            </tr>
            <tr style="border-bottom:1px solid var(--border);">
                <td>Database</td>
                <td>MySQL / MariaDB</td>
            </tr>
            <tr>
                <td>Current Date</td>
                <td><?= date('d F Y, H:i') ?></td>
            </tr>
        </table>
    </div>

</div>
</div>
</body>
</html>
