<?php
session_start();
header('Content-Type: application/json');
include '../includes/db.php';

$me = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_reviews':    getReviews();    break;
    case 'submit_review':  submitReview();  break;
    case 'upload_image':   uploadImage();   break;
    case 'edit_review': editReview(); break;
    default: echo json_encode(['error' => 'Unknown action']);
}

mysqli_close($conn);

function getReviews() {
    global $conn;
    $product_id = (int)($_GET['product_id'] ?? 0);
    if (!$product_id) { echo json_encode(['error' => 'Missing product_id']); return; }

    $result = mysqli_query($conn, "
        SELECT r.*, u.username AS buyer_name
        FROM reviews r
        JOIN users u ON r.user_id = u.id
        WHERE r.product_id = $product_id
        ORDER BY r.created_at DESC
    ");

    $reviews = mysqli_fetch_all($result, MYSQLI_ASSOC);

    // Get average rating
    $avg = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT AVG(rating) AS avg_rating, COUNT(*) AS total FROM reviews WHERE product_id = $product_id"
    ));

    echo json_encode([
        'reviews'    => $reviews,
        'avg_rating' => round((float)$avg['avg_rating'], 1),
        'total'      => (int)$avg['total']
    ]);
}

function submitReview() {
    global $conn, $me;

    if (!$me) { echo json_encode(['error' => 'You must be signed in to leave a review']); return; }

    $product_id  = (int)($_POST['product_id'] ?? 0);
    $rating      = (int)($_POST['rating']     ?? 0);
    $review_text = trim($_POST['review_text'] ?? '');
    $image_url   = $_POST['image_url'] ?? null;

    if (!$product_id || !$rating || $rating < 1 || $rating > 5) {
        echo json_encode(['error' => 'Missing required fields']); return;
    }

    // Block seller from reviewing their own product
    $own = mysqli_query($conn, "SELECT id FROM products WHERE id=$product_id AND seller_id=$me");
    if (mysqli_num_rows($own) > 0) {
        echo json_encode(['error' => 'You cannot review your own product']); return;
    }

    // Check if already reviewed
    $check = mysqli_query($conn, "SELECT id FROM reviews WHERE product_id=$product_id AND user_id=$me");
    if (mysqli_num_rows($check) > 0) {
        echo json_encode(['error' => 'You have already reviewed this product']); return;
    }

    $stmt = mysqli_prepare($conn,
        "INSERT INTO reviews (product_id, user_id, rating, review_text, image_url) VALUES (?,?,?,?,?)"
    );
    if (!$stmt) { echo json_encode(['error' => 'DB prepare error: ' . mysqli_error($conn)]); return; }
    mysqli_stmt_bind_param($stmt, 'iiiss', $product_id, $me, $rating, $review_text, $image_url);
    if (!mysqli_stmt_execute($stmt)) {
        echo json_encode(['error' => 'Failed to save review: ' . mysqli_stmt_error($stmt)]); return;
    }

    echo json_encode(['success' => true]);
}

function uploadImage() {
    global $me;
    if (!isset($_FILES['image'])) { echo json_encode(['error' => 'No file']); return; }

    $upload_dir = __DIR__ . '/../images/reviews/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

    $ext     = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','gif','webp'];
    if (!in_array($ext, $allowed)) { echo json_encode(['error' => 'Invalid type']); return; }

    $filename = 'review_' . $me . '_' . time() . '.' . $ext;
    if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $filename)) {
        echo json_encode(['success' => true, 'url' => '/revibe/images/reviews/' . $filename]);
    } else {
        echo json_encode(['error' => 'Upload failed']);
    }
}

function editReview() {
    global $conn, $me;

    if (!$me) { echo json_encode(['error' => 'Not authenticated']); return; }

    $review_id   = (int)($_POST['review_id'] ?? 0);
    $review_text = trim($_POST['review_text'] ?? '');

    if (!$review_id || !$review_text) {
        echo json_encode(['error' => 'Missing data']); return;
    }

    $stmt = mysqli_prepare($conn,
        "UPDATE reviews SET review_text=? WHERE id=? AND user_id=?"
    );
    mysqli_stmt_bind_param($stmt, 'sii', $review_text, $review_id, $me);
    mysqli_stmt_execute($stmt);

    if (mysqli_stmt_affected_rows($stmt) > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Not allowed']);
    }
}