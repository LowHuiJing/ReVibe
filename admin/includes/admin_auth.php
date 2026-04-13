<?php
// Admin Authentication Functions
require_once '../includes/db.php';
require_once '../includes/session.php';

function requireAdminLogin() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        header('Location: /revibe/admin/signin.php');
        exit;
    }
}

function isAdminLoggedIn() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    return isset($_SESSION['user_id']) && $_SESSION['role'] === 'admin';
}

function adminLoginUser($email, $password) {
    global $conn;

    $e = mysqli_real_escape_string($conn, trim($email));
    $result = mysqli_query($conn, "SELECT * FROM users WHERE email='$e' AND role='admin' AND is_active=1");

    if (mysqli_num_rows($result) === 0) {
        return ['success' => false, 'message' => 'Invalid admin credentials.'];
    }

    $user = mysqli_fetch_assoc($result);
    $stored = (string)$user['password_hash'];

    $isBcrypt = preg_match('/^\$2[aby]\$/', $stored);
    $isArgon  = strncmp($stored, '$argon2', 6) === 0;

    if ($isBcrypt || $isArgon) {
        if (!password_verify($password, $stored)) {
            return ['success' => false, 'message' => 'Invalid admin credentials.'];
        }
    } else {
        $ALLOW_PLAINTEXT = true;
        if (!$ALLOW_PLAINTEXT || $password !== $stored) {
            return ['success' => false, 'message' => 'Invalid admin credentials.'];
        }
    }

    // Login session
    $_SESSION['user_id']       = $user['id'];
    $_SESSION['username']      = $user['username'];
    $_SESSION['email']         = $user['email'];
    $_SESSION['role']          = $user['role'];
    $_SESSION['last_activity'] = time();

    return ['success' => true, 'message' => 'Admin login successful.'];
}

function adminLogout() {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
?>
