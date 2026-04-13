<?php
require_once 'includes/db.php';
require_once 'includes/session.php';
require_once 'includes/auth.php';

if (isLoggedIn()) { header('Location: /revibe/dashboard.php'); exit; }

$errors  = [];
$timeout = isset($_GET['timeout']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $errors[] = 'Please fill in all fields.';
    } else {
        $result = loginUser($email, $password);
        if ($result['success']) {
            header('Location: /revibe/dashboard.php');
            exit;
        } else {
            $errors[] = $result['message'];
        }
    }
}

$page_title = 'Sign In — REVIBE';
require_once 'includes/header.php';
?>

<main style="min-height:calc(100vh - 120px);display:flex;align-items:center;justify-content:center;padding:2rem 1rem;">
<div style="background:#fff;border-radius:20px;padding:2.5rem 2.8rem;box-shadow:0 4px 24px rgba(0,0,0,.09);width:100%;max-width:440px;">

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.8rem;">
        <div>
            <h1 style="font-size:1.5rem;font-weight:900;color:#1a1a1a;margin:0 0 .3rem;">Welcome Back</h1>
            <p style="color:#888;font-size:.88rem;margin:0;">Sign in to your REVIBE account</p>
        </div>
        <div style="position:relative;">
            <button onclick="toggleAdminMenu()" id="menuBtn" style="display:flex;align-items:center;gap:.45rem;background:#1a1a1a;color:#c8f04a;border:none;padding:.55rem 1rem;border-radius:10px;font-size:.82rem;font-weight:700;cursor:pointer;font-family:inherit;letter-spacing:.3px;transition:background .18s;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/></svg>
                Portal
                <svg id="menuChevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="transition:transform .2s;"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
            <div id="adminMenu" style="display:none;position:absolute;top:calc(100% + 8px);right:0;background:#fff;border:1px solid #e8e8e8;border-radius:14px;box-shadow:0 8px 30px rgba(0,0,0,0.12);width:220px;z-index:100;overflow:hidden;opacity:0;transform:translateY(-6px);transition:opacity .18s,transform .18s;">
                <div style="padding:.6rem .6rem .3rem;font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.8px;color:#aaa;">Select Portal</div>
                <div style="padding:.3rem .6rem .6rem;display:flex;flex-direction:column;gap:.3rem;">
                    <a href="/revibe/signin.php" style="display:flex;align-items:center;gap:.7rem;padding:.7rem .85rem;background:#f8f8f8;color:#1a1a1a;text-decoration:none;border-radius:9px;font-weight:700;font-size:.88rem;transition:background .15s;" onmouseover="this.style.background='#f0f0f0'" onmouseout="this.style.background='#f8f8f8'">
                        <span style="width:32px;height:32px;background:#1a1a1a;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:.9rem;flex-shrink:0;">👤</span>
                        <div>
                            <div>User Portal</div>
                            <div style="font-size:.72rem;color:#999;font-weight:500;">Shop &amp; manage orders</div>
                        </div>
                    </a>
                    <a href="/revibe/admin/signin.php" style="display:flex;align-items:center;gap:.7rem;padding:.7rem .85rem;background:#f4f3ff;color:#4c3fb5;text-decoration:none;border-radius:9px;font-weight:700;font-size:.88rem;transition:background .15s;" onmouseover="this.style.background='#ece9ff'" onmouseout="this.style.background='#f4f3ff'">
                        <span style="width:32px;height:32px;background:#4c3fb5;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:.9rem;flex-shrink:0;">🔐</span>
                        <div>
                            <div>Admin Portal</div>
                            <div style="font-size:.72rem;color:#8b7fd4;font-weight:500;">Dashboard &amp; controls</div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php if ($timeout): ?>
    <div style="background:#fef3c7;color:#92400e;padding:.75rem 1rem;border-radius:8px;font-size:.85rem;margin-bottom:1.2rem;">
        Your session expired. Please sign in again.
    </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
    <div style="background:#fdecea;color:#c62828;padding:.75rem 1rem;border-radius:8px;font-size:.85rem;margin-bottom:1.2rem;">
        <?php foreach ($errors as $e): ?><p style="margin:0 0 2px;">• <?= htmlspecialchars($e) ?></p><?php endforeach; ?>
    </div>
    <?php endif; ?>

    <form method="POST">
        <div style="margin-bottom:1rem;">
            <label style="display:block;font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#444;margin-bottom:.35rem;">Email Address</label>
            <input type="email" name="email" required
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                   placeholder="Enter your email"
                   style="width:100%;padding:.7rem .9rem;border:1.5px solid #ddd;border-radius:8px;font-size:.95rem;font-family:inherit;outline:none;box-sizing:border-box;"
                   onfocus="this.style.borderColor='#c8f04a'" onblur="this.style.borderColor='#ddd'">
        </div>
        <div style="margin-bottom:1.5rem;">
            <label style="display:block;font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#444;margin-bottom:.35rem;">Password</label>
            <input type="password" name="password" required
                   placeholder="Enter your password"
                   style="width:100%;padding:.7rem .9rem;border:1.5px solid #ddd;border-radius:8px;font-size:.95rem;font-family:inherit;outline:none;box-sizing:border-box;"
                   onfocus="this.style.borderColor='#c8f04a'" onblur="this.style.borderColor='#ddd'">
        </div>
        <button type="submit"
                style="width:100%;padding:.9rem;background:#1a1a1a;color:#c8f04a;border:none;border-radius:12px;font-size:.95rem;font-weight:700;cursor:pointer;font-family:inherit;letter-spacing:.5px;">
            Sign In
        </button>
        <div style="display:flex;justify-content:flex-end;margin:.5rem 0 1.2rem;">
            <a href="/revibe/forgot_password.php"
                style="font-size:.82rem;color:#1a1a1a;font-weight:700;text-decoration:none;">
                Forgot password?
            </a>
        </div>
    </form>

    <p style="text-align:center;font-size:.85rem;color:#888;margin:1.4rem 0 0;">
        Don't have an account?
        <a href="/revibe/signup.php" style="color:#1a1a1a;font-weight:700;text-decoration:none;">Sign Up</a>
    </p>
</div>
</main>

<script>
function toggleAdminMenu() {
    const menu = document.getElementById('adminMenu');
    const chevron = document.getElementById('menuChevron');
    const isOpen = menu.style.display === 'block';
    if (isOpen) {
        menu.style.opacity = '0';
        menu.style.transform = 'translateY(-6px)';
        setTimeout(() => { menu.style.display = 'none'; }, 160);
        chevron.style.transform = 'rotate(0deg)';
    } else {
        menu.style.display = 'block';
        requestAnimationFrame(() => {
            menu.style.opacity = '1';
            menu.style.transform = 'translateY(0)';
        });
        chevron.style.transform = 'rotate(180deg)';
    }
}
document.addEventListener('click', function(e) {
    const menu = document.getElementById('adminMenu');
    const chevron = document.getElementById('menuChevron');
    if (!e.target.closest('#menuBtn') && !e.target.closest('#adminMenu')) {
        menu.style.opacity = '0';
        menu.style.transform = 'translateY(-6px)';
        setTimeout(() => { menu.style.display = 'none'; }, 160);
        chevron.style.transform = 'rotate(0deg)';
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
