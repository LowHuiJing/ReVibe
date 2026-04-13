<?php
// Session timeout in seconds (30 minutes)
define('SESSION_TIMEOUT', 1800);

if (session_status() === PHP_SESSION_NONE) session_start();

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /revibe/signin.php');
        exit;
    }
}

function checkSessionTimeout() {
    if (isset($_SESSION['last_activity'])) {
        if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
            logoutUser();
            header('Location: /revibe/signin.php?timeout=1');
            exit;
        }
    }
    $_SESSION['last_activity'] = time();
}

function loginSession($user) {
    $_SESSION['user_id']       = $user['id'];
    $_SESSION['username']      = $user['username'];
    $_SESSION['email']         = $user['email'];
    $_SESSION['role']          = $user['role'] ?? 'user';
    $_SESSION['last_activity'] = time();
}

function logoutUser() {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
