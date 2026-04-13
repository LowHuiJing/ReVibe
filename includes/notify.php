<?php
/**
 * Insert a notification row for a user.
 * type must be one of: message, order, payment, review, offer, product, system
 */
function sendNotification($conn, $user_id, $type, $title, $body, $link = null) {
    if (!$user_id) return;
    $stmt = mysqli_prepare($conn,
        "INSERT INTO notifications (user_id, type, title, body, link) VALUES (?, ?, ?, ?, ?)"
    );
    mysqli_stmt_bind_param($stmt, 'issss', $user_id, $type, $title, $body, $link);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}
