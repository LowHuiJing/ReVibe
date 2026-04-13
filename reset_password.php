<?php
require_once 'includes/db.php';
require_once 'includes/session.php';
require_once 'includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (isLoggedIn()) { header('Location: /revibe/dashboard.php'); exit; }

$errors = [];
$success = false;

function token_hash($token) {
    return hash('sha256', $token);
}

$email = trim($_GET['email'] ?? ($_POST['email'] ?? ''));
$token = trim($_GET['token'] ?? ($_POST['token'] ?? ''));

if ($email === '' || $token === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Invalid or missing reset link.';
}

// Token expiry: 60 minutes
$TOKEN_TTL_MINUTES = 60;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
    $new1 = $_POST['new_password'] ?? '';
    $new2 = $_POST['confirm_password'] ?? '';

    if (strlen($new1) < 6) $errors[] = 'Password must be at least 6 characters.';
    if ($new1 !== $new2)   $errors[] = 'Passwords do not match.';

    if (empty($errors)) {
        $hash = token_hash($token);

        $stmt = mysqli_prepare($conn,
            "SELECT email, created_at
             FROM password_reset_tokens
             WHERE email = ? AND token = ?
             AND created_at >= (NOW() - INTERVAL ? MINUTE)
             LIMIT 1"
        );
        mysqli_stmt_bind_param($stmt, "ssi", $email, $hash, $TOKEN_TTL_MINUTES);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);

        if (!$row) {
            $errors[] = 'This reset link is invalid or has expired.';
        } else {
            $pwHash = password_hash($new1, PASSWORD_BCRYPT);

            $upd = mysqli_prepare($conn, "UPDATE users SET password_hash = ? WHERE email = ? AND is_active = 1");
            mysqli_stmt_bind_param($upd, "ss", $pwHash, $email);
            mysqli_stmt_execute($upd);
            mysqli_stmt_close($upd);

            $del = mysqli_prepare($conn, "DELETE FROM password_reset_tokens WHERE email = ?");
            mysqli_stmt_bind_param($del, "s", $email);
            mysqli_stmt_execute($del);
            mysqli_stmt_close($del);

            $success = true;
        }
    }
}

$page_title = 'Reset Password — REVIBE';
require_once 'includes/header.php';
?>

<main style="min-height:calc(100vh - 120px);display:flex;align-items:center;justify-content:center;padding:2rem 1rem;">
<div style="background:#fff;border-radius:20px;padding:2.5rem 2.8rem;box-shadow:0 4px 24px rgba(0,0,0,.09);width:100%;max-width:440px;">

    <h1 style="font-size:1.5rem;font-weight:900;color:#1a1a1a;margin:0 0 .3rem;">Set New Password</h1>
    <p style="color:#888;font-size:.88rem;margin:0 0 1.8rem;">Choose a new password for your account</p>

    <?php if (!empty($errors)): ?>
    <div style="background:#fdecea;color:#c62828;padding:.75rem 1rem;border-radius:8px;font-size:.85rem;margin-bottom:1.2rem;">
        <?php foreach ($errors as $e): ?><p style="margin:0 0 2px;">• <?= htmlspecialchars($e) ?></p><?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div style="background:#ecfdf5;color:#065f46;padding:.75rem 1rem;border-radius:8px;font-size:.85rem;margin-bottom:1.2rem;">
        Your password has been updated. You can sign in now.
    </div>
    <a href="/revibe/signin.php"
       style="display:inline-block;width:100%;text-align:center;padding:.9rem;background:#1a1a1a;color:#c8f04a;border-radius:12px;font-size:.95rem;font-weight:700;text-decoration:none;letter-spacing:.5px;">
        Go to Sign In
    </a>
    <?php else: ?>
    <form method="POST">
        <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

        <div style="margin-bottom:1rem;">
            <label style="display:block;font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#444;margin-bottom:.35rem;">New Password</label>
            <input type="password" name="new_password" required
                   placeholder="Enter a new password"
                   style="width:100%;padding:.7rem .9rem;border:1.5px solid #ddd;border-radius:8px;font-size:.95rem;font-family:inherit;outline:none;box-sizing:border-box;"
                   onfocus="this.style.borderColor='#c8f04a'" onblur="this.style.borderColor='#ddd'">
        </div>

        <div style="margin-bottom:1.5rem;">
            <label style="display:block;font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#444;margin-bottom:.35rem;">Confirm Password</label>
            <input type="password" name="confirm_password" required
                   placeholder="Confirm new password"
                   style="width:100%;padding:.7rem .9rem;border:1.5px solid #ddd;border-radius:8px;font-size:.95rem;font-family:inherit;outline:none;box-sizing:border-box;"
                   onfocus="this.style.borderColor='#c8f04a'" onblur="this.style.borderColor='#ddd'">
        </div>

        <button type="submit"
                style="width:100%;padding:.9rem;background:#1a1a1a;color:#c8f04a;border:none;border-radius:12px;font-size:.95rem;font-weight:700;cursor:pointer;font-family:inherit;letter-spacing:.5px;">
            Update Password
        </button>
    </form>

    <p style="text-align:center;font-size:.85rem;color:#888;margin:1.4rem 0 0;">
        <a href="/revibe/signin.php" style="color:#1a1a1a;font-weight:700;text-decoration:none;">Back to Sign In</a>
    </p>
    <?php endif; ?>
</div>
</main>

<?php require_once 'includes/footer.php'; ?>

