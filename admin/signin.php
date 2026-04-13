<?php
require_once '../includes/db.php';
require_once 'includes/admin_auth.php';

if (isAdminLoggedIn()) {
    header('Location: /revibe/admin/dashboard.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $errors[] = 'Please fill in all fields.';
    } else {
        $result = adminLoginUser($email, $password);
        if ($result['success']) {
            header('Location: /revibe/admin/dashboard.php');
            exit;
        } else {
            $errors[] = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Portal — REVIBE</title>
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #0f1117;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .wrap {
            display: flex;
            width: 100%;
            max-width: 900px;
            min-height: 500px;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 25px 60px rgba(0,0,0,.5);
        }
        /* Left panel */
        .panel-left {
            flex: 1;
            background: #1a1a1a;
            padding: 3rem 2.5rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .brand { display: flex; align-items: center; gap: 10px; margin-bottom: 3rem; }
        .brand-name {
            font-size: 1.6rem; font-weight: 900;
            letter-spacing: 1px; color: #fff;
        }
        .brand-name span { color: #c8f04a; }
        .brand-tag {
            font-size: .6rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: 1.5px; background: #c8f04a; color: #111;
            padding: 3px 7px; border-radius: 4px;
        }
        .panel-left h2 {
            font-size: 1.9rem; font-weight: 900; color: #fff;
            line-height: 1.2; margin-bottom: .75rem;
        }
        .panel-left h2 span { color: #c8f04a; }
        .panel-left p { font-size: .9rem; color: #64748b; line-height: 1.6; }
        .features { margin-top: 2.5rem; display: flex; flex-direction: column; gap: .75rem; }
        .feature {
            display: flex; align-items: center; gap: .75rem;
            font-size: .85rem; color: #94a3b8;
        }
        .feature-dot {
            width: 8px; height: 8px; border-radius: 50%;
            background: #c8f04a; flex-shrink: 0;
        }
        /* Right panel */
        .panel-right {
            width: 400px;
            background: #fff;
            padding: 3rem 2.5rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .panel-right h3 {
            font-size: 1.3rem; font-weight: 800;
            color: #0f172a; margin-bottom: .3rem;
        }
        .panel-right .sub { font-size: .85rem; color: #94a3b8; margin-bottom: 2rem; }
        .error-box {
            background: #fef2f2; color: #b91c1c;
            border: 1px solid #fecaca;
            padding: .75rem 1rem; border-radius: 8px;
            margin-bottom: 1.2rem; font-size: .83rem;
        }
        .error-box p { margin: 2px 0; }
        .form-group { margin-bottom: 1.1rem; }
        label {
            display: block; font-size: .72rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: .6px;
            color: #475569; margin-bottom: .4rem;
        }
        input[type="email"], input[type="password"] {
            width: 100%; padding: .75rem 1rem;
            border: 1.5px solid #e2e8f0; border-radius: 10px;
            font-size: .9rem; font-family: inherit; color: #0f172a;
            outline: none; transition: border-color .15s, box-shadow .15s;
            background: #f8fafc;
        }
        input:focus {
            border-color: #c8f04a;
            box-shadow: 0 0 0 3px rgba(200,240,74,.2);
            background: #fff;
        }
        .submit-btn {
            width: 100%; padding: .9rem;
            background: #1a1a1a; color: #c8f04a;
            border: none; border-radius: 10px;
            font-size: .9rem; font-weight: 800;
            cursor: pointer; font-family: inherit;
            margin-top: .5rem;
            transition: background .15s, transform .1s;
        }
        .submit-btn:hover { background: #2d2d2d; }
        .submit-btn:active { transform: scale(.98); }
        .back-link {
            display: block; text-align: center;
            margin-top: 1.5rem; color: #94a3b8;
            text-decoration: none; font-size: .8rem;
            transition: color .15s;
        }
        .back-link:hover { color: #1a1a1a; }
        @media (max-width: 640px) {
            .panel-left { display: none; }
            .panel-right { width: 100%; }
        }
    </style>
</head>
<body>
<div class="wrap">
    <!-- LEFT -->
    <div class="panel-left">
        <div class="brand">
            <div class="brand-name">RE<span>VIBE</span></div>
            <div class="brand-tag">Admin</div>
        </div>
        <h2>Manage your<br><span>marketplace</span><br>with ease.</h2>
        <p>Full control over users, products, orders, and platform settings — all in one place.</p>
        <div class="features">
            <div class="feature"><div class="feature-dot"></div>Real-time order & delivery tracking</div>
            <div class="feature"><div class="feature-dot"></div>Product approval & seller management</div>
            <div class="feature"><div class="feature-dot"></div>Revenue analytics & top products</div>
            <div class="feature"><div class="feature-dot"></div>Content moderation & banned words</div>
        </div>
    </div>

    <!-- RIGHT -->
    <div class="panel-right">
        <h3>Admin Portal</h3>
        <p class="sub">Sign in with your administrator credentials</p>

        <?php if (!empty($errors)): ?>
        <div class="error-box">
            <?php foreach ($errors as $e): ?>
                <p>✕ <?= htmlspecialchars($e) ?></p>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                       placeholder="admin@revibe.com" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password"
                       placeholder="••••••••" required>
            </div>
            <button type="submit" class="submit-btn">Sign In →</button>
        </form>

        <a href="/revibe/signin.php" class="back-link">← Back to user portal</a>
    </div>
</div>
</body>
</html>
