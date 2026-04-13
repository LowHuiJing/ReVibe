<?php
require_once 'includes/db.php';
require_once 'includes/session.php';
logoutUser();
header('Location: /revibe/signin.php');
exit;
