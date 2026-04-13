<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['cart_session'])) {
    $_SESSION['cart_session'] = session_id();
}

// Get cart count
$cart_count = 0;
if (isset($conn)) {
    $sid = mysqli_real_escape_string($conn, $_SESSION['cart_session']);
    $r = mysqli_query($conn, "SELECT SUM(quantity) as t FROM cart WHERE session_id='$sid'");
    if ($r) { $row = mysqli_fetch_assoc($r); $cart_count = (int)($row['t'] ?? 0); }
}

// Determine active nav link
$current = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'REVIBE' ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Montserrat:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
    <link rel="stylesheet" href="/revibe/css/style.css?v=<?= time() ?>">

    <style>
body.dark #notif-dropdown {
    background: #1e293b !important;
    border-color: #334155 !important;
}
body.dark #notif-dropdown h3 { color: #e2e8f0; }
body.dark #notif-dropdown a  { color: #93c5fd; }
body.dark #notif-list a {
    border-bottom-color: #334155 !important;
    color: #e2e8f0 !important;
}
body.dark #notif-list a div[style*="color:#1e293b"] { color: #e2e8f0 !important; }
body.dark #notif-list a div[style*="color:#64748b"] { color: #94a3b8 !important; }
body.dark #notif-list a[style*="#eff6ff"] { background: #1e3a8a44 !important; }
</style>
    <script>
    // Apply saved preference immediately to avoid flash
    if (localStorage.getItem('theme') === 'dark') {
        document.documentElement.classList.add('dark-pending');
    }
</script>


</head>
<body>

<nav class="navbar">
    <a href="/revibe/index.php" class="navbar__logo">REVIBE</a>

    <ul class="navbar__links">
        <li>
            <a href="/revibe/products.php?type=new"
                class="<?= ($current==='products.php' && ($_GET['type']??'')=='new') ? 'active':'' ?>">
                New
            </a>
        </li>

        <li>
            <a href="/revibe/products.php?type=preloved"
                class="<?= ($current==='products.php' && ($_GET['type']??'')=='preloved') ? 'active':'' ?>">
                Pre-Loved
            </a>
        </li>
        <li><a href="/revibe/faq.php">FAQ</a></li>
    </ul>

    <div class="navbar__icons">
        <!-- Dark Mode Toggle -->
        <button onclick="toggleDark()" id="theme-btn" title="Toggle dark mode"
            style="background:none; border:none; cursor:pointer; display:flex; align-items:center; justify-content:center;">
        <svg id="icon-moon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#111" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
        </svg>
        <svg id="icon-sun" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#111" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="display:none;">
            <circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
        </svg>
        </button>
<!-- Notifications -->
<div id="notif-wrapper" style="position:relative;">
    <button onclick="toggleDropdown()" title="Notifications"
        style="background:none;border:none;cursor:pointer;display:flex;align-items:center;position:relative;padding:0;">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
        </svg>
        <span id="notif-badge" style="position:absolute;top:-7px;right:-7px;background:#e11d48;
            color:#fff;font-size:10px;font-weight:700;width:17px;height:17px;
            border-radius:50%;display:none;align-items:center;justify-content:center;">0</span>
    </button>

    <div id="notif-dropdown" style="display:none;position:absolute;top:calc(100% + 12px);right:0;
        width:340px;background:white;border-radius:16px;
        box-shadow:0 8px 32px rgba(0,0,0,0.15);
        border:1px solid #e5e7eb;z-index:999;overflow:hidden;">

        <!-- Header -->
        <div style="display:flex;align-items:center;justify-content:space-between;
                    padding:14px 16px;border-bottom:1px solid #f1f5f9;">
            <h3 style="font-size:15px;font-weight:700;margin:0;">Notifications</h3>
            <button onclick="markAllRead()" style="background:none;border:none;
                color:#3b82f6;font-size:12px;cursor:pointer;font-weight:600;">
                Mark all read
            </button>
        </div>

        <!-- List -->
        <div id="notif-list" style="max-height:360px;overflow-y:auto;"></div>

        <!-- Footer -->
        <div style="padding:12px 16px;border-top:1px solid #f1f5f9;text-align:center;">
            <a href="/revibe/notifications.php"
               style="color:#3b82f6;font-size:13px;font-weight:600;text-decoration:none;">
               See All Notifications →
            </a>
        </div>
    </div>
</div>

<!-- Messages -->
        <div style="position:relative;">
            <a href="/revibe/messaging.php" title="Messages">
                <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                </svg>
            </a>
            <span id="msg-badge" style="position:absolute;top:-7px;right:-7px;background:#e11d48;
                color:#fff;font-size:10px;font-weight:700;width:17px;height:17px;
                border-radius:50%;display:none;align-items:center;justify-content:center;">0</span>
        </div>

        <!-- Wishlist -->
        <a href="/revibe/wishlist_page.php" title="Wishlist">
            <svg viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
        </a>
        <!-- Cart -->
        <a href="/revibe/cart.php" title="Cart">
            <svg viewBox="0 0 24 24"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
            <?php if ($cart_count > 0): ?>
            <span class="cart-badge"><?= $cart_count ?></span>
            <?php endif; ?>
        </a>
        <!-- Account -->
        <?php if (isset($_SESSION['user_id'])): ?>
        <a href="/revibe/dashboard.php" title="My Account">
            <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        </a>
        <?php else: ?>
        <a href="/revibe/signin.php" title="Sign In">
            <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        </a>
        <?php endif; ?>
    </div>
                
    <script>
(function() {
    fetch('/revibe/api/messages_api.php?action=get_unread_count')
        .then(r => r.json())
        .then(data => {
            if (data.unread > 0) {
                const badge = document.getElementById('msg-badge');
                badge.textContent = data.unread;
                badge.style.display = 'flex';
            }
        });
})();
</script>

</nav>
