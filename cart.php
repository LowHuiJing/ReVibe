<?php
require_once 'includes/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['cart_session'])) {
    $_SESSION['cart_session'] = session_id();
}
$sid = $_SESSION['cart_session'];

// ── Handle POST actions ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'add') {
        // Validate product_id
        if (!isset($_POST['product_id']) || !is_numeric($_POST['product_id']) || (int)$_POST['product_id'] <= 0) {
            http_response_code(400);
            exit('Invalid product ID');
        }

        $pid   = (int)$_POST['product_id'];
        $redir = $_POST['redirect'] ?? 'cart';

        // Block sellers from buying their own products
        if (isset($_SESSION['user_id'])) {
            $own = mysqli_query($conn, "SELECT id FROM products WHERE id=$pid AND seller_id=" . (int)$_SESSION['user_id']);
            if (mysqli_num_rows($own) > 0) {
                $_SESSION['flash_error'] = 'You cannot purchase your own listing.';
                $dest = $redir === 'detail' ? "product_detail.php?id=$pid" : ($redir === 'index' ? 'index.php' : ($redir === 'products' ? 'products.php' : 'cart.php'));
                header("Location: $dest");
                exit;
            }
        }

        $stmt = mysqli_prepare($conn, "SELECT id, quantity FROM cart WHERE session_id=? AND product_id=?");
        mysqli_stmt_bind_param($stmt, "si", $sid, $pid);
        mysqli_stmt_execute($stmt);
        $check = mysqli_stmt_get_result($stmt);
        mysqli_stmt_close($stmt);

        if (mysqli_num_rows($check) > 0) {
            $row = mysqli_fetch_assoc($check);
            $cid = $row['id'];
            $stmt2 = mysqli_prepare($conn, "UPDATE cart SET quantity=quantity+1 WHERE id=?");
            mysqli_stmt_bind_param($stmt2, "i", $cid);
            mysqli_stmt_execute($stmt2);
            mysqli_stmt_close($stmt2);
        } else {
            $stmt2 = mysqli_prepare($conn, "INSERT INTO cart (session_id, product_id, quantity) VALUES (?, ?, 1)");
            mysqli_stmt_bind_param($stmt2, "si", $sid, $pid);
            mysqli_stmt_execute($stmt2);
            mysqli_stmt_close($stmt2);
        }

        $_SESSION['flash'] = '✓ Added to cart';

        if ($redir === 'products') {
            header("Location: products.php");
        } elseif ($redir === 'index') {
            header("Location: index.php");
        } elseif ($redir === 'detail') {
            header("Location: product_detail.php?id=" . $pid);
        } else {
            header("Location: cart.php");
        }
        exit;
    }

    if ($action === 'update') {
        // Validate cart_id and quantity
        if (!isset($_POST['cart_id']) || !is_numeric($_POST['cart_id']) || (int)$_POST['cart_id'] <= 0) {
            http_response_code(400);
            exit('Invalid cart ID');
        }
        if (!isset($_POST['quantity']) || !is_numeric($_POST['quantity']) || (int)$_POST['quantity'] <= 0) {
            http_response_code(400);
            exit('Invalid quantity');
        }

        $cid = (int)$_POST['cart_id'];
        $qty = max(1, (int)$_POST['quantity']);

        $stmt = mysqli_prepare($conn, "UPDATE cart SET quantity=? WHERE id=? AND session_id=?");
        mysqli_stmt_bind_param($stmt, "iis", $qty, $cid, $sid);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        header("Location: cart.php"); exit;
    }

    if ($action === 'remove') {
        // Validate cart_id
        if (!isset($_POST['cart_id']) || !is_numeric($_POST['cart_id']) || (int)$_POST['cart_id'] <= 0) {
            http_response_code(400);
            exit('Invalid cart ID');
        }

        $cid = (int)$_POST['cart_id'];

        $stmt = mysqli_prepare($conn, "DELETE FROM cart WHERE id=? AND session_id=?");
        mysqli_stmt_bind_param($stmt, "is", $cid, $sid);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        header("Location: cart.php"); exit;
    }
}

// ── Load cart items ──────────────────────────────────────────
$stmt = mysqli_prepare($conn,
    "SELECT cart.id as cart_id,cart.quantity,
            products.id as product_id, products.name, products.price,
            products.`condition`,
            (SELECT image_url 
             FROM product_images pi 
             WHERE pi.product_id = products.id AND pi.is_primary = 1 
             LIMIT 1) AS image,
            categories.name AS category_name
     FROM cart
     JOIN products ON cart.product_id = products.id
     JOIN categories ON products.category_id = categories.id
     WHERE cart.session_id=?
     ORDER BY cart.id ASC"
);
mysqli_stmt_bind_param($stmt, "s", $sid);
mysqli_stmt_execute($stmt);
$cartResult = mysqli_stmt_get_result($stmt);
mysqli_stmt_close($stmt);

$items = [];
$total = 0;
while ($row = mysqli_fetch_assoc($cartResult)) {
    $row['subtotal'] = $row['price'] * $row['quantity'];
    $total += $row['subtotal'];
    $items[] = $row;
}

$page_title = 'REVIBE – Your Cart';
require_once 'includes/header.php';
?>

<main class="cart-page">
    <h1>Your Cart</h1>

    <?php if (empty($items)): ?>
    <div class="cart-empty">
        <p>Your cart is empty.</p>
        <a href="products.php">Browse the collection →</a>
    </div>

    <?php else: ?>

    <?php foreach ($items as $item):
        $condition = strtolower($item['condition']);
        $cat_class = $condition === 'new' ? 'new-item' : 'preloved';
        $cat_label = $condition === 'new' ? 'New Item' : 'PreLoved';
    ?>
    <div class="cart-item">

        <!-- Image -->
        <img src="<?= htmlspecialchars($item['image']) ?>"
             alt="<?= htmlspecialchars($item['name']) ?>"
             class="cart-item__img"
             onerror="this.src='images/placeholder.jpg'">

        <!-- Info -->
        <div>
            <p class="cart-item__name"><?= htmlspecialchars($item['name']) ?></p>
            <p class="cart-item__category <?= $cat_class ?>"><?= $cat_label ?></p>
        </div>

        <!-- Quantity stepper -->
        <form method="POST" id="qty-form-<?= $item['cart_id'] ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="cart_id" value="<?= $item['cart_id'] ?>">
            <div class="qty-wrap">
                <button type="button" class="qty-btn"
                        onclick="changeQty(<?= $item['cart_id'] ?>, -1)">−</button>
                <input type="number" name="quantity"
                       id="qty-<?= $item['cart_id'] ?>"
                       class="qty-input"
                       value="<?= $item['quantity'] ?>"
                       min="1"
                       onchange="submitQty(<?= $item['cart_id'] ?>)">
                <button type="button" class="qty-btn"
                        onclick="changeQty(<?= $item['cart_id'] ?>, 1)">+</button>
            </div>
        </form>

        <!-- Subtotal -->
        <p class="cart-item__price">RM <?= number_format($item['subtotal'], 2) ?></p>

        <!-- Remove -->
        <form method="POST" id="remove-form-<?= $item['cart_id'] ?>">
            <input type="hidden" name="action" value="remove">
            <input type="hidden" name="cart_id" value="<?= $item['cart_id'] ?>">
            <button type="button" class="cart-item__remove" title="Remove item"
                    onclick="confirmRemove(<?= $item['cart_id'] ?>)">×</button>
        </form>

    </div>
    <?php endforeach; ?>

    <!-- Summary -->
    <div class="cart-summary">
        <p class="cart-summary__total">Total: RM <?= number_format($total, 2) ?></p>

        <?php if (!isset($_SESSION['user_id'])): ?>
        <!-- Login prompt -->
        <div style="background:#f9f9f9;border:2px solid #c8f04a;border-radius:14px;
                    padding:1.2rem 1.4rem;margin-bottom:1rem;text-align:center;">
            <p style="font-size:.9rem;font-weight:700;color:#1a1a1a;margin:0 0 .4rem;">
                You need to be logged in to checkout
            </p>
            <p style="font-size:.8rem;color:#888;margin:0 0 1rem;">
                Your cart items are saved — sign in to continue.
            </p>
            <div style="display:flex;gap:.7rem;justify-content:center;flex-wrap:wrap;">
                <a href="/revibe/signin.php"
                   style="padding:.65rem 1.5rem;background:#1a1a1a;color:#c8f04a;border-radius:10px;font-weight:700;font-size:.88rem;text-decoration:none;">
                    Sign In to Checkout
                </a>
                <a href="/revibe/signup.php"
                   style="padding:.65rem 1.5rem;background:#fff;color:#1a1a1a;border:2px solid #1a1a1a;border-radius:10px;font-weight:700;font-size:.88rem;text-decoration:none;">
                    Create Account
                </a>
            </div>
        </div>
        <?php else: ?>
        <a href="payment.php" class="btn-checkout">Proceed to Checkout</a>
        <?php endif; ?>
    </div>

    <?php endif; ?>
</main>

<script>
function changeQty(cartId, delta) {
    const input = document.getElementById('qty-' + cartId);
    const currentVal = parseInt(input.value);

    if (currentVal === 1 && delta === -1) {
        if (confirm('Remove this item from your cart?')) {
            document.getElementById('remove-form-' + cartId).submit();
        }
        return;
    }

    const newVal = Math.max(1, currentVal + delta);
    input.value = newVal;
    submitQty(cartId);
}

function submitQty(cartId) {
    document.getElementById('qty-form-' + cartId).submit();
}

function confirmRemove(cartId) {
    if (confirm('Remove this item from your cart?')) {
        document.getElementById('remove-form-' + cartId).submit();
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>