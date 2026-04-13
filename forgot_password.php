<?php
require_once 'includes/db.php';
require_once 'includes/session.php';
require_once 'includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (isLoggedIn()) { header('Location: /revibe/dashboard.php'); exit; }

$errors = [];
$sent = false;
$devResetLink = '';

// Generate reset link and redirect
$SHOW_RESET_LINK = true;
$AUTO_REDIRECT_IF_EXISTS = true;

function token_hash($token) {
    return hash('sha256', $token);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = '⚠️ Please enter a valid email address.';
    } else {
        // If the user exists and is active, create a reset token.
        $stmt = mysqli_prepare($conn, "SELECT email FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);

        if ($row) {
            $sent = true;
            $token = bin2hex(random_bytes(16));
            $hash = token_hash($token);

            // Store hash; overwrite any existing token for this email.
            $del = mysqli_prepare($conn, "DELETE FROM password_reset_tokens WHERE email = ?");
            mysqli_stmt_bind_param($del, "s", $email);
            mysqli_stmt_execute($del);
            mysqli_stmt_close($del);

            $ins = mysqli_prepare($conn, "INSERT INTO password_reset_tokens (email, token, created_at) VALUES (?, ?, NOW())");
            mysqli_stmt_bind_param($ins, "ss", $email, $hash);
            mysqli_stmt_execute($ins);
            mysqli_stmt_close($ins);

            if ($SHOW_RESET_LINK) {
                $devResetLink = "/revibe/reset_password.php?email=" . urlencode($email) . "&token=" . urlencode($token);
            }

            if ($AUTO_REDIRECT_IF_EXISTS) {
                header("Location: /revibe/reset_password.php?email=" . urlencode($email) . "&token=" . urlencode($token));
                exit;
            }
        } else {
            $errors[] = '⚠️ Email not found.';
        }
    }
}

$page_title = 'Forgot Password — REVIBE';
require_once 'includes/header.php';
?>

<main style="min-height:calc(100vh - 120px);display:flex;align-items:center;justify-content:center;padding:2rem 1rem;">
<div style="background:#fff;border-radius:20px;padding:2.5rem 2.8rem;box-shadow:0 4px 24px rgba(0,0,0,.09);width:100%;max-width:440px;">

    <h1 style="font-size:1.5rem;font-weight:900;color:#1a1a1a;margin:0 0 .3rem;">Reset Password</h1>
    <p style="color:#888;font-size:.88rem;margin:0 0 1.8rem;">Enter your email to receive a reset link</p>

    <?php if (!empty($errors)): ?>
    <div style="background:#fdecea;color:#c62828;padding:.75rem 1rem;border-radius:8px;font-size:.85rem;margin-bottom:1.2rem;">
        <?php foreach ($errors as $e): ?><p style="margin:0 0 2px;">• <?= htmlspecialchars($e) ?></p><?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($sent): ?>
    <div style="background:#ecfdf5;color:#065f46;padding:.75rem 1rem;border-radius:8px;font-size:.85rem;margin-bottom:1.2rem;">
        If an account with that email exists, a reset link has been generated.
        <?php if ($devResetLink): ?>
            <div style="margin-top:.6rem;font-size:.82rem;">
                Dev link: <a href="<?= htmlspecialchars($devResetLink) ?>" style="color:#1a1a1a;font-weight:700;text-decoration:none;"><?= htmlspecialchars($devResetLink) ?></a>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <form method="POST">
        <div style="margin-bottom:1.2rem;">
            <label style="display:block;font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#444;margin-bottom:.35rem;">Email Address</label>
            <input type="email" name="email" required
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                   placeholder="Enter your email"
                   style="width:100%;padding:.7rem .9rem;border:1.5px solid #ddd;border-radius:8px;font-size:.95rem;font-family:inherit;outline:none;box-sizing:border-box;"
                   onfocus="this.style.borderColor='#c8f04a'" onblur="this.style.borderColor='#ddd'">
        </div>

        <button type="submit"
                style="width:100%;padding:.9rem;background:#1a1a1a;color:#c8f04a;border:none;border-radius:12px;font-size:.95rem;font-weight:700;cursor:pointer;font-family:inherit;letter-spacing:.5px;">
            Send Reset Link
        </button>
    </form>

    <p style="text-align:center;font-size:.85rem;color:#888;margin:1.4rem 0 0;">
        <a href="/revibe/signin.php" style="color:#1a1a1a;font-weight:700;text-decoration:none;">Back to Sign In</a>
    </p>
</div>
</main>

<?php require_once 'includes/footer.php'; ?>
