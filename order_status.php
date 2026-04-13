<?php
require_once 'includes/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();


$order_id     = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$error        = '';
$cancel_success = '';
$review_success = '';
$order        = null;
$delivery     = null;
$items        = [];
$timeline     = [];
$reviewed_products = [];

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review']) && $order_id) {
    if (!isset($_SESSION['user_id'])) {
        $error = 'You must be signed in to leave a review.';
    } else {
        $uid        = (int)$_SESSION['user_id'];
        $product_id = (int)($_POST['review_product_id'] ?? 0);
        $rating     = (int)($_POST['rating']            ?? 0);
        $review_txt = mysqli_real_escape_string($conn, trim($_POST['review_text'] ?? ''));

        if ($rating < 1 || $rating > 5) {
            $error = 'Please select a star rating.';
        } else {
            // Block seller from reviewing their own product
            $self_chk = mysqli_query($conn,
                "SELECT id FROM products WHERE id=$product_id AND seller_id=$uid"
            );
            if (mysqli_num_rows($self_chk) > 0) {
                $error = 'You cannot review your own product.';
            }
        }
        if (empty($error) && $rating >= 1 && $rating <= 5) {
            // Verify buyer owns this order and the product is in it
            $own = mysqli_query($conn,
                "SELECT oi.id FROM orders o
                 JOIN order_items oi ON oi.order_id = o.id
                 WHERE o.id = $order_id AND o.user_id = $uid AND oi.product_id = $product_id LIMIT 1"
            );
            if (mysqli_num_rows($own) === 0) {
                $error = 'You cannot review this item.';
            } else {
                $dup = mysqli_query($conn,
                    "SELECT id FROM reviews WHERE product_id=$product_id AND user_id=$uid LIMIT 1"
                );
                if (mysqli_num_rows($dup) > 0) {
                    $error = 'You have already reviewed this item.';
                } else {
                    $ins = mysqli_prepare($conn,
                        "INSERT INTO reviews (product_id, user_id, rating, review_text) VALUES (?, ?, ?, ?)"
                    );
                    mysqli_stmt_bind_param($ins, 'iiis', $product_id, $uid, $rating, $review_txt);
                    if (mysqli_stmt_execute($ins)) {
                        $review_success = 'Review submitted!';
                        $reviewed_products[$product_id] = true;
                    } else {
                        $error = 'Failed to submit review: ' . mysqli_error($conn);
                    }
                    mysqli_stmt_close($ins);
                }
            }
        }
    }
}

// Handle seller marking order as packed
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_packed']) && $order_id) {
    if (!isset($_SESSION['user_id'])) {
        $error = 'You must be signed in.';
    } else {
        $uid = (int)$_SESSION['user_id'];
        // Verify caller is the seller of at least one item in this order
        $sel_chk = mysqli_prepare($conn,
            "SELECT oi.id FROM order_items oi
             JOIN products p ON p.id = oi.product_id
             WHERE oi.order_id = ? AND p.seller_id = ? LIMIT 1"
        );
        mysqli_stmt_bind_param($sel_chk, 'ii', $order_id, $uid);
        mysqli_stmt_execute($sel_chk);
        $is_seller_row = mysqli_fetch_assoc(mysqli_stmt_get_result($sel_chk));
        mysqli_stmt_close($sel_chk);

        if (!$is_seller_row) {
            $error = 'Access denied.';
        } else {
            $del_chk = mysqli_prepare($conn,
                "SELECT id, status FROM deliveries WHERE order_id = ? LIMIT 1"
            );
            mysqli_stmt_bind_param($del_chk, 'i', $order_id);
            mysqli_stmt_execute($del_chk);
            $del_row = mysqli_fetch_assoc(mysqli_stmt_get_result($del_chk));
            mysqli_stmt_close($del_chk);

            if (!$del_row) {
                $error = 'No delivery record found for this order.';
            } elseif ($del_row['status'] !== 'pending') {
                $error = 'Order can only be marked as packed when status is pending.';
            } else {
                mysqli_query($conn,
                    "UPDATE deliveries SET status='packed' WHERE id=" . (int)$del_row['id']
                );
                mysqli_query($conn,
                    "UPDATE orders SET status='confirmed' WHERE id=$order_id"
                );
            }
        }
    }
}

// Handle cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order']) && $order_id) {
    if (!isset($_SESSION['user_id'])) {
        $error = 'You must be signed in to cancel an order.';
    } else {
        $uid = (int)$_SESSION['user_id'];
        // Verify ownership and that delivery is still pending (before packed)
        $chk = mysqli_prepare($conn,
            "SELECT o.id, o.status, d.status AS delivery_status
             FROM orders o
             LEFT JOIN deliveries d ON d.order_id = o.id
             WHERE o.id = ? AND o.user_id = ?"
        );
        mysqli_stmt_bind_param($chk, 'ii', $order_id, $uid);
        mysqli_stmt_execute($chk);
        $chk_row = mysqli_fetch_assoc(mysqli_stmt_get_result($chk));
        mysqli_stmt_close($chk);

        if (!$chk_row) {
            $error = 'Order not found or access denied.';
        } elseif (in_array($chk_row['status'], ['cancelled', 'delivered', 'refunded'])) {
            $error = 'This order cannot be cancelled.';
        } elseif (!in_array($chk_row['delivery_status'] ?? 'pending', ['pending', null])) {
            $error = 'This order can no longer be cancelled — it has already been packed.';
        } else {
            mysqli_query($conn, "UPDATE orders SET status='cancelled' WHERE id=$order_id");
            mysqli_query($conn, "UPDATE deliveries SET status='cancelled' WHERE order_id=$order_id");

            // Restore stock for each item
            $restore_q = mysqli_query($conn,
                "SELECT product_id, quantity FROM order_items WHERE order_id=$order_id"
            );
            while ($ri = mysqli_fetch_assoc($restore_q)) {
                $pid = (int)$ri['product_id'];
                $qty = (int)$ri['quantity'];
                mysqli_query($conn,
                    "UPDATE products
                     SET stock_quantity = stock_quantity + $qty,
                         status = CASE WHEN status = 'sold_out' THEN 'approved' ELSE status END
                     WHERE id = $pid"
                );
            }

            $cancel_success = 'Your order has been cancelled successfully.';
        }
    }
}

if ($order_id) {
    $stmt = mysqli_prepare($conn,
        "SELECT o.*, p.payment_method, p.transaction_ref, p.paid_at
         FROM orders o
         LEFT JOIN payments p ON p.order_id = o.id
         WHERE o.id = ?"
    );
    mysqli_stmt_bind_param($stmt, 'i', $order_id);
    mysqli_stmt_execute($stmt);
    $order = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$order) {
        $error = 'Order not found. Please check your order ID.';
    } else {
        $is_seller = false;
        if (isset($_SESSION['user_id']) && $order['user_id'] != $_SESSION['user_id']) {
            // Check if they are the seller of items in this order
            $sc = mysqli_prepare($conn,
                "SELECT oi.id FROM order_items oi
                 JOIN products p ON p.id = oi.product_id
                 WHERE oi.order_id = ? AND p.seller_id = ? LIMIT 1"
            );
            $session_uid = (int)$_SESSION['user_id'];
            mysqli_stmt_bind_param($sc, 'ii', $order_id, $session_uid);
            mysqli_stmt_execute($sc);
            $is_seller = (bool)mysqli_fetch_assoc(mysqli_stmt_get_result($sc));
            mysqli_stmt_close($sc);
            if (!$is_seller) {
                $error = 'You do not have permission to view this order.';
                $order = null;
            }
        }
        if ($order) {
            $stmt = mysqli_prepare($conn,
                "SELECT oi.*,
                        (SELECT image_url FROM product_images pi
                         WHERE pi.product_id = oi.product_id AND pi.is_primary = 1 LIMIT 1) AS image
                 FROM order_items oi WHERE oi.order_id = ?"
            );
            mysqli_stmt_bind_param($stmt, 'i', $order_id);
            mysqli_stmt_execute($stmt);
            $items_result = mysqli_stmt_get_result($stmt);
            while ($row = mysqli_fetch_assoc($items_result)) $items[] = $row;
            mysqli_stmt_close($stmt);

            // Load already-reviewed products (any order)
            if (isset($_SESSION['user_id']) && !empty($items)) {
                $uid_r   = (int)$_SESSION['user_id'];
                $pids_in = implode(',', array_unique(array_map(fn($r) => (int)$r['product_id'], $items)));
                $rev_q   = mysqli_query($conn,
                    "SELECT product_id FROM reviews WHERE user_id=$uid_r AND product_id IN ($pids_in)"
                );
                while ($rr = mysqli_fetch_assoc($rev_q)) {
                    $reviewed_products[(int)$rr['product_id']] = true;
                }
            }

            $stmt = mysqli_prepare($conn, "SELECT * FROM deliveries WHERE order_id = ? LIMIT 1");
            mysqli_stmt_bind_param($stmt, 'i', $order_id);
            mysqli_stmt_execute($stmt);
            $delivery = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);

            $statuses   = ['pending', 'packed', 'shipped', 'out_for_delivery', 'delivered'];
            $labels     = ['Order Placed', 'Packed', 'Shipped', 'Out for Delivery', 'Delivered'];
            $icons      = ['📦', '🏷️', '🚚', '🛵', '✅'];
            $cur_status = $delivery['status'] ?? $order['status'] ?? 'pending';
            $cur_index  = array_search($cur_status, $statuses);
            if ($cur_index === false) $cur_index = 0; // cancelled / refunded: mark only first step
            foreach ($statuses as $i => $s) {
                $timeline[] = [
                    'status'    => $s,
                    'label'     => $labels[$i],
                    'icon'      => $icons[$i],
                    'completed' => $i <= $cur_index,
                    'active'    => $i === $cur_index,
                ];
            }
        }
    }
}

$page_title = 'Order Status — REVIBE';
require_once 'includes/header.php';
?>

<style>
body.dark { background: #0f172a; }
body.dark .os-heading       { color: #e2e8f0 !important; }
body.dark .os-subheading    { color: #64748b !important; }
body.dark .os-card          { background: #1e293b !important; box-shadow: 0 2px 12px rgba(0,0,0,.3) !important; }
body.dark .os-card h2,
body.dark .os-card h3       { color: #e2e8f0 !important; }
body.dark .os-card-label    { color: #64748b !important; }
body.dark .os-card-text     { color: #94a3b8 !important; }
body.dark .os-card-strong   { color: #e2e8f0 !important; }
body.dark .os-item-border   { border-color: #334155 !important; }
body.dark .os-item-img      { background: #0f172a !important; }
body.dark .os-item-name     { color: #e2e8f0 !important; }
body.dark .os-item-meta     { color: #64748b !important; }
body.dark .os-item-price    { color: #e2e8f0 !important; }
body.dark .os-total         { color: #e2e8f0 !important; }
body.dark .os-timeline-track{ background: #334155 !important; }
body.dark .os-step-inactive { background: #334155 !important; border-color: #334155 !important; }
body.dark .os-step-label    { color: #64748b !important; }
body.dark .os-lookup-input  { background: #0f172a !important; border-color: #334155 !important; color: #e2e8f0 !important; }
body.dark .os-lookup-label  { color: #94a3b8 !important; }
body.dark .os-lookup-h2     { color: #e2e8f0 !important; }
body.dark .os-est-strong    { color: #e2e8f0 !important; }
/* Review section */
body.dark .os-review-item   { border-bottom-color: #334155 !important; }
body.dark .os-review-name   { color: #e2e8f0 !important; }
body.dark .os-review-meta   { color: #64748b !important; }
body.dark .os-review-input  { background: #0f172a !important; border-color: #334155 !important; color: #e2e8f0 !important; }
body.dark .os-reviewed-badge{ background: #1e3a2e !important; color: #86efac !important; }
/* Star rating */
.star-rating { display: flex; flex-direction: row-reverse; justify-content: flex-end; gap: 4px; }
.star-rating input { display: none; }
.star-rating label { font-size: 1.6rem; cursor: pointer; color: #ddd; transition: color .15s; }
.star-rating input:checked ~ label,
.star-rating label:hover,
.star-rating label:hover ~ label { color: #f59e0b; }
body.dark .star-rating label { color: #475569; }
body.dark .star-rating input:checked ~ label,
body.dark .star-rating label:hover,
body.dark .star-rating label:hover ~ label { color: #f59e0b; }
</style>

<main style="max-width:820px;margin:40px auto;padding:0 16px;">

    <div style="margin-bottom:1.5rem;">
        <h1 class="os-heading" style="font-size:1.5rem;font-weight:900;color:#1a1a1a;margin:0 0 4px;">Order Tracking</h1>
        <p class="os-subheading" style="color:#888;font-size:.9rem;margin:0;">Track your order status and delivery</p>
    </div>

    <?php if (!$order_id || $error): ?>
    <div class="os-card" style="background:#fff;border-radius:16px;padding:2rem;box-shadow:0 2px 12px rgba(0,0,0,.06);">
        <?php if ($error): ?>
        <div style="background:#fdecea;color:#c62828;padding:.8rem 1rem;border-radius:10px;margin-bottom:1.2rem;font-size:.88rem;">
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>
        <h2 class="os-lookup-h2" style="font-size:1rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;margin:0 0 1.2rem;">Look Up Your Order</h2>
        <form method="GET" style="display:flex;gap:.8rem;align-items:flex-end;flex-wrap:wrap;">
            <div style="flex:1;min-width:200px;">
                <label class="os-lookup-label" style="display:block;font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#444;margin-bottom:.3rem;">Order ID</label>
                <input type="number" name="order_id" placeholder="e.g. 42" min="1"
                       class="os-lookup-input" style="width:100%;padding:.65rem .9rem;border:1.5px solid #ddd;border-radius:8px;font-size:.95rem;outline:none;box-sizing:border-box;"
                       onfocus="this.style.borderColor='#c8f04a'" onblur="this.style.borderColor=''">
            </div>
            <button type="submit"
                    style="padding:.72rem 1.5rem;background:#1a1a1a;color:#c8f04a;border:none;border-radius:10px;font-size:.9rem;font-weight:700;cursor:pointer;">
                Track Order
            </button>
        </form>
    </div>

    <?php else: ?>
    <?php if ($cancel_success): ?>
    <div style="background:#dcfce7;color:#166534;border-radius:16px;padding:1rem 1.8rem;margin-bottom:1.5rem;display:flex;align-items:center;gap:.8rem;">
        <span style="font-size:1.4rem;">✓</span>
        <p style="margin:0;font-weight:700;font-size:.92rem;"><?= htmlspecialchars($cancel_success) ?></p>
    </div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div style="background:#fdecea;color:#c62828;border-radius:16px;padding:1rem 1.8rem;margin-bottom:1.5rem;font-size:.88rem;">
        <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>
    <?php if (isset($_GET['paid'])): ?>
    <div style="background:#c8f04a;color:#1a1a1a;border-radius:16px;padding:1.2rem 1.8rem;margin-bottom:1.5rem;display:flex;align-items:center;gap:1rem;">
        <span style="font-size:2rem;">✓</span>
        <div>
            <p style="font-weight:900;font-size:1rem;margin:0 0 2px;">Payment Successful!</p>
            <p style="font-size:.88rem;margin:0;">Your order has been placed. Track its progress below.</p>
        </div>
    </div>
    <?php endif; ?>

    <div style="background:#1a1a1a;color:#fff;border-radius:16px;padding:1.6rem 2rem;margin-bottom:1.5rem;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;">
        <div>
            <p style="color:#888;font-size:.78rem;text-transform:uppercase;letter-spacing:.5px;margin:0 0 3px;">Order</p>
            <p style="font-size:1.4rem;font-weight:900;color:#c8f04a;margin:0;">#<?= $order['id'] ?></p>
            <p style="font-size:.82rem;color:#94a3b8;margin:3px 0 0;"><?= date('d M Y, h:i A', strtotime($order['created_at'])) ?></p>
        </div>
        <div style="text-align:right;">
            <p style="color:#888;font-size:.78rem;text-transform:uppercase;letter-spacing:.5px;margin:0 0 3px;">Total</p>
            <p style="font-size:1.3rem;font-weight:900;color:#c8f04a;margin:0;">RM <?= number_format($order['total_amount'], 2) ?></p>
        </div>
    </div>

    <?php if (in_array($order['status'], ['cancelled', 'refunded'])): ?>
    <div style="background:<?= $order['status']==='cancelled' ? '#fdecea' : '#ede9fe' ?>;color:<?= $order['status']==='cancelled' ? '#c62828' : '#5b21b6' ?>;border-radius:16px;padding:1.1rem 1.8rem;margin-bottom:1.5rem;display:flex;align-items:center;gap:.8rem;">
        <span style="font-size:1.4rem;"><?= $order['status']==='cancelled' ? '✕' : '↩' ?></span>
        <p style="margin:0;font-weight:700;font-size:.92rem;">
            This order has been <strong><?= ucfirst($order['status']) ?></strong>.
            <?= $order['status']==='refunded' ? 'Your refund is being processed.' : '' ?>
        </p>
    </div>
    <?php endif; ?>

    <?php if ($delivery && !in_array($order['status'], ['cancelled', 'refunded'])): ?>
    <div class="os-card" style="background:#fff;border-radius:16px;padding:1.8rem;box-shadow:0 2px 12px rgba(0,0,0,.06);margin-bottom:1.5rem;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.6rem;flex-wrap:wrap;gap:.8rem;">
            <h2 style="font-size:1rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#1a1a1a;margin:0;padding-bottom:.5rem;border-bottom:2px solid #c8f04a;">
                Delivery Status
            </h2>
            <div>
                <span style="font-size:.8rem;color:#888;">Tracking: </span>
                <span style="font-weight:700;font-family:monospace;font-size:.88rem;"><?= htmlspecialchars($delivery['tracking_number']) ?></span>
                <span style="font-size:.8rem;color:#888;margin-left:.6rem;">· <?= htmlspecialchars($delivery['courier']) ?></span>
            </div>
        </div>

        <div style="display:flex;justify-content:space-between;position:relative;padding:0 1rem;">
            <div style="position:absolute;top:24px;left:calc(1rem + 24px);right:calc(1rem + 24px);height:4px;background:#f0f0f0;z-index:0;"></div>
            <?php
            $filled = max(0, $timeline ? array_search($delivery['status'], array_column($timeline, 'status')) : 0);
            $pct = count($timeline) > 1 ? ($filled / (count($timeline) - 1)) * 100 : 0;
            ?>
            <div style="position:absolute;top:24px;left:calc(1rem + 24px);width:calc(<?= $pct ?>% * (100% - 2rem - 48px) / 100);height:4px;background:#c8f04a;z-index:1;max-width:calc(100% - 2rem - 48px);"></div>

            <?php foreach ($timeline as $step): ?>
            <div style="display:flex;flex-direction:column;align-items:center;gap:.5rem;z-index:2;flex:1;">
                <div style="width:48px;height:48px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.2rem;
                            background:<?= $step['completed'] ? '#c8f04a' : '#f0f0f0' ?>;
                            border:3px solid <?= $step['active'] ? '#1a1a1a' : ($step['completed'] ? '#c8f04a' : '#f0f0f0') ?>;">
                    <?= $step['icon'] ?>
                </div>
                <span style="font-size:.72rem;font-weight:<?= $step['active'] ? '700' : '500' ?>;color:<?= $step['active'] ? '#1a1a1a' : '#888' ?>;text-align:center;line-height:1.3;">
                    <?= $step['label'] ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if ($delivery['estimated_date']): ?>
        <p style="text-align:center;color:#888;font-size:.82rem;margin-top:1.5rem;">
            Estimated delivery: <strong style="color:#1a1a1a;"><?= date('d M Y', strtotime($delivery['estimated_date'])) ?></strong>
        </p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="os-card" style="background:#fff;border-radius:16px;padding:1.8rem;box-shadow:0 2px 12px rgba(0,0,0,.06);margin-bottom:1.5rem;">
        <h2 style="font-size:1rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#1a1a1a;margin:0 0 1.2rem;padding-bottom:.5rem;border-bottom:2px solid #c8f04a;display:inline-block;">
            Items Ordered
        </h2>
        <?php foreach ($items as $item): ?>
        <div class="os-item-border" style="display:flex;gap:1rem;padding:.8rem 0;border-bottom:1px solid #f5f5f5;align-items:center;">
            <div class="os-item-img" style="width:56px;height:56px;border-radius:10px;overflow:hidden;flex-shrink:0;background:#f5f5f5;">
                <img src="<?= htmlspecialchars($item['image'] ?? '') ?>" alt=""
                     style="width:100%;height:100%;object-fit:cover;"
                     onerror="this.src='images/placeholder.jpg'">
            </div>
            <div style="flex:1;">
                <p class="os-item-name" style="font-weight:700;font-size:.92rem;margin:0 0 3px;"><?= htmlspecialchars($item['product_name']) ?></p>
                <p class="os-item-meta" style="font-size:.8rem;color:#888;margin:0;"><?= !empty($item['size']) ? 'Size: ' . htmlspecialchars($item['size']) . ' · ' : '' ?>Qty: <?= $item['quantity'] ?></p>
            </div>
            <span class="os-item-price" style="font-weight:700;font-size:.92rem;">RM <?= number_format($item['unit_price'] * $item['quantity'], 2) ?></span>
        </div>
        <?php endforeach; ?>
        <div style="display:flex;justify-content:flex-end;padding-top:.8rem;">
            <span class="os-total" style="font-size:1rem;font-weight:900;">Total: RM <?= number_format($order['total_amount'], 2) ?></span>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.5rem;">
        <div class="os-card" style="background:#fff;border-radius:16px;padding:1.4rem;box-shadow:0 2px 12px rgba(0,0,0,.06);">
            <h3 class="os-card-label" style="font-size:.82rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#888;margin:0 0 .8rem;">Delivery Address</h3>
            <p class="os-card-strong" style="font-weight:700;margin:0 0 3px;"><?= htmlspecialchars($order['full_name']) ?></p>
            <p class="os-card-text" style="font-size:.88rem;color:#555;margin:0 0 2px;"><?= htmlspecialchars($order['address']) ?></p>
            <p class="os-card-text" style="font-size:.88rem;color:#555;margin:0 0 2px;"><?= htmlspecialchars($order['city']) ?> <?= htmlspecialchars($order['postcode']) ?></p>
            <p class="os-card-text" style="font-size:.88rem;color:#555;margin:0 0 2px;"><?= htmlspecialchars($order['state']) ?></p>
            <p class="os-card-label" style="font-size:.82rem;color:#888;margin-top:.5rem;"><?= htmlspecialchars($order['phone']) ?></p>
        </div>
        <div class="os-card" style="background:#fff;border-radius:16px;padding:1.4rem;box-shadow:0 2px 12px rgba(0,0,0,.06);">
            <h3 class="os-card-label" style="font-size:.82rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#888;margin:0 0 .8rem;">Payment Info</h3>
            <?php if ($order['payment_method']): ?>
            <p class="os-card-text" style="font-size:.88rem;color:#555;margin:0 0 4px;"><strong class="os-card-strong">Method:</strong> <?= ucwords(str_replace('_', ' ', $order['payment_method'])) ?></p>
            <?php endif; ?>
            <?php if ($order['transaction_ref']): ?>
            <p class="os-card-label" style="font-size:.82rem;color:#888;margin:0 0 4px;">Ref: <span style="font-family:monospace;"><?= htmlspecialchars($order['transaction_ref']) ?></span></p>
            <?php endif; ?>
            <?php if ($order['paid_at']): ?>
            <p class="os-card-label" style="font-size:.82rem;color:#888;margin:0;">Paid: <?= date('d M Y, h:i A', strtotime($order['paid_at'])) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <?php if (($delivery['status'] ?? '') === 'delivered'): ?>
    <div style="text-align:center;margin-bottom:1.5rem;">
        <a href="return_request.php?order_id=<?= $order_id ?>"
           style="display:inline-block;padding:.8rem 2rem;border:2px solid #ef4444;color:#ef4444;border-radius:12px;font-weight:700;text-decoration:none;">
            Request Return / Refund
        </a>
    </div>
    <?php endif; ?>

    <?php
    $cancellable  = isset($_SESSION['user_id'])
        && !$is_seller
        && !in_array($order['status'], ['cancelled','delivered','refunded'])
        && in_array($delivery['status'] ?? 'pending', ['pending', null]);
    $can_pack = $is_seller
        && ($delivery['status'] ?? 'pending') === 'pending'
        && !in_array($order['status'], ['cancelled','delivered','refunded']);
    ?>
    <?php if ($can_pack || $cancellable): ?>
    <div style="text-align:center;margin-bottom:1.5rem;display:flex;justify-content:center;gap:1rem;flex-wrap:wrap;">
        <?php if ($can_pack): ?>
        <form method="POST" onsubmit="return confirm('Mark this order as packed?');">
            <input type="hidden" name="mark_packed" value="1">
            <button type="submit"
                    style="padding:.8rem 2rem;background:#1a1a1a;color:#c8f04a;border:none;border-radius:12px;font-weight:700;font-size:.9rem;cursor:pointer;font-family:inherit;">
                Mark as Packed
            </button>
        </form>
        <?php endif; ?>
        <?php if ($cancellable): ?>
        <form method="POST" onsubmit="return confirm('Are you sure you want to cancel this order?');">
            <input type="hidden" name="cancel_order" value="1">
            <button type="submit"
                    style="padding:.8rem 2rem;background:#fff;border:2px solid #ef4444;color:#ef4444;border-radius:12px;font-weight:700;font-size:.9rem;cursor:pointer;font-family:inherit;">
                Cancel Order
            </button>
        </form>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php
    $show_review = !$is_seller
        && isset($_SESSION['user_id'])
        && !empty($order['user_id'])
        && (int)$order['user_id'] === (int)$_SESSION['user_id']
        && (
            in_array($delivery['status'] ?? '', ['delivered'])
            || in_array($order['status'], ['refunded', 'completed', 'delivered'])
        );
    ?>
    <?php if ($show_review): ?>
    <div class="os-card" style="background:#fff;border-radius:16px;padding:1.8rem;box-shadow:0 2px 12px rgba(0,0,0,.06);margin-bottom:1.5rem;">
        <h2 style="font-size:1rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#1a1a1a;margin:0 0 .4rem;padding-bottom:.5rem;border-bottom:2px solid #c8f04a;display:inline-block;">
            Rate Your Purchase
        </h2>
        <p style="color:#888;font-size:.82rem;margin:.6rem 0 1.4rem;">Share your experience for each item.</p>

        <?php if ($review_success): ?>
        <div style="background:#dcfce7;color:#166534;padding:.7rem 1rem;border-radius:8px;margin-bottom:1rem;font-size:.88rem;font-weight:600;">
            ✓ <?= htmlspecialchars($review_success) ?>
        </div>
        <?php endif; ?>
        <?php if ($error && isset($_POST['submit_review'])): ?>
        <div style="background:#fdecea;color:#c62828;padding:.7rem 1rem;border-radius:8px;margin-bottom:1rem;font-size:.88rem;">
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <?php foreach ($items as $item): ?>
        <?php $already = isset($reviewed_products[(int)$item['product_id']]); ?>
        <div class="os-review-item" style="display:flex;gap:1rem;padding:1rem 0;border-bottom:1px solid #f0f0f0;align-items:flex-start;flex-wrap:wrap;">
            <!-- Product thumbnail + name -->
            <div style="display:flex;gap:.8rem;align-items:center;flex:1;min-width:200px;">
                <div style="width:52px;height:52px;border-radius:8px;overflow:hidden;flex-shrink:0;background:#f5f5f5;">
                    <img src="<?= htmlspecialchars($item['image'] ?? '') ?>" alt=""
                         style="width:100%;height:100%;object-fit:cover;"
                         onerror="this.src='images/placeholder.jpg'">
                </div>
                <div>
                    <p class="os-review-name" style="font-weight:700;font-size:.88rem;margin:0 0 2px;"><?= htmlspecialchars($item['product_name']) ?></p>
                    <p class="os-review-meta" style="font-size:.75rem;color:#888;margin:0;">Qty: <?= $item['quantity'] ?></p>
                </div>
            </div>
            <!-- Review form or badge -->
            <?php if ($already): ?>
            <div class="os-reviewed-badge" style="background:#f0fdf4;color:#16a34a;padding:.45rem .9rem;border-radius:8px;font-size:.82rem;font-weight:700;align-self:center;">
                ✓ Reviewed
            </div>
            <?php else: ?>
            <form method="POST" style="flex:2;min-width:240px;">
                <input type="hidden" name="review_product_id" value="<?= (int)$item['product_id'] ?>">
                <!-- Star rating -->
                <div class="star-rating" style="margin-bottom:.6rem;">
                    <?php for ($s = 5; $s >= 1; $s--): ?>
                    <input type="radio" name="rating" id="star<?= $s ?>_<?= $item['product_id'] ?>" value="<?= $s ?>">
                    <label for="star<?= $s ?>_<?= $item['product_id'] ?>">★</label>
                    <?php endfor; ?>
                </div>
                <textarea name="review_text" rows="2" placeholder="Write your review (optional)…"
                          class="os-review-input"
                          style="width:100%;padding:.55rem .8rem;border:1.5px solid #ddd;border-radius:8px;font-size:.85rem;outline:none;resize:vertical;box-sizing:border-box;font-family:inherit;"
                          onfocus="this.style.borderColor='#c8f04a'" onblur="this.style.borderColor=''"></textarea>
                <button type="submit" name="submit_review"
                        style="margin-top:.5rem;padding:.5rem 1.2rem;background:#1a1a1a;color:#c8f04a;border:none;border-radius:8px;font-size:.82rem;font-weight:700;cursor:pointer;font-family:inherit;">
                    Submit Review
                </button>
            </form>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div style="text-align:center;">
        <a href="products.php" style="color:#888;font-size:.88rem;text-decoration:none;">← Continue Shopping</a>
    </div>

    <?php endif; ?>
</main>

<?php require_once 'includes/footer.php'; ?>
