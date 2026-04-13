<?php
require_once 'includes/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['cart_session'])) {
    $_SESSION['cart_session'] = session_id();
}
$sid = $_SESSION['cart_session'];

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pid = (int)$_POST['product_id'];
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle') {
        $check = mysqli_prepare($conn, "SELECT id FROM wishlist WHERE session_id=? AND product_id=?");
        mysqli_stmt_bind_param($check, "si", $sid, $pid);
        mysqli_stmt_execute($check);
        $result = mysqli_stmt_get_result($check);
        mysqli_stmt_close($check);

        if (mysqli_num_rows($result) > 0) {
            $stmt = mysqli_prepare($conn, "DELETE FROM wishlist WHERE session_id=? AND product_id=?");
            mysqli_stmt_bind_param($stmt, "si", $sid, $pid);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            echo json_encode(['wishlisted' => false]);
        } else {
            $stmt = mysqli_prepare($conn, "INSERT INTO wishlist (session_id, product_id) VALUES (?,?)");
            mysqli_stmt_bind_param($stmt, "si", $sid, $pid);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            echo json_encode(['wishlisted' => true]);
        }
    }

    if ($action === 'check') {
        $stmt = mysqli_prepare($conn, "SELECT id FROM wishlist WHERE session_id=? AND product_id=?");
        mysqli_stmt_bind_param($stmt, "si", $sid, $pid);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        mysqli_stmt_close($stmt);
        echo json_encode(['wishlisted' => mysqli_num_rows($result) > 0]);
    }
}