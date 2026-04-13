<?php
require_once '../includes/db.php';
require_once 'includes/admin_auth.php';

// Logout
adminLogout();
header('Location: /revibe/admin/signin.php');
exit;
?>
