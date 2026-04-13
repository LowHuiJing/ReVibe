<?php
require_once 'includes/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['cart_session'])) $_SESSION['cart_session'] = session_id();
$sid = $_SESSION['cart_session'];

// ── Helper: load cart items ────────────────────────────────────────────────
function loadCart($conn, $sid) {
    $stmt = mysqli_prepare($conn,
        "SELECT cart.id as cart_id, cart.quantity,
                products.id as product_id, products.name, products.price,
                (SELECT image_url FROM product_images pi
                 WHERE pi.product_id = products.id AND pi.is_primary = 1 LIMIT 1) AS image
         FROM cart JOIN products ON cart.product_id = products.id
         WHERE cart.session_id=? ORDER BY cart.id ASC"
    );
    mysqli_stmt_bind_param($stmt, 's', $sid);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    mysqli_stmt_close($stmt);
    $items = []; $total = 0;
    while ($row = mysqli_fetch_assoc($res)) {
        $row['subtotal'] = $row['price'] * $row['quantity'];
        $total += $row['subtotal'];
        $items[] = $row;
    }
    return [$items, $total];
}

// ── Helper: validate expiry MM/YY ─────────────────────────────────────────
function validateExpiry($expiry) {
    if (!preg_match('/^(0[1-9]|1[0-2])\/(\d{2})$/', trim($expiry), $m)) return false;
    $month = (int)$m[1];
    $year  = (int)('20' . $m[2]);
    $now   = new DateTime();
    $exp   = new DateTime("$year-$month-01");
    $exp->modify('last day of this month');
    return $exp >= $now;
}

// ── Helper: process & insert order ────────────────────────────────────────
function processOrder($conn, $sid, $data, $items, $total) {
    $user_id        = $data['user_id'];
    $full_name      = $data['full_name'];
    $email          = $data['email'];
    $phone          = $data['phone'];
    $address        = $data['address'];
    $city           = $data['city'];
    $postcode       = $data['postcode'];
    $state          = $data['state'];
    $payment_method = $data['payment_method'];
    $card_last_four = $data['card_last_four'] ?? null;

    mysqli_begin_transaction($conn);
    try {
        // 1. Order
        $stmt = mysqli_prepare($conn,
            "INSERT INTO orders (session_id,user_id,full_name,email,phone,address,city,postcode,state,total_amount,status)
             VALUES (?,?,?,?,?,?,?,?,?,?,'confirmed')"
        );
        mysqli_stmt_bind_param($stmt,'sisssssssd',
            $sid,$user_id,$full_name,$email,$phone,$address,$city,$postcode,$state,$total);
        mysqli_stmt_execute($stmt);
        $order_id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        // 2. Order items
        $istmt = mysqli_prepare($conn,
            "INSERT INTO order_items (order_id,product_id,product_name,quantity,unit_price) VALUES (?,?,?,?,?)"
        );
        foreach ($items as $item) {
            mysqli_stmt_bind_param($istmt,'iisid',
                $order_id,$item['product_id'],$item['name'],$item['quantity'],$item['price']);
            mysqli_stmt_execute($istmt);
        }
        mysqli_stmt_close($istmt);

        // 3. Decrease product stock (change `quantity` to your actual stock column)
        mysqli_begin_transaction($conn);

        try {

            // 1. Group quantity by product
            $qtyByProduct = [];
            foreach ($items as $item) {
                $pid = (int)$item['product_id'];
                $qty = (int)$item['quantity'];
                $qtyByProduct[$pid] = ($qtyByProduct[$pid] ?? 0) + $qty;
            }

        // 2. Prepare statements

            // Reduce stock
            $updateStockStmt = mysqli_prepare($conn,
                "UPDATE products
                SET stock_quantity = stock_quantity - ?
                WHERE id = ? AND stock_quantity >= ?"
            );

            // Set sold_out ONLY if stock = 0
            $updateStatusStmt = mysqli_prepare($conn,
                "UPDATE products
                SET status = 'sold_out'
                WHERE id = ? AND stock_quantity = 0"
            );

            // 3. Execute updates
            foreach ($qtyByProduct as $pid => $qty) {

                // --- Step 1: Deduct stock ---
                mysqli_stmt_bind_param($updateStockStmt, 'iii', $qty, $pid, $qty);

                if (!mysqli_stmt_execute($updateStockStmt)) {
                    throw new Exception('Stock update failed: ' . mysqli_error($conn));
                }

                if (mysqli_stmt_affected_rows($updateStockStmt) !== 1) {
                    throw new Exception("Not enough stock for product ID $pid.");
                }

                // --- Step 2: Update status if needed ---
                mysqli_stmt_bind_param($updateStatusStmt, 'i', $pid);

                if (!mysqli_stmt_execute($updateStatusStmt)) {
                    throw new Exception('Status update failed: ' . mysqli_error($conn));
                }
            }

            // 4. Commit transaction
            mysqli_commit($conn);

        } catch (Exception $e) {

            mysqli_rollback($conn);
            throw $e;
        }

        // Close statements
        mysqli_stmt_close($updateStockStmt);
        mysqli_stmt_close($updateStatusStmt);


        // 4. Payment
        $txn_ref = 'TXN-' . strtoupper(uniqid());
        $paid_at = date('Y-m-d H:i:s');
        $stmt = mysqli_prepare($conn,
            "INSERT INTO payments (order_id,payment_method,payment_status,transaction_ref,amount,paid_at)
             VALUES (?,?,'paid',?,?,?)"
        );
        mysqli_stmt_bind_param($stmt,'issds',
            $order_id,$payment_method,$txn_ref,$total,$paid_at);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // 5. Delivery
        $tracking = 'RVJT' . str_pad($order_id, 8, '0', STR_PAD_LEFT);
        $est_date = date('Y-m-d', strtotime('+5 weekdays'));
        $stmt = mysqli_prepare($conn,
            "INSERT INTO deliveries (order_id,tracking_number,courier,status,estimated_date)
             VALUES (?,?,'J&T Express','pending',?)"
        );
        mysqli_stmt_bind_param($stmt,'iss',$order_id,$tracking,$est_date);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // 6. Clear cart
        $stmt = mysqli_prepare($conn, "DELETE FROM cart WHERE session_id=?");
        mysqli_stmt_bind_param($stmt,'s',$sid);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        mysqli_commit($conn);
        return ['order_id'=>$order_id,'txn_ref'=>$txn_ref,'tracking'=>$tracking,'est_date'=>$est_date];
    } catch (Exception $e) {
        mysqli_rollback($conn);
        return ['error' => $e->getMessage()];
    }
}

// PHASE A — Confirm from redirect (online banking / ewallet auto-submit)
$phase    = 'form'; 
$errors   = [];
$result   = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_payment'])) {
    // Auto-submitted after redirect countdown
    $pending = $_SESSION['pending_payment'] ?? null;
    if ($pending) {
        [$items, $total] = loadCart($conn, $sid);
        // Cart may already have been cleared — use saved items
        if (empty($items)) { $items = $pending['items']; $total = $pending['total']; }
        $result = processOrder($conn, $sid, $pending, $items, $total);
        unset($_SESSION['pending_payment']);
        if ($result && !isset($result['error'])) { $phase = 'success'; }
        else {
            $errors[] = 'Payment processing failed: ' . ($result['error'] ?? 'Unknown error. Check that orders_tables.sql has been imported.');
            $phase = 'form';
        }
    } else {
        $errors[] = 'Session expired. Please try again.';
        $phase = 'form';
    }
}

// PHASE B — Checkout form submitted
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    [$items, $total] = loadCart($conn, $sid);

    $full_name      = trim($_POST['full_name']      ?? '');
    $email          = trim($_POST['email']          ?? '');
    $phone          = trim($_POST['phone']          ?? '');
    $address        = trim($_POST['address']        ?? '');
    $city           = trim($_POST['city']           ?? '');
    $postcode       = trim($_POST['postcode']       ?? '');
    $state          = trim($_POST['state']          ?? '');
    $payment_method = trim($_POST['payment_method'] ?? '');
    $card_number    = preg_replace('/\D/', '', $_POST['card_number'] ?? '');
    $card_expiry    = trim($_POST['card_expiry']    ?? '');

    // — Common validations
    if (empty($full_name))  $errors[] = 'Full name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
    if (empty($phone))      $errors[] = 'Phone number is required.';
    if (empty($address))    $errors[] = 'Address is required.';
    if (empty($city))       $errors[] = 'City is required.';
    if (empty($postcode))   $errors[] = 'Postcode is required.';
    if (empty($state))      $errors[] = 'State is required.';
    if (!in_array($payment_method, ['credit_card','debit_card','online_banking','ewallet']))
        $errors[] = 'Please select a payment method.';
    if (empty($items))      $errors[] = 'Your cart is empty.';

    // — Card-only validations
    $isCard = in_array($payment_method, ['credit_card','debit_card']);
    if ($isCard) {
        if (strlen($card_number) < 13) $errors[] = 'Enter a valid card number.';
        if (empty($card_expiry))       $errors[] = 'Expiry date is required.';
        elseif (!validateExpiry($card_expiry)) $errors[] = 'Your card has expired or the date is invalid.';
    }

    if (empty($errors)) {
        $orderData = [
            'user_id'        => isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null,
            'full_name'      => $full_name,
            'email'          => $email,
            'phone'          => $phone,
            'address'        => $address,
            'city'           => $city,
            'postcode'       => $postcode,
            'state'          => $state,
            'payment_method' => $payment_method,
            'card_last_four' => $isCard ? substr($card_number,-4) : null,
            'items'          => $items,
            'total'          => $total,
        ];

        if ($isCard) {
            // Process immediately
            $result = processOrder($conn, $sid, $orderData, $items, $total);
            if ($result && !isset($result['error'])) {
                $phase = 'success';
            } else {
                $phase = 'form';
                $errors[] = 'Order processing failed: ' . ($result['error'] ?? 'Unknown error. Check that orders_tables.sql has been imported.');
            }
        } else {
            // Save to session → show redirect page
            $_SESSION['pending_payment'] = $orderData;
            $phase = 'redirect';
        }
    }
}

// PHASE C — Load cart for form display
if ($phase === 'form') {
    [$items, $total] = loadCart($conn, $sid);
    if (empty($items)) { header('Location: cart.php'); exit; }
}

$page_title = 'Checkout — REVIBE';
require_once 'includes/header.php';

// Provider branding for redirect page
$providers = [
    'online_banking' => [
        'label'   => 'FPX Online Banking',
        'icon'    => '🏦',
        'colour'  => '#003087',
        'options' => ['Maybank2u','CIMB Clicks','RHB Now','Public Bank','Hong Leong Connect','AmOnline'],
    ],
    'ewallet' => [
        'label'   => 'E-Wallet',
        'icon'    => '📱',
        'colour'  => '#00b14f',
        'options' => ["Touch 'n Go eWallet","Boost","GrabPay","ShopeePay"],
    ],
];
$selMethod    = $_POST['payment_method'] ?? ($_SESSION['pending_payment']['payment_method'] ?? 'credit_card');
$providerInfo = $providers[$selMethod] ?? null;
?>

<style>
body.dark { background: #0f172a; }
/* Cards */
body.dark .pay-card    { background: #1e293b !important; box-shadow: 0 2px 12px rgba(0,0,0,.3) !important; }
body.dark .pay-summary { background: #1e293b !important; box-shadow: 0 2px 12px rgba(0,0,0,.3) !important; }
/* Text inside cards */
body.dark .pay-card h2,
body.dark .pay-summary h2       { color: #e2e8f0 !important; }
body.dark .pay-card label       { color: #94a3b8 !important; }
/* Inputs/selects/textareas inside cards */
body.dark .pay-card input,
body.dark .pay-card select,
body.dark .pay-card textarea    { background: #0f172a !important; border-color: #334155 !important; color: #e2e8f0 !important; }
/* Payment method radio labels */
body.dark .pay-method-lbl       { border-color: #334155 !important; background: #1e293b !important; color: #e2e8f0 !important; }
/* Order summary */
body.dark .pay-item-border      { border-bottom-color: #334155 !important; }
body.dark .pay-item-name        { color: #e2e8f0 !important; }
body.dark .pay-item-meta        { color: #64748b !important; }
body.dark .pay-item-price       { color: #e2e8f0 !important; }
body.dark .pay-total-bdr        { border-top-color: #334155 !important; }
body.dark .pay-total-lbl        { color: #94a3b8 !important; }
body.dark .pay-total-val        { color: #e2e8f0 !important; }
/* Success screen */
body.dark .pay-success-bg       { background: #1e293b !important; box-shadow: 0 2px 16px rgba(0,0,0,.3) !important; }
body.dark .pay-success-h1       { color: #e2e8f0 !important; }
body.dark .pay-success-sub      { color: #64748b !important; }
body.dark .pay-success-row      { border-bottom-color: #334155 !important; }
body.dark .pay-success-lbl      { color: #94a3b8 !important; }
body.dark .pay-success-val      { color: #e2e8f0 !important; }
/* Redirect screen */
body.dark .pay-redirect-h2      { color: #e2e8f0 !important; }
body.dark .pay-redirect-p       { color: #64748b !important; }
/* Warnings */
body.dark .pay-warn-banking     { background: #422006 !important; border-color: #92400e !important; color: #fed7aa !important; }
body.dark .pay-warn-ewallet     { background: #14532d !important; border-color: #166534 !important; color: #86efac !important; }
/* Error banner */
body.dark .pay-err              { background: #7f1d1d !important; color: #fca5a5 !important; }
</style>

<main style="max-width:1060px;margin:40px auto;padding:0 16px;font-family:'Montserrat',sans-serif;">

<?php /* ══════════ SUCCESS ══════════ */ if ($phase === 'success'): ?>
<div style="text-align:center;padding:3rem 1rem;">
    <div style="width:88px;height:88px;background:#c8f04a;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:2.5rem;margin:0 auto 1.4rem;animation:popIn .4s ease;">✓</div>
    <h1 class="pay-success-h1" style="font-size:1.7rem;font-weight:900;color:#1a1a1a;margin:0 0 .4rem;">Order Placed Successfully!</h1>
    <p class="pay-success-sub" style="color:#888;margin:0 0 2rem;font-size:.95rem;">Thank you for shopping with REVIBE.</p>

    <div class="pay-success-bg" style="background:#fff;border-radius:18px;padding:1.6rem 2rem;box-shadow:0 2px 16px rgba(0,0,0,.07);max-width:480px;margin:0 auto 2.2rem;text-align:left;">
        <?php
        $rows = [
            'Order ID'         => '#' . $result['order_id'],
            'Transaction Ref'  => $result['txn_ref'],
            'Tracking Number'  => $result['tracking'],
            'Est. Delivery'    => date('d M Y', strtotime($result['est_date'])),
        ];
        $last = array_key_last($rows);
        foreach ($rows as $lbl => $val):
        ?>
        <div class="pay-success-row" style="display:flex;justify-content:space-between;align-items:center;padding:.65rem 0;<?= $lbl !== $last ? 'border-bottom:1px solid #f0f0f0;' : '' ?>">
            <span class="pay-success-lbl" style="color:#888;font-size:.85rem;"><?= $lbl ?></span>
            <span class="pay-success-val" style="font-weight:700;font-size:.9rem;font-family:<?= in_array($lbl,['Transaction Ref','Tracking Number']) ? 'monospace' : 'inherit' ?>;"><?= htmlspecialchars($val) ?></span>
        </div>
        <?php endforeach; ?>
    </div>

    <div style="display:flex;gap:1rem;justify-content:center;flex-wrap:wrap;">
        <a href="order_status.php?order_id=<?= $result['order_id'] ?>"
           style="padding:.85rem 2rem;background:#1a1a1a;color:#c8f04a;border-radius:12px;font-weight:700;text-decoration:none;font-size:.95rem;">
            Track My Order →
        </a>
        <a href="products.php"
           style="padding:.85rem 2rem;background:#fff;color:#1a1a1a;border:2px solid #1a1a1a;border-radius:12px;font-weight:700;text-decoration:none;font-size:.95rem;">
            Continue Shopping
        </a>
    </div>
</div>
<style>@keyframes popIn{from{transform:scale(.5);opacity:0}to{transform:scale(1);opacity:1}}</style>

<?php /* ══════════ REDIRECT SIMULATION ══════════ */ elseif ($phase === 'redirect'): ?>
<?php $pInfo = $providers[$selMethod]; ?>
<div id="redirect-screen"
     style="display:flex;flex-direction:column;align-items:center;justify-content:center;
            min-height:65vh;text-align:center;padding:2rem;">

    <!-- Provider badge -->
    <div style="width:80px;height:80px;border-radius:20px;background:<?= $pInfo['colour'] ?>;
                display:flex;align-items:center;justify-content:center;
                font-size:2.5rem;margin-bottom:1.5rem;
                box-shadow:0 8px 24px <?= $pInfo['colour'] ?>55;">
        <?= $pInfo['icon'] ?>
    </div>

    <h2 class="pay-redirect-h2" style="font-size:1.3rem;font-weight:900;margin:0 0 .4rem;">
        Redirecting to <?= htmlspecialchars($pInfo['label']) ?>
    </h2>
    <p class="pay-redirect-p" style="color:#888;font-size:.9rem;margin:0 0 2rem;">
        Please do not close this window…
    </p>

    <!-- Spinner -->
    <div style="width:48px;height:48px;border:4px solid #eee;border-top-color:#1a1a1a;
                border-radius:50%;animation:spin 0.8s linear infinite;margin-bottom:2rem;"></div>

    <!-- Progress bar -->
    <div style="width:280px;height:6px;background:#eee;border-radius:99px;overflow:hidden;margin-bottom:1.2rem;">
        <div id="prog-bar"
             style="height:100%;width:0%;background:#c8f04a;border-radius:99px;
                    transition:width 3s linear;"></div>
    </div>

    <p id="redirect-msg" style="color:#aaa;font-size:.82rem;">Connecting to <?= htmlspecialchars($pInfo['options'][0]) ?>…</p>

    <!-- Hidden auto-submit form -->
    <form id="confirm-form" method="POST" action="payment.php">
        <input type="hidden" name="confirm_payment" value="1">
    </form>
</div>

<style>
@keyframes spin { to { transform: rotate(360deg); } }
</style>
<script>
(function() {
    // Start progress bar after paint
    setTimeout(function() {
        document.getElementById('prog-bar').style.width = '100%';
    }, 100);

    // Message sequence
    var messages = [
        'Connecting to secure gateway…',
        'Verifying transaction…',
        'Authorising payment…',
        'Payment successful! Redirecting back…'
    ];
    var idx = 0;
    var msgEl = document.getElementById('redirect-msg');
    var interval = setInterval(function() {
        idx++;
        if (idx < messages.length) msgEl.textContent = messages[idx];
    }, 800);

    // Auto-submit after 3.2 s
    setTimeout(function() {
        clearInterval(interval);
        document.getElementById('confirm-form').submit();
    }, 3200);
})();
</script>

<?php /* ══════════ CHECKOUT FORM ══════════ */ else: ?>
<?php if (!empty($errors)): ?>
<div class="pay-err" style="background:#fdecea;color:#c62828;padding:1rem 1.1rem;border-radius:12px;margin-bottom:1.4rem;font-size:.88rem;">
    <?php foreach ($errors as $e): ?><p style="margin:0 0 3px;">• <?= htmlspecialchars($e) ?></p><?php endforeach; ?>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 360px;gap:1.8rem;align-items:start;" class="checkout-grid">

<!-- ── LEFT: Form ── -->
<div>
<?php
$card  = 'background:#fff;border-radius:16px;padding:1.6rem;box-shadow:0 2px 12px rgba(0,0,0,.06);margin-bottom:1.4rem;';
$h2s   = 'font-size:1rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#1a1a1a;margin:0 0 1.2rem;padding-bottom:.5rem;border-bottom:2px solid #c8f04a;';
$is    = 'width:100%;padding:.65rem .9rem;border:1.5px solid #ddd;border-radius:8px;font-size:.95rem;outline:none;box-sizing:border-box;font-family:inherit;';
$ls    = 'display:block;font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#444;margin-bottom:.4rem;';
$gs    = 'margin-bottom:1rem;';
$selM  = $_POST['payment_method'] ?? 'credit_card';
?>
<form method="POST" id="checkout-form" onsubmit="return beforeSubmit()">

    <!-- Delivery Info -->
    <div class="pay-card" style="<?= $card ?>">
        <h2 style="<?= $h2s ?>">Delivery Information</h2>
        <div style="<?= $gs ?>">
            <label style="<?= $ls ?>">Full Name</label>
            <input type="text" name="full_name" required
                   value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
                   style="<?= $is ?>" onfocus="hl(this)" onblur="unhl(this)">
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <div style="<?= $gs ?>">
                <label style="<?= $ls ?>">Email</label>
                <input type="email" name="email" required
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                       style="<?= $is ?>" onfocus="hl(this)" onblur="unhl(this)">
            </div>
            <div style="<?= $gs ?>">
                <label style="<?= $ls ?>">Phone</label>
                <input type="text" name="phone" required
                       value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                       style="<?= $is ?>" onfocus="hl(this)" onblur="unhl(this)">
            </div>
        </div>
        <div style="<?= $gs ?>">
            <label style="<?= $ls ?>">Address</label>
            <textarea name="address" rows="2" required
                      style="<?= $is ?>resize:vertical;" onfocus="hl(this)" onblur="unhl(this)"><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;">
            <div style="<?= $gs ?>">
                <label style="<?= $ls ?>">City</label>
                <input type="text" name="city" required
                       value="<?= htmlspecialchars($_POST['city'] ?? '') ?>"
                       style="<?= $is ?>" onfocus="hl(this)" onblur="unhl(this)">
            </div>
            <div style="<?= $gs ?>">
                <label style="<?= $ls ?>">Postcode</label>
                <input type="text" name="postcode" required
                       value="<?= htmlspecialchars($_POST['postcode'] ?? '') ?>"
                       style="<?= $is ?>" onfocus="hl(this)" onblur="unhl(this)">
            </div>
            <div style="<?= $gs ?>">
                <label style="<?= $ls ?>">State</label>
                <select name="state" required style="<?= $is ?>">
                    <option value="">— Select —</option>
                    <?php
                    $states = ['Johor','Kedah','Kelantan','Kuala Lumpur','Labuan','Melaka',
                               'Negeri Sembilan','Pahang','Perak','Perlis','Pulau Pinang',
                               'Putrajaya','Sabah','Sarawak','Selangor','Terengganu'];
                    $pState = $_POST['state'] ?? '';
                    foreach ($states as $s):
                    ?>
                    <option value="<?= $s ?>" <?= $pState===$s ? 'selected':'' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <!-- Payment Method -->
    <div class="pay-card" style="<?= $card ?>">
        <h2 style="<?= $h2s ?>">Payment Method</h2>
        <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:.8rem;margin-bottom:1.4rem;" id="method-grid">
            <?php
            $methods = [
                'credit_card'    => ['💳','Credit Card'],
                'debit_card'     => ['🏧','Debit Card'],
                'online_banking' => ['🏦','Online Banking'],
                'ewallet'        => ['📱','E-Wallet'],
            ];
            foreach ($methods as $val => [$icon, $label]):
            $active = $selM === $val;
            ?>
            <label class="pay-method-lbl" id="lbl-<?= $val ?>"
                   style="display:flex;align-items:center;gap:.7rem;padding:.9rem 1rem;
                          border:2px solid <?= $active?'#1a1a1a':'#ddd' ?>;
                          border-radius:10px;cursor:pointer;
                          background:<?= $active?'#f9f9f9':'#fff' ?>;
                          transition:all .15s;">
                <input type="radio" name="payment_method" value="<?= $val ?>"
                       <?= $active?'checked':'' ?>
                       onchange="switchMethod('<?= $val ?>')"
                       style="accent-color:#1a1a1a;">
                <span style="font-size:1.2rem;"><?= $icon ?></span>
                <span style="font-size:.9rem;font-weight:600;"><?= $label ?></span>
            </label>
            <?php endforeach; ?>
        </div>

        <!-- ── Card fields (credit/debit) ── -->
        <div id="card-fields"
             style="display:<?= in_array($selM,['credit_card','debit_card'])?'block':'none' ?>;">
            <div style="<?= $gs ?>">
                <label style="<?= $ls ?>">Card Number</label>
                <input type="text" name="card_number" id="card_number"
                       placeholder="•••• •••• •••• ••••" maxlength="19"
                       value="<?= htmlspecialchars($_POST['card_number'] ?? '') ?>"
                       style="<?= $is ?>font-family:monospace;letter-spacing:2px;"
                       oninput="formatCard(this)" onfocus="hl(this)" onblur="unhl(this)">
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                <div style="<?= $gs ?>">
                    <label style="<?= $ls ?>">Expiry (MM/YY)</label>
                    <input type="text" name="card_expiry" id="card_expiry"
                           placeholder="MM/YY" maxlength="5"
                           value="<?= htmlspecialchars($_POST['card_expiry'] ?? '') ?>"
                           style="<?= $is ?>" oninput="formatExpiry(this)"
                           onfocus="hl(this)" onblur="unhl(this)">
                    <span id="expiry-err" style="color:#c62828;font-size:.75rem;display:none;">Card is expired.</span>
                </div>
                <div style="<?= $gs ?>">
                    <label style="<?= $ls ?>">CVV</label>
                    <input type="text" name="card_cvv" id="card_cvv" placeholder="•••"
                        inputmode="numeric" pattern="[0-9]*"
                        maxlength="4" value="<?= htmlspecialchars($_POST['card_cvv'] ?? '') ?>"
                        style="<?= $is ?>" oninput="formatCVV(this)" onfocus="hl(this)"
                        onblur="validateCVV(this)">
                    <span id="cvv-err" style="color:#c62828;font-size:.75rem;display:none;">
                        Invalid CVV.
                    </span>
                </div>
            </div>
        </div>

        <!-- ── Online Banking fields ── -->
        <div id="ob-fields"
             style="display:<?= $selM==='online_banking'?'block':'none' ?>;">
            <div style="<?= $gs ?>">
                <label style="<?= $ls ?>">Select Bank</label>
                <select name="ob_bank" style="<?= $is ?>">
                    <?php foreach(['Maybank2u','CIMB Clicks','RHB Now','Public Bank','Hong Leong Connect','AmOnline'] as $b): ?>
                    <option><?= $b ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="pay-warn-banking" style="background:#fffbe6;border:1px solid #fcd34d;border-radius:10px;padding:.9rem 1rem;font-size:.82rem;color:#78350f;">
                💡 You will be redirected to your bank's secure portal to complete payment.
            </div>
        </div>

        <!-- ── E-Wallet fields ── -->
        <div id="ew-fields"
             style="display:<?= $selM==='ewallet'?'block':'none' ?>;">
            <div style="<?= $gs ?>">
                <label style="<?= $ls ?>">Select E-Wallet</label>
                <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:.6rem;">
                    <?php
                    $wallets = [
                        'tng'      => ["Touch 'n Go","#00b14f"],
                        'boost'    => ['Boost','#e84646'],
                        'grabpay'  => ['GrabPay','#00b14f'],
                        'shopee'   => ['ShopeePay','#ee4d2d'],
                    ];
                    $selW = $_POST['ewallet_choice'] ?? 'tng';
                    foreach ($wallets as $wval => [$wlbl,$wcol]):
                    ?>
                    <label style="display:flex;align-items:center;gap:.6rem;padding:.75rem .9rem;
                                  border:2px solid <?= $selW===$wval?$wcol:'#ddd' ?>;
                                  border-radius:8px;cursor:pointer;font-size:.85rem;font-weight:600;">
                        <input type="radio" name="ewallet_choice" value="<?= $wval ?>"
                               <?= $selW===$wval?'checked':'' ?>
                               onchange="highlightWallet(this,'<?= $wcol ?>')"
                               style="accent-color:<?= $wcol ?>;">
                        <?= $wlbl ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="pay-warn-ewallet" style="background:#f0fdf4;border:1px solid #86efac;border-radius:10px;padding:.9rem 1rem;font-size:.82rem;color:#166534;margin-top:.3rem;">
                📱 A payment request will be sent to your e-wallet app automatically.
            </div>
        </div>
    </div>

    <!-- hidden so disabled button doesn't break POST -->
    <input type="hidden" name="checkout" value="1">
    <button type="submit" id="submit-btn"
            style="width:100%;padding:1rem;background:#1a1a1a;color:#c8f04a;border:none;
                   border-radius:14px;font-size:1rem;font-weight:700;cursor:pointer;letter-spacing:.5px;">
        Place Order — RM <?= number_format($total, 2) ?>
    </button>
</form>
</div>

<!-- ── RIGHT: Order Summary ── -->
<div style="position:sticky;top:90px;">
    <div class="pay-summary" style="background:#fff;border-radius:16px;padding:1.4rem;box-shadow:0 2px 12px rgba(0,0,0,.06);">
        <h2 style="font-size:1rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;
                   color:#1a1a1a;margin:0 0 1.2rem;">
            Order Summary (<?= count($items) ?> item<?= count($items)!==1?'s':'' ?>)
        </h2>
        <?php foreach ($items as $item): ?>
        <div class="pay-item-border" style="display:flex;gap:.8rem;padding:.7rem 0;border-bottom:1px solid #f5f5f5;">
            <div style="width:52px;height:52px;border-radius:8px;overflow:hidden;flex-shrink:0;background:#f5f5f5;">
                <img src="<?= htmlspecialchars($item['image'] ?? '') ?>"
                     alt="" style="width:100%;height:100%;object-fit:cover;"
                     onerror="this.src='images/placeholder.jpg'">
            </div>
            <div style="flex:1;min-width:0;">
                <p class="pay-item-name" style="font-size:.85rem;font-weight:600;color:#1a1a1a;margin:0 0 2px;
                           white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                    <?= htmlspecialchars($item['name']) ?>
                </p>
                <p class="pay-item-meta" style="font-size:.75rem;color:#888;margin:0;">Qty: <?= $item['quantity'] ?></p>
            </div>
            <span class="pay-item-price" style="font-size:.88rem;font-weight:700;color:#1a1a1a;white-space:nowrap;">
                RM <?= number_format($item['subtotal'], 2) ?>
            </span>
        </div>
        <?php endforeach; ?>
        <div class="pay-total-bdr" style="margin-top:1rem;padding-top:.8rem;border-top:2px solid #1a1a1a;">
            <div style="display:flex;justify-content:space-between;margin-bottom:.5rem;">
                <span class="pay-total-lbl" style="font-size:.88rem;color:#888;">Subtotal</span>
                <span class="pay-total-val" style="font-size:.88rem;">RM <?= number_format($total, 2) ?></span>
            </div>
            <div style="display:flex;justify-content:space-between;margin-bottom:.5rem;">
                <span class="pay-total-lbl" style="font-size:.88rem;color:#888;">Shipping</span>
                <span style="font-size:.88rem;color:#22c55e;font-weight:700;">FREE</span>
            </div>
            <div style="display:flex;justify-content:space-between;margin-top:.8rem;">
                <span class="pay-total-val" style="font-size:1rem;font-weight:900;">Total</span>
                <span class="pay-total-val" style="font-size:1.1rem;font-weight:900;">RM <?= number_format($total, 2) ?></span>
            </div>
        </div>
    </div>
    <p style="font-size:.72rem;color:#bbb;text-align:center;margin-top:.8rem;">
        🔒 Dummy checkout — no real payment is processed.
    </p>
</div>
</div><!-- /grid -->

<style>
@media(max-width:768px){ .checkout-grid{ grid-template-columns:1fr !important; } }
</style>

<script>
// ── Focus helpers
function hl(el)   { el.style.borderColor = '#c8f04a'; }
function unhl(el) { el.style.borderColor = document.body.classList.contains('dark') ? '#334155' : '#ddd'; }

// ── Card formatter
function formatCard(input) {
    var v = input.value.replace(/\D/g,'').substring(0,16);
    input.value = v.replace(/(.{4})/g,'$1 ').trim();
}

// ── Expiry formatter + live validation
function formatExpiry(input) {
    var v = input.value.replace(/\D/g,'').substring(0,4);
    if (v.length >= 2) v = v.substring(0,2) + '/' + v.substring(2);
    input.value = v;
    validateExpiryLive(v);
}
function validateExpiryLive(val) {
    var errEl = document.getElementById('expiry-err');
    if (!errEl) return;
    var m = val.match(/^(0[1-9]|1[0-2])\/(\d{2})$/);
    if (!m) { errEl.style.display='none'; return; }
    var exp = new Date(2000 + parseInt(m[2]), parseInt(m[1]) - 1, 1);
    exp.setMonth(exp.getMonth()+1); exp.setDate(exp.getDate()-1); // last day
    errEl.style.display = (exp < new Date()) ? 'block' : 'none';
}

// ── CVV formatter + validation
function formatCVV(input) {
    input.value = input.value.replace(/\D/g, '').substring(0, 3);
}
function validateCVV(input) {
    var errEl = document.getElementById('cvv-err');
    if (!errEl) return;
    var v = input.value.replace(/\D/g, '');
    errEl.style.display = (v.length >= 3 && v.length < 4) ? 'none' : 'block';
}

// ── Switch payment method
function switchMethod(val) {
    var dark = document.body.classList.contains('dark');
    var methods = ['credit_card','debit_card','online_banking','ewallet'];
    methods.forEach(function(m) {
        var lbl = document.getElementById('lbl-' + m);
        var active = (m === val);
        lbl.style.borderColor = active ? (dark ? '#c8f04a' : '#1a1a1a') : (dark ? '#334155' : '#ddd');
        lbl.style.background  = dark ? '#1e293b' : (active ? '#f9f9f9' : '#fff');
    });
    var isCard = val === 'credit_card' || val === 'debit_card';
    document.getElementById('card-fields').style.display = isCard            ? 'block' : 'none';
    document.getElementById('ob-fields').style.display   = val==='online_banking' ? 'block' : 'none';
    document.getElementById('ew-fields').style.display   = val==='ewallet'        ? 'block' : 'none';

    // Update button label
    var label = isCard ? 'Place Order' : 'Continue to Payment';
    document.getElementById('submit-btn').textContent =
        label + ' — RM <?= number_format($total, 2) ?>';
}

// ── Ewallet option highlight
function highlightWallet(radio, colour) {
    document.querySelectorAll('#ew-fields label').forEach(function(lbl) {
        lbl.style.borderColor = '#ddd';
    });
    radio.closest('label').style.borderColor = colour;
}

// ── Pre-submit: show loading on redirect methods
function beforeSubmit() {
    var method = document.querySelector('input[name="payment_method"]:checked').value;
    if (method === 'online_banking' || method === 'ewallet') {
        document.getElementById('submit-btn').textContent = 'Redirecting…';
        document.getElementById('submit-btn').disabled = true;
    }
    return true;
}

// Init correct button label on load
(function() {
    var checked = document.querySelector('input[name="payment_method"]:checked');
    if (checked) switchMethod(checked.value);
})();
</script>

<?php endif; ?>
</main>

<?php require_once 'includes/footer.php'; ?>
