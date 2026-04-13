<?php
require_once 'includes/db.php';
require_once 'includes/session.php';
require_once 'includes/auth.php';

if (isLoggedIn()) { header('Location: /revibe/dashboard.php'); exit; }

$errors  = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']         ?? '');
    $email    = trim($_POST['email']            ?? '');
    $password = $_POST['password']              ?? '';
    $confirm  = $_POST['confirm_password']      ?? '';

    $errors = validateRegistration($username, $email, $password, $confirm);

    if (empty($errors)) {
        $result = registerUser($username, $email, $password);
        if ($result['success']) {
            $success = $result['message'];
        } else {
            $errors[] = $result['message'];
        }
    }
}

$page_title = 'Sign Up — REVIBE';
require_once 'includes/header.php';
?>

<main style="min-height:calc(100vh - 120px);display:flex;align-items:center;justify-content:center;padding:2rem 1rem;">
<div style="background:#fff;border-radius:20px;padding:2.5rem 2.8rem;box-shadow:0 4px 24px rgba(0,0,0,.09);width:100%;max-width:460px;">

    <h1 style="font-size:1.5rem;font-weight:900;color:#1a1a1a;margin:0 0 .3rem;">Create Account</h1>
    <p style="color:#888;font-size:.88rem;margin:0 0 1.8rem;">Join REVIBE and start shopping sustainably</p>

    <?php if (!empty($errors)): ?>
    <div style="background:#fdecea;color:#c62828;padding:.75rem 1rem;border-radius:8px;font-size:.85rem;margin-bottom:1.2rem;">
        <?php foreach ($errors as $e): ?><p style="margin:0 0 2px;">• <?= htmlspecialchars($e) ?></p><?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div style="background:#dcfce7;color:#166534;padding:1rem 1.1rem;border-radius:10px;margin-bottom:1.2rem;font-size:.88rem;">
        <p style="margin:0 0 .5rem;font-weight:700;">✓ <?= htmlspecialchars($success) ?></p>
        <a href="/revibe/signin.php" style="color:#166534;font-weight:700;">Click here to sign in →</a>
    </div>
    <?php endif; ?>

    <?php if (!$success): ?>
    <form method="POST">
        <?php
        $is = 'width:100%;padding:.7rem .9rem;border:1.5px solid #ddd;border-radius:8px;font-size:.95rem;font-family:inherit;outline:none;box-sizing:border-box;';
        $ls = 'display:block;font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#444;margin-bottom:.35rem;';
        $gs = 'margin-bottom:1rem;';
        ?>
        <div style="<?= $gs ?>">
            <label style="<?= $ls ?>">Username</label>
            <input type="text" name="username" required
                   value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                   placeholder="At least 3 characters"
                   style="<?= $is ?>" onfocus="this.style.borderColor='#c8f04a'" onblur="this.style.borderColor='#ddd'">
        </div>
        <div style="<?= $gs ?>">
            <label style="<?= $ls ?>">Email Address</label>
            <input type="email" name="email" required
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                   placeholder="Enter your email"
                   style="<?= $is ?>" onfocus="this.style.borderColor='#c8f04a'" onblur="this.style.borderColor='#ddd'">
        </div>
        <div style="<?= $gs ?>">
            <label style="<?= $ls ?>">Password</label>
            <input type="password" name="password" required
                   placeholder="Minimum 8 characters"
                   style="<?= $is ?>" onfocus="this.style.borderColor='#c8f04a'" onblur="this.style.borderColor='#ddd'">
        </div>
        <div style="margin-bottom:1.5rem;">
            <label style="<?= $ls ?>">Confirm Password</label>
            <input type="password" name="confirm_password" required
                   placeholder="Repeat your password"
                   style="<?= $is ?>" onfocus="this.style.borderColor='#c8f04a'" onblur="this.style.borderColor='#ddd'">
        </div>
        <button type="submit"
                style="width:100%;padding:.9rem;background:#1a1a1a;color:#c8f04a;border:none;border-radius:12px;font-size:.95rem;font-weight:700;cursor:pointer;font-family:inherit;letter-spacing:.5px;">
            Create Account
        </button>
    </form>
    <?php endif; ?>

    <p style="text-align:center;font-size:.85rem;color:#888;margin:1.4rem 0 0;">
        Already have an account?
        <a href="/revibe/signin.php" style="color:#1a1a1a;font-weight:700;text-decoration:none;">Sign In</a>
    </p>
</div>
</main>

<?php require_once 'includes/footer.php'; ?>
