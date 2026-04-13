<?php
require_once 'includes/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Add images column if it doesn't exist
mysqli_query($conn, "ALTER TABLE return_requests ADD COLUMN IF NOT EXISTS images TEXT NULL");

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$errors   = [];
$success  = '';
$order    = null;
$items    = [];

if (!$order_id) {
    header('Location: order_status.php');
    exit;
}

$stmt = mysqli_prepare($conn, "SELECT o.*, d.status as delivery_status FROM orders o
                                LEFT JOIN deliveries d ON d.order_id = o.id
                                WHERE o.id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $order_id);
mysqli_stmt_execute($stmt);
$order = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$order || $order['delivery_status'] !== 'delivered') {
    header('Location: order_status.php?order_id=' . $order_id);
    exit;
}

if (isset($_SESSION['user_id']) && $order['user_id'] && $order['user_id'] != $_SESSION['user_id']) {
    header('Location: order_status.php');
    exit;
}

$items_result = mysqli_query($conn,
    "SELECT oi.*,
            (SELECT COUNT(*) FROM return_requests rr WHERE rr.order_item_id = oi.id) as already_requested
     FROM order_items oi WHERE oi.order_id = $order_id"
);
while ($row = mysqli_fetch_assoc($items_result)) $items[] = $row;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_return'])) {
    $item_id       = (int)($_POST['order_item_id'] ?? 0);
    $reason        = mysqli_real_escape_string($conn, $_POST['reason']        ?? '');
    $details       = mysqli_real_escape_string($conn, trim($_POST['details'] ?? ''));
    $refund_method = mysqli_real_escape_string($conn, $_POST['refund_method'] ?? '');

    $valid_reasons = ['defective', 'wrong_item', 'not_as_described', 'changed_mind', 'other'];
    $valid_refunds = ['original_payment'];

    if (!$item_id)                                 $errors[] = 'Please select an item to return.';
    if (!in_array($reason, $valid_reasons))        $errors[] = 'Please select a valid reason.';
    if (!in_array($refund_method, $valid_refunds)) $errors[] = 'Please select a refund method.';

    $chk = mysqli_query($conn, "SELECT id FROM order_items WHERE id=$item_id AND order_id=$order_id");
    if (mysqli_num_rows($chk) === 0) $errors[] = 'Invalid item selected.';

    $dup = mysqli_query($conn, "SELECT id FROM return_requests WHERE order_item_id=$item_id LIMIT 1");
    if (mysqli_num_rows($dup) > 0) $errors[] = 'A return request for this item already exists.';

    // Handle photo uploads — at least one required
    $uploaded_images = [];
    $upload_dir = __DIR__ . '/images/returns/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
    $allowed_ext = ['jpg','jpeg','png','webp'];

    if (empty($_FILES['return_images']['name'][0])) {
        $errors[] = 'Please upload at least one photo of the item.';
    } else {
        foreach ($_FILES['return_images']['name'] as $i => $fname) {
            if ($_FILES['return_images']['error'][$i] !== UPLOAD_ERR_OK) continue;
            $ext = strtolower(pathinfo($fname, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed_ext)) {
                $errors[] = 'Invalid file type: ' . htmlspecialchars($fname) . '. Only JPG, PNG, WEBP allowed.';
                continue;
            }
            if ($_FILES['return_images']['size'][$i] > 5 * 1024 * 1024) {
                $errors[] = htmlspecialchars($fname) . ' exceeds the 5 MB limit.';
                continue;
            }
            $filename = 'return_' . $order_id . '_' . time() . '_' . $i . '.' . $ext;
            if (move_uploaded_file($_FILES['return_images']['tmp_name'][$i], $upload_dir . $filename)) {
                $uploaded_images[] = 'images/returns/' . $filename;
            }
        }
        if (empty($uploaded_images) && empty($errors)) {
            $errors[] = 'Photo upload failed. Please try again.';
        }
    }

    if (empty($errors)) {
        $images_json = json_encode($uploaded_images);
        $stmt = mysqli_prepare($conn,
            "INSERT INTO return_requests (order_id, order_item_id, reason, details, refund_method, status, images)
             VALUES (?, ?, ?, ?, ?, 'pending', ?)"
        );
        mysqli_stmt_bind_param($stmt, 'iissss', $order_id, $item_id, $reason, $details, $refund_method, $images_json);
        if (mysqli_stmt_execute($stmt)) {
            $success = 'Your return request has been submitted. We will process it within 3–5 business days.';

            // Notify the seller of the product
            $seller_q = mysqli_query($conn,
                "SELECT p.seller_id, oi.product_name
                 FROM order_items oi
                 LEFT JOIN products p ON p.id = oi.product_id
                 WHERE oi.id = $item_id LIMIT 1"
            );
            if ($seller_row = mysqli_fetch_assoc($seller_q)) {
                require_once 'includes/notify.php';
                sendNotification($conn, $seller_row['seller_id'], 'order',
                    'Return Request Received',
                    'A buyer has requested a return for "' . $seller_row['product_name'] . '". Please review and respond.',
                    '/revibe/product_management.php'
                );
            }
        } else {
            $errors[] = 'Failed to submit return request. Please try again.';
        }
        mysqli_stmt_close($stmt);
    }
}

$page_title = 'Return Request — REVIBE';
require_once 'includes/header.php';
?>

<style>
body.dark { background: #0f172a; }
body.dark .rr-heading      { color: #e2e8f0 !important; }
body.dark .rr-subheading   { color: #64748b !important; }
body.dark .rr-back         { color: #64748b !important; }
body.dark .rr-card         { background: #1e293b !important; box-shadow: 0 2px 12px rgba(0,0,0,.3) !important; }
body.dark .rr-card h2      { color: #e2e8f0 !important; }
body.dark .rr-label        { color: #94a3b8 !important; }
body.dark .rr-input        { background: #0f172a !important; border-color: #334155 !important; color: #e2e8f0 !important; }
body.dark .rr-input:focus  { border-color: #c8f04a !important; }
body.dark .rr-radio-row    { border-color: #334155 !important; }
body.dark .rr-radio-name   { color: #e2e8f0 !important; }
body.dark .rr-radio-meta   { color: #64748b !important; }
body.dark .rr-already      { color: #f87171 !important; }
body.dark .rr-upload-zone  { border-color: #334155 !important; }
body.dark .rr-upload-text  { color: #e2e8f0 !important; }
body.dark .rr-upload-hint  { color: #64748b !important; }
body.dark .rr-photo-hint   { color: #94a3b8 !important; }
</style>

<main style="max-width:680px;margin:40px auto;padding:0 16px;">

    <div style="margin-bottom:1.5rem;">
        <a href="order_status.php?order_id=<?= $order_id ?>"
           class="rr-back" style="font-size:.82rem;color:#888;text-decoration:none;">← Back to Order #<?= $order_id ?></a>
        <h1 class="rr-heading" style="font-size:1.5rem;font-weight:900;color:#1a1a1a;margin:.5rem 0 4px;">Return / Refund Request</h1>
        <p class="rr-subheading" style="color:#888;font-size:.9rem;margin:0;">Order #<?= $order_id ?></p>
    </div>

    <?php if ($success): ?>
    <div style="background:#d4edda;color:#155724;padding:1.2rem 1.4rem;border-radius:14px;margin-bottom:1.5rem;font-weight:600;">
        <p style="margin:0 0 8px;">✓ <?= htmlspecialchars($success) ?></p>
    </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
    <div style="background:#fdecea;color:#c62828;padding:.9rem 1rem;border-radius:10px;margin-bottom:1.2rem;font-size:.88rem;">
        <?php foreach ($errors as $e): ?><p style="margin:0 0 3px;"><?= htmlspecialchars($e) ?></p><?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="rr-card" style="background:#fff;border-radius:16px;padding:2rem;box-shadow:0 2px 12px rgba(0,0,0,.06);">
        <?php
        $is = 'width:100%;padding:.65rem .9rem;border:1.5px solid #ddd;border-radius:8px;font-size:.95rem;outline:none;box-sizing:border-box;';
        $ls = 'display:block;font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#444;margin-bottom:.3rem;';
        $gs = 'margin-bottom:1.1rem;';
        $h2style = 'font-size:1rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#1a1a1a;margin:0 0 1.2rem;padding-bottom:.5rem;border-bottom:2px solid #c8f04a;display:inline-block;';
        ?>
        <h2 style="<?= $h2style ?>">Submit Return Request</h2>
        <form method="POST" enctype="multipart/form-data">
            <div style="<?= $gs ?>">
                <label class="rr-label" style="<?= $ls ?>">Select Item to Return</label>
                <?php foreach ($items as $item): ?>
                <label class="rr-radio-row" style="display:flex;align-items:center;gap:.8rem;padding:.9rem 1rem;border:1.5px solid #ddd;border-radius:10px;margin-bottom:.6rem;cursor:<?= $item['already_requested'] ? 'not-allowed' : 'pointer' ?>;opacity:<?= $item['already_requested'] ? '.5' : '1' ?>;">
                    <input type="radio" name="order_item_id" value="<?= $item['id'] ?>"
                           <?= $item['already_requested'] ? 'disabled' : '' ?>
                           <?= (isset($_POST['order_item_id']) && $_POST['order_item_id'] == $item['id']) ? 'checked' : '' ?>
                           style="accent-color:#1a1a1a;">
                    <div style="flex:1;">
                        <p class="rr-radio-name" style="font-weight:700;font-size:.9rem;margin:0 0 2px;"><?= htmlspecialchars($item['product_name']) ?></p>
                        <p class="rr-radio-meta" style="font-size:.78rem;color:#888;margin:0;">Qty: <?= $item['quantity'] ?> · RM <?= number_format($item['unit_price'] * $item['quantity'], 2) ?></p>
                        <?php if ($item['already_requested']): ?>
                        <p class="rr-already" style="font-size:.75rem;color:#ef4444;font-weight:600;margin:2px 0 0;">Return already requested</p>
                        <?php endif; ?>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>

            <div style="<?= $gs ?>">
                <label class="rr-label" style="<?= $ls ?>">Reason for Return</label>
                <select name="reason" required class="rr-input" style="<?= $is ?>">
                    <option value="">— Select reason —</option>
                    <?php
                    $reasons = ['defective'=>'Defective / Damaged','wrong_item'=>'Wrong Item Received',
                                'not_as_described'=>'Not As Described','changed_mind'=>'Changed My Mind','other'=>'Other'];
                    $sel = $_POST['reason'] ?? '';
                    foreach ($reasons as $v => $l):
                    ?>
                    <option value="<?= $v ?>" <?= $sel===$v ? 'selected' : '' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="<?= $gs ?>">
                <label class="rr-label" style="<?= $ls ?>">Additional Details (optional)</label>
                <textarea name="details" rows="3" placeholder="Describe the issue..."
                          class="rr-input" style="<?= $is ?>resize:vertical;"><?= htmlspecialchars($_POST['details'] ?? '') ?></textarea>
            </div>

            <div style="<?= $gs ?>">
                <label class="rr-label" style="<?= $ls ?>">Preferred Refund Method</label>
                <select name="refund_method" required class="rr-input" style="<?= $is ?>">
                    <option value="original_payment" selected>Original Payment Method</option>
                </select>
            </div>

            <div style="<?= $gs ?>">
                <label class="rr-label" style="<?= $ls ?>">Photos <span style="color:#ef4444;">*</span></label>
                <p class="rr-photo-hint" style="font-size:.78rem;color:#888;margin:0 0 .6rem;">Upload clear photos showing the issue. At least 1 photo required (JPG, PNG, WEBP · max 5 MB each).</p>
                <label id="photo-label" class="rr-upload-zone" style="display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.5rem;
                       border:2px dashed #ddd;border-radius:10px;padding:1.4rem;cursor:pointer;transition:border-color .2s;"
                       onmouseover="this.style.borderColor='#c8f04a'" onmouseout="this.style.borderColor=''">
                    <span style="font-size:2rem;">📷</span>
                    <span class="rr-upload-text" style="font-size:.85rem;font-weight:700;color:#1a1a1a;">Click to upload photos</span>
                    <span class="rr-upload-hint" style="font-size:.75rem;color:#888;">You can select multiple files</span>
                    <input type="file" name="return_images[]" id="returnImages" accept="image/jpeg,image/png,image/webp"
                           multiple required style="display:none;" onchange="previewReturnImages(this)">
                </label>
                <div id="imagePreviewGrid" style="display:none;margin-top:.8rem;display:flex;flex-wrap:wrap;gap:.5rem;"></div>
            </div>

            <div style="background:#fffbeb;border:1.5px solid #f59e0b;border-radius:10px;padding:.9rem 1rem;margin-bottom:1.4rem;font-size:.82rem;color:#92400e;">
                ⚠️ Returns are accepted within <strong>14 days</strong> of delivery. Items must be in original condition.
            </div>

            <button type="submit" name="submit_return"
                    style="width:100%;padding:1rem;background:#1a1a1a;color:#c8f04a;border:none;border-radius:12px;font-size:.95rem;font-weight:700;cursor:pointer;">
                Submit Return Request
            </button>
        </form>
    </div>
</main>

<script>
function previewReturnImages(input) {
    const grid = document.getElementById('imagePreviewGrid');
    grid.innerHTML = '';
    grid.style.display = 'flex';
    Array.from(input.files).forEach(file => {
        const reader = new FileReader();
        reader.onload = e => {
            const wrap = document.createElement('div');
            wrap.style.cssText = 'position:relative;width:80px;height:80px;border-radius:8px;overflow:hidden;border:1.5px solid #ddd;';
            const img = document.createElement('img');
            img.src = e.target.result;
            img.style.cssText = 'width:100%;height:100%;object-fit:cover;';
            wrap.appendChild(img);
            grid.appendChild(wrap);
        };
        reader.readAsDataURL(file);
    });
    document.getElementById('photo-label').style.borderColor = '#c8f04a';
}
</script>

<?php require_once 'includes/footer.php'; ?>
