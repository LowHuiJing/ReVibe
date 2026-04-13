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
    case 'get_conversations':  getConversations();  break;
    case 'get_messages':       getMessages();       break;
    case 'send_message':       sendMessage();       break;
    case 'delete_message':     deleteMessage();     break;
    case 'block_user':         blockUser();         break;
    case 'unblock_user':       unblockUser();       break;
    case 'set_away':           setAway();           break;
    case 'upload_image':       uploadImage();       break;
    case 'start_conversation': startConversation(); break;
    case 'get_unread_count':     getUnreadCount();      break;
    default: echo json_encode(['error' => 'Unknown action']);
}

mysqli_close($conn);

// ── Helpers ────────────────────────────────────────────────

function isBlocked($conn, $me, $other) {
    $stmt = mysqli_prepare($conn,
        "SELECT id FROM blocked_users
         WHERE (blocker_id=? AND blocked_id=?) OR (blocker_id=? AND blocked_id=?)
         LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, 'iiii', $me, $other, $other, $me);
    mysqli_stmt_execute($stmt);
    return mysqli_num_rows(mysqli_stmt_get_result($stmt)) > 0;
}

function filterProfanity($conn, $text) {
    $res = mysqli_query($conn, "SELECT word FROM banned_words");
    while ($row = mysqli_fetch_assoc($res)) {
        $word = preg_quote($row['word'], '/');
        if (preg_match('/\b'.$word.'\b/iu', $text)) {
            return ['blocked' => true];
        }
    }
    return ['blocked' => false, 'text' => $text];
}

function createMessageNotification($conn, $receiverId, $senderName, $convId) {
    $title = "New message from $senderName";
    $body  = "You have a new message.";
    $link  = "/revibe/messaging.php?conversation_id=$convId";
    $stmt  = mysqli_prepare($conn,
        "INSERT INTO notifications (user_id, type, title, body, link) VALUES (?, 'message', ?, ?, ?)"
    );
    mysqli_stmt_bind_param($stmt, 'isss', $receiverId, $title, $body, $link);
    mysqli_stmt_execute($stmt);
}

// ── Action handlers ────────────────────────────────────────

function getConversations() {
    global $conn, $me;
    $stmt = mysqli_prepare($conn, "
        SELECT c.*,
               p.name  AS product_title,
               p.price AS product_price,
               (SELECT pi.image_url FROM product_images pi WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1) AS product_image,
NULL AS product_sizes,
               u_b.username AS buyer_name,
               u_s.username AS seller_name
        FROM conversations c
        LEFT JOIN products p  ON c.product_id = p.id
        LEFT JOIN users u_b   ON c.buyer_id   = u_b.id
        LEFT JOIN users u_s   ON c.seller_id  = u_s.id
        WHERE c.buyer_id = ? OR c.seller_id = ?
        ORDER BY c.last_message_at DESC
    ");
    mysqli_stmt_bind_param($stmt, 'ii', $me, $me);
    mysqli_stmt_execute($stmt);
    $rows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

    foreach ($rows as &$r) {
        $r['my_unread']    = ($me == $r['buyer_id']) ? $r['buyer_unread'] : $r['seller_unread'];
        $r['other_id']     = ($me == $r['buyer_id']) ? $r['seller_id']   : $r['buyer_id'];
        $r['other_name']   = ($me == $r['buyer_id']) ? $r['seller_name'] : $r['buyer_name'];
        $r['is_blocked']   = isBlocked($conn, $me, $r['other_id']) ? 1 : 0;
    }
    echo json_encode($rows);
}

function getMessages() {
    global $conn, $me;
    $conv_id = (int)($_GET['conversation_id'] ?? 0);
    if (!$conv_id) { echo json_encode(['error' => 'Missing conversation_id']); return; }

    // Authorize: only participants can view messages
    $chk = mysqli_prepare($conn, "SELECT buyer_id, seller_id FROM conversations WHERE id=? LIMIT 1");
    mysqli_stmt_bind_param($chk, 'i', $conv_id);
    mysqli_stmt_execute($chk);
    $conv = mysqli_fetch_assoc(mysqli_stmt_get_result($chk));
    mysqli_stmt_close($chk);

    if (!$conv || !in_array($me, [(int)$conv['buyer_id'], (int)$conv['seller_id']], true)) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        return;
    }

    // Mark as read for the current user
    if ($me === (int)$conv['buyer_id']) {
        $upd = mysqli_prepare($conn, "UPDATE conversations SET buyer_unread=0 WHERE id=?");
    } else {
        $upd = mysqli_prepare($conn, "UPDATE conversations SET seller_unread=0 WHERE id=?");
    }
    mysqli_stmt_bind_param($upd, 'i', $conv_id);
    mysqli_stmt_execute($upd);
    mysqli_stmt_close($upd);

    $stmt = mysqli_prepare($conn, "
        SELECT m.*, u.username AS sender_name,
               r.body AS reply_body, ru.username AS reply_sender
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        LEFT JOIN messages r ON m.reply_to_id = r.id
        LEFT JOIN users ru ON r.sender_id = ru.id
        WHERE m.conversation_id = ?
        ORDER BY m.created_at ASC
    ");
    mysqli_stmt_bind_param($stmt, 'i', $conv_id);
    mysqli_stmt_execute($stmt);
    $rows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    echo json_encode($rows);
}

function sendMessage() {
    global $conn, $me;
    $conv_id   = (int)($_POST['conversation_id'] ?? 0);
    $body      = trim($_POST['body'] ?? '');
    $reply_to  = (int)($_POST['reply_to_id'] ?? 0) ?: null;
    $image_url = $_POST['image_url'] ?? null;

    if (!$conv_id || (!$body && !$image_url)) {
        echo json_encode(['error' => 'Missing data']); return;
    }

    $chk = mysqli_prepare($conn, "SELECT buyer_id, seller_id FROM conversations WHERE id=?");
    mysqli_stmt_bind_param($chk, 'i', $conv_id);
    mysqli_stmt_execute($chk);
    $conv = mysqli_fetch_assoc(mysqli_stmt_get_result($chk));
    if (!$conv || !in_array($me, [$conv['buyer_id'], $conv['seller_id']])) {
        echo json_encode(['error' => 'Unauthorized']); return;
    }

    $other_id = ($me == $conv['buyer_id']) ? $conv['seller_id'] : $conv['buyer_id'];

    if (isBlocked($conn, $me, $other_id)) {
        echo json_encode(['error' => 'You cannot message this user']); return;
    }

    $away_check = mysqli_prepare($conn, "SELECT username FROM users WHERE id=?");
    mysqli_stmt_bind_param($away_check, 'i', $me);
    mysqli_stmt_execute($away_check);
    $me_user = mysqli_fetch_assoc(mysqli_stmt_get_result($away_check));

    if ($body) {
    $filter = filterProfanity($conn, $body);
    if ($filter['blocked']) {
        echo json_encode(['error' => 'profanity']);
        return;
    }
    $body = $filter['text'];
}

    $stmt = mysqli_prepare($conn,
        "INSERT INTO messages (conversation_id, sender_id, body, image_url, reply_to_id) VALUES (?,?,?,?,?)"
    );
    mysqli_stmt_bind_param($stmt, 'iissi', $conv_id, $me, $body, $image_url, $reply_to);
    mysqli_stmt_execute($stmt);
    $new_id = mysqli_insert_id($conn);

    $preview   = $body ?: '📷 Image';
    $other_col = ($me == $conv['buyer_id']) ? 'seller_unread' : 'buyer_unread';
    $upd = mysqli_prepare($conn,
        "UPDATE conversations SET last_message=?, last_message_at=NOW(), $other_col=$other_col+1 WHERE id=?"
    );
    mysqli_stmt_bind_param($upd, 'si', $preview, $conv_id);
    mysqli_stmt_execute($upd);

    createMessageNotification($conn, $other_id, $me_user['username'], $conv_id);

    echo json_encode(['success' => true, 'message_id' => $new_id]);
}

function deleteMessage() {
    global $conn, $me;
    $msg_id = (int)($_POST['message_id'] ?? 0);
    
    // Get conversation_id first
    $r = mysqli_fetch_assoc(mysqli_query($conn, "SELECT conversation_id FROM messages WHERE id=$msg_id AND sender_id=$me"));
    
    // Mark deleted
    $stmt = mysqli_prepare($conn, "UPDATE messages SET is_deleted=1 WHERE id=? AND sender_id=?");
    mysqli_stmt_bind_param($stmt, 'ii', $msg_id, $me);
    mysqli_stmt_execute($stmt);

    if ($r && mysqli_stmt_affected_rows($stmt) > 0) {
        // Update conversation preview with last non-deleted message
        $conv_id = $r['conversation_id'];
        $last = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT body, image_url FROM messages 
             WHERE conversation_id=$conv_id AND is_deleted=0 
             ORDER BY created_at DESC LIMIT 1"
        ));
        $preview = $last ? ($last['body'] ?: '📷 Image') : 'No messages yet';
        $esc = mysqli_real_escape_string($conn, $preview);
        mysqli_query($conn, "UPDATE conversations SET last_message='$esc' WHERE id=$conv_id");
    }

    echo json_encode(['success' => mysqli_stmt_affected_rows($stmt) > 0]);
}

function blockUser() {
    global $conn, $me;
    $target = (int)($_POST['user_id'] ?? 0);
    $stmt = mysqli_prepare($conn, "INSERT IGNORE INTO blocked_users (blocker_id, blocked_id) VALUES (?,?)");
    mysqli_stmt_bind_param($stmt, 'ii', $me, $target);
    mysqli_stmt_execute($stmt);
    echo json_encode(['success' => true]);
}

function unblockUser() {
    global $conn, $me;
    $target = (int)($_POST['user_id'] ?? 0);
    $stmt = mysqli_prepare($conn, "DELETE FROM blocked_users WHERE blocker_id=? AND blocked_id=?");
    mysqli_stmt_bind_param($stmt, 'ii', $me, $target);
    mysqli_stmt_execute($stmt);
    echo json_encode(['success' => true]);
}

function setAway() {
    global $conn, $me;
    $is_away = (int)($_POST['is_away'] ?? 0);
    $msg     = trim($_POST['away_message'] ?? '');
    // Store in session for now (no away columns in users table)
    $_SESSION['is_away']      = $is_away;
    $_SESSION['away_message'] = $msg;
    echo json_encode(['success' => true]);
}

function uploadImage() {
    global $me;
    if (!isset($_FILES['image'])) { echo json_encode(['error' => 'No file']); return; }

    $upload_dir = __DIR__ . '/../images/messages/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

    $ext     = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','gif','webp'];
    if (!in_array($ext, $allowed)) { echo json_encode(['error' => 'Invalid type']); return; }

    $filename = 'msg_' . $me . '_' . time() . '.' . $ext;
    if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $filename)) {
        echo json_encode(['success' => true, 'url' => '/revibe/images/messages/' . $filename]);
    } else {
        echo json_encode(['error' => 'Upload failed']);
    }
}

function startConversation() {
    global $conn, $me;
    $product_id = (int)($_POST['product_id'] ?? 0);
    $seller_id  = (int)($_POST['seller_id']  ?? 0);

    if (!$seller_id || $seller_id == $me) {
        echo json_encode(['error' => 'Invalid seller']); return;
    }

    $find = mysqli_prepare($conn,
        "SELECT id FROM conversations WHERE product_id=? AND buyer_id=? AND seller_id=?"
    );
    mysqli_stmt_bind_param($find, 'iii', $product_id, $me, $seller_id);
    mysqli_stmt_execute($find);
    $existing = mysqli_fetch_assoc(mysqli_stmt_get_result($find));

    if ($existing) {
        echo json_encode(['conversation_id' => $existing['id']]);
    } else {
        $ins = mysqli_prepare($conn,
            "INSERT INTO conversations (product_id, buyer_id, seller_id, last_message_at) VALUES (?,?,?,NOW())"
        );
        mysqli_stmt_bind_param($ins, 'iii', $product_id, $me, $seller_id);
        mysqli_stmt_execute($ins);
        echo json_encode(['conversation_id' => mysqli_insert_id($conn)]);
    }
}
function getUnreadCount() {
    global $conn, $me;
    $result = mysqli_query($conn, "
        SELECT SUM(CASE WHEN buyer_id=$me THEN buyer_unread ELSE seller_unread END) AS total
        FROM conversations
        WHERE buyer_id=$me OR seller_id=$me
    ");
    $row = mysqli_fetch_assoc($result);
    echo json_encode(['unread' => (int)($row['total'] ?? 0)]);
}
