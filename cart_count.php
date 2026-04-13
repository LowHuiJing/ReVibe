<?php
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['cart_session'])) {
    echo 0;
    exit;
}

$sid = mysqli_real_escape_string($conn, $_SESSION['cart_session']);
$stmt = mysqli_prepare($conn, "SELECT SUM(quantity) as total FROM cart WHERE session_id=?");
mysqli_stmt_bind_param($stmt, "s", $sid);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);
echo (int)($row['total'] ?? 0);