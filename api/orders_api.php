<?php
session_start();
header('Content-Type: application/json');
include '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];

$result = mysqli_query($conn,
    "SELECT o.id, o.status,
            CASE WHEN o.status IN ('cancelled','refunded') THEN o.status
                 ELSE COALESCE(d.status, o.status) END AS display_status
     FROM orders o
     LEFT JOIN deliveries d ON d.order_id = o.id
     WHERE o.user_id = $user_id
     ORDER BY o.created_at DESC"
);

$statuses = [];
while ($row = mysqli_fetch_assoc($result)) {
    $statuses[$row['id']] = $row['display_status'];
}

echo json_encode($statuses);
