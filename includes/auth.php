<?php
// Auth functions — requires $conn from includes/db.php

function registerUser($username, $email, $password) {
    global $conn;

    $u = mysqli_real_escape_string($conn, $username);
    $e = mysqli_real_escape_string($conn, $email);

    if (mysqli_num_rows(mysqli_query($conn, "SELECT id FROM users WHERE username='$u'")) > 0)
        return ['success' => false, 'message' => 'Username is already taken.'];

    if (mysqli_num_rows(mysqli_query($conn, "SELECT id FROM users WHERE email='$e'")) > 0)
        return ['success' => false, 'message' => 'Email is already registered.'];

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $ok   = mysqli_query($conn,
        "INSERT INTO users (username, email, password_hash) VALUES ('$u', '$e', '$hash')"
    );

    if ($ok) {
        // Create empty profile and settings rows
        $uid = mysqli_insert_id($conn);
        mysqli_query($conn, "INSERT IGNORE INTO user_profiles (user_id) VALUES ($uid)");
        mysqli_query($conn, "INSERT IGNORE INTO user_settings (user_id) VALUES ($uid)");
        return ['success' => true, 'message' => 'Account created successfully.'];
    }
    return ['success' => false, 'message' => 'Registration failed. Please try again.'];
}

function validateRegistration($username, $email, $password, $confirm) {
    $errors = [];
    if (empty($username) || strlen($username) < 3)
        $errors[] = 'Username must be at least 3 characters.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors[] = 'Please enter a valid email address.';
    if (strlen($password) < 8)
        $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm)
        $errors[] = 'Passwords do not match.';
    return $errors;
}

function loginUser($email, $password) {
    global $conn;

    $e = mysqli_real_escape_string($conn, trim($email));
    $result = mysqli_query($conn, "SELECT * FROM users WHERE email='$e' AND is_active=1");

    if (mysqli_num_rows($result) === 0) {
        return ['success' => false, 'message' => 'Invalid email or password.'];
    }

    $user = mysqli_fetch_assoc($result);
    $stored = (string)$user['password_hash'];

    $isBcrypt = preg_match('/^\$2[aby]\$/', $stored);
    $isArgon  = strncmp($stored, '$argon2', 6) === 0;

    if ($isBcrypt || $isArgon) {
        if (!password_verify($password, $stored)) {
            return ['success' => false, 'message' => 'Invalid email or password.'];
        }
    } else {
        // Plaintext/dummy mode (DEV only)
        $ALLOW_PLAINTEXT = true;
        if (!$ALLOW_PLAINTEXT || $password !== $stored) {
            return ['success' => false, 'message' => 'Invalid email or password.'];
        }
    }

    loginSession($user);
    return ['success' => true, 'message' => 'Login successful.'];
}


function changePassword($user_id, $current_password, $new_password) {
    global $conn;
    $result = mysqli_query($conn, "SELECT password_hash FROM users WHERE id=$user_id");
    $user   = mysqli_fetch_assoc($result);
    if (!password_verify($current_password, $user['password_hash']))
        return ['success' => false, 'message' => 'Current password is incorrect.'];

    $hash = password_hash($new_password, PASSWORD_BCRYPT);
    $ok   = mysqli_query($conn, "UPDATE users SET password_hash='$hash' WHERE id=$user_id");
    return $ok
        ? ['success' => true,  'message' => 'Password changed successfully.']
        : ['success' => false, 'message' => 'Failed to change password.'];
}
