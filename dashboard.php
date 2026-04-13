<?php
require_once 'includes/db.php';
require_once 'includes/session.php';
require_once 'includes/auth.php';

checkSessionTimeout();

if (!isset($_SESSION['user_id'])) {
    $page_title = 'Dashboard — REVIBE';
    require_once 'includes/header.php';
    echo '<main style="max-width:500px;margin:80px auto;padding:0 1.5rem;text-align:center;">
        <div style="background:#fff;border-radius:20px;box-shadow:0 2px 14px rgba(0,0,0,.08);padding:2.5rem 2rem;">
            <div style="font-size:2.5rem;margin-bottom:1rem;">🔒</div>
            <h1 style="font-size:1.2rem;font-weight:900;margin:0 0 .5rem;">Sign in to view your dashboard</h1>
            <p style="color:#888;font-size:.88rem;margin:0 0 1.5rem;">Access your orders, listings, and account settings.</p>
            <div style="display:flex;gap:.7rem;justify-content:center;flex-wrap:wrap;">
                <a href="/revibe/signin.php" style="padding:.7rem 1.6rem;background:#1a1a1a;color:#c8f04a;border-radius:10px;font-weight:700;font-size:.88rem;text-decoration:none;">Sign In</a>
                <a href="/revibe/signup.php" style="padding:.7rem 1.6rem;background:#fff;color:#1a1a1a;border:2px solid #1a1a1a;border-radius:10px;font-weight:700;font-size:.88rem;text-decoration:none;">Create Account</a>
            </div>
        </div>
    </main>';
    require_once 'includes/footer.php';
    exit;
}

$user_id = $_SESSION['user_id'];

$result = mysqli_query($conn,
    "SELECT u.username, u.email, u.created_at,
            p.first_name, p.last_name, p.avatar_url, p.bio
     FROM users u
     LEFT JOIN user_profiles p ON u.id = p.user_id
     WHERE u.id = $user_id"
);
$user = mysqli_fetch_assoc($result);

$display_name = !empty($user['first_name']) ? $user['first_name'] : $user['username'];
$join_date    = date('F Y', strtotime($user['created_at']));

// Stats
$order_count   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM orders WHERE user_id=$user_id"))['c'];
$product_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM products WHERE seller_id=$user_id"))['c'];
$rating_data   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c, ROUND(AVG(rating),1) avg FROM reviews WHERE product_id IN (SELECT id FROM products WHERE seller_id=$user_id)"));
$ticket_count  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM support_tickets WHERE user_id=$user_id AND status!='closed'"))['c'] ?? 0;

$page_title = 'Dashboard — REVIBE';
require_once 'includes/header.php';
?>

<main style="max-width:1100px;margin:40px auto;padding:0 1.5rem;">

    <!-- Welcome banner -->
    <div style="background:#1a1a1a;border-radius:20px;padding:2rem 2.4rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:2rem;">
        <div>
            <p style="color:#c8f04a;font-size:.78rem;font-weight:700;letter-spacing:2px;text-transform:uppercase;margin:0 0 .3rem;">Welcome back</p>
            <h1 style="color:#fff;font-size:1.6rem;font-weight:900;margin:0 0 .3rem;"><?= htmlspecialchars($display_name) ?> 👋</h1>
            <p style="color:#94a3b8;font-size:.85rem;margin:0;">Member since <?= $join_date ?></p>
        </div>
        <div style="display:flex;align-items:center;gap:.8rem;flex-shrink:0;">
            

            <div style="width:60px;height:60px;border-radius:50%;background:#c8f04a;display:flex;align-items:center;justify-content:center;font-size:1.6rem;font-weight:900;color:#1a1a1a;flex-shrink:0;overflow:hidden;">
                <?php if (!empty($user['avatar_url'])): ?>
                <img src="<?= htmlspecialchars($user['avatar_url']) ?>" alt="" style="width:100%;height:100%;border-radius:50%;object-fit:cover;">
                <?php else: ?>
                <?= strtoupper(substr($user['username'], 0, 1)) ?>
                <?php endif; ?>
            </div>
            <a href="/revibe/logout.php"
                style="padding:.55rem 1.1rem;background:#fff;color:#1a1a1a;border-radius:10px;font-weight:800;font-size:.85rem;text-decoration:none;border:2px solid #fff;">
                Logout
            </a>
        </div>
    </div>

    <!-- Stats -->
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:2rem;">
        <?php
        $stats = [
            ['Orders',           $order_count,                                                   'profile_private.php', '🛍️'],
            ['Products Listed',  $product_count,                                                 'product_management.php?id='.$user_id, '👗'],
            ['Avg Rating',       $rating_data['avg'] ? $rating_data['avg'].' / 5' : '—',        'profile_public.php?id='.$user_id, '⭐'],
            ['Open Tickets',     $ticket_count,                                                  'profile_private.php', '🎫'],
        ];
        foreach ($stats as [$label, $value, $link, $icon]):
        ?>
        <a href="/revibe/<?= $link ?>" style="text-decoration:none;">
            <div style="background:#fff;border-radius:16px;padding:1.3rem 1.5rem;box-shadow:0 2px 10px rgba(0,0,0,.05);transition:box-shadow .15s;"
                 onmouseover="this.style.boxShadow='0 4px 20px rgba(0,0,0,.12)'" onmouseout="this.style.boxShadow='0 2px 10px rgba(0,0,0,.05)'">
                <div style="font-size:1.4rem;margin-bottom:.5rem;"><?= $icon ?></div>
                <div style="font-size:1.5rem;font-weight:900;color:#1a1a1a;"><?= $value ?></div>
                <div style="font-size:.75rem;color:#888;text-transform:uppercase;letter-spacing:.5px;margin-top:2px;"><?= $label ?></div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Quick actions -->
    <h2 style="font-size:1rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#1a1a1a;margin:0 0 1rem;">Quick Actions</h2>
    <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:1rem;">
        <?php
        $actions = [
            ['Account Settings',   'Update your profile and password',        'account.php',                          '#c8f04a'],
            ['My Public Profile',  'See how others view your profile',         'profile_public.php?id='.$user_id,     '#1a1a1a'],
            ['Order History',      'View your past orders and payments',       'profile_private.php',                  '#1a1a1a'],
            ['Support Tickets',    'Submit or track support requests',         'profile_private.php#tickets',          '#1a1a1a'],
        ];
        foreach ($actions as [$title, $desc, $link, $bg]):
        $fg = $bg === '#c8f04a' ? '#1a1a1a' : '#fff';
        ?>
        <a href="/revibe/<?= $link ?>" style="text-decoration:none;">
            <div style="background:<?= $bg ?>;border-radius:14px;padding:1.3rem 1.5rem;">
                <p style="color:<?= $fg ?>;font-weight:700;font-size:.95rem;margin:0 0 4px;"><?= $title ?></p>
                <p style="color:<?= $bg==='#c8f04a'?'#444':'#94a3b8' ?>;font-size:.8rem;margin:0;"><?= $desc ?></p>
            </div>
        </a>
        <?php endforeach; ?>
    </div>

</main>

<style>@media(max-width:640px){main .stats-grid{grid-template-columns:repeat(2,1fr)!important;}}</style>
<?php require_once 'includes/footer.php'; ?>
