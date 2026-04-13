<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json');

include '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$me = (int)$_SESSION['user_id'];

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_notifications': getNotifications(); break;
    case 'get_badge_count':   getBadgeCount();    break;
    case 'mark_read':         markRead();         break;
    case 'mark_all_read':     markAllRead();      break;
    default: echo json_encode(['error' => 'Unknown action']);
}

mysqli_close($conn);

function getNotifications() {
    global $conn, $me;

    $type   = $_GET['type'] ?? 'all';
    $limit  = max(1, min(50, (int)($_GET['limit'] ?? 50)));
    $offset = max(0, (int)($_GET['offset'] ?? 0));

    if ($type !== 'all') {
        $stmt = mysqli_prepare($conn,
            "SELECT id, user_id, type, title, body, link, is_read, created_at
             FROM notifications
             WHERE user_id = ? AND type = ?
             ORDER BY created_at DESC
             LIMIT ? OFFSET ?"
        );
        mysqli_stmt_bind_param($stmt, "isii", $me, $type, $limit, $offset);
    } else {
        $stmt = mysqli_prepare($conn,
            "SELECT id, user_id, type, title, body, link, is_read, created_at
             FROM notifications
             WHERE user_id = ?
             ORDER BY created_at DESC
             LIMIT ? OFFSET ?"
        );
        mysqli_stmt_bind_param($stmt, "iii", $me, $limit, $offset);
    }

    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
    mysqli_stmt_close($stmt);

    if ($type !== 'all') {
        $cstmt = mysqli_prepare($conn, "SELECT COUNT(*) AS c FROM notifications WHERE user_id = ? AND type = ?");
        mysqli_stmt_bind_param($cstmt, "is", $me, $type);
    } else {
        $cstmt = mysqli_prepare($conn, "SELECT COUNT(*) AS c FROM notifications WHERE user_id = ?");
        mysqli_stmt_bind_param($cstmt, "i", $me);
    }
    mysqli_stmt_execute($cstmt);
    $cres = mysqli_stmt_get_result($cstmt);
    $total = (int)(mysqli_fetch_assoc($cres)['c'] ?? 0);
    mysqli_stmt_close($cstmt);

    echo json_encode(['notifications' => $rows, 'total' => $total]);
}

function getBadgeCount() {
    global $conn, $me;
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS c FROM notifications WHERE user_id = ? AND is_read = 0");
    mysqli_stmt_bind_param($stmt, "i", $me);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $c = (int)(mysqli_fetch_assoc($res)['c'] ?? 0);
    mysqli_stmt_close($stmt);
    echo json_encode(['count' => $c]);
}

function markRead() {
    global $conn, $me;
    $id = (int)($_POST['id'] ?? 0);
    $stmt = mysqli_prepare($conn, "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $id, $me);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    echo json_encode(['success' => true]);
}

function markAllRead() {
    global $conn, $me;
    $stmt = mysqli_prepare($conn, "UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $me);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    echo json_encode(['success' => true]);
}
