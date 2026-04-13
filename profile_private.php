<?php
require_once 'includes/db.php';
require_once 'includes/session.php';
require_once 'includes/auth.php';

checkSessionTimeout();

if (!isset($_SESSION['user_id'])) {
    $page_title = 'My Dashboard — REVIBE';
    require_once 'includes/header.php';
    echo '<main style="max-width:500px;margin:80px auto;padding:0 1.5rem;text-align:center;">
        <div style="background:#fff;border-radius:20px;box-shadow:0 2px 14px rgba(0,0,0,.08);padding:2.5rem 2rem;">
            <div style="font-size:2.5rem;margin-bottom:1rem;">🔒</div>
            <h1 style="font-size:1.2rem;font-weight:900;margin:0 0 .5rem;">Sign in to view your orders & tickets</h1>
            <p style="color:#888;font-size:.88rem;margin:0 0 1.5rem;">Your order history, payment records, and support tickets are private.</p>
            <div style="display:flex;gap:.7rem;justify-content:center;flex-wrap:wrap;">
                <a href="/revibe/signin.php" style="padding:.7rem 1.6rem;background:#1a1a1a;color:#c8f04a;border-radius:10px;font-weight:700;font-size:.88rem;text-decoration:none;">Sign In</a>
                <a href="/revibe/signup.php" style="padding:.7rem 1.6rem;background:#fff;color:#1a1a1a;border:2px solid #1a1a1a;border-radius:10px;font-weight:700;font-size:.88rem;text-decoration:none;">Create Account</a>
            </div>
        </div>
    </main>';
    require_once 'includes/footer.php';
    exit;
}

$user_id = $_SESSION['user_id'];

// Order history (user_id column in revibe)
$orders_result = mysqli_query($conn,
    "SELECT o.id, o.total_amount, o.status, o.created_at,
            COUNT(oi.id) as item_count,
            CASE WHEN o.status IN ('cancelled','refunded') THEN o.status
                 ELSE COALESCE(d.status, o.status) END as display_status
     FROM orders o
     LEFT JOIN order_items oi ON o.id = oi.order_id
     LEFT JOIN deliveries d ON d.order_id = o.id
     WHERE o.user_id = $user_id
     GROUP BY o.id
     ORDER BY o.created_at DESC"
);

// Payment history (from payments table — no transactions table in revibe)
$payments_result = mysqli_query($conn,
    "SELECT p.id, p.amount, p.payment_method, p.payment_status,
            p.transaction_ref, p.paid_at, p.created_at,
            o.id as order_id
     FROM payments p
     JOIN orders o ON o.id = p.order_id
     WHERE o.user_id = $user_id
     ORDER BY p.created_at DESC"
);

// Support tickets
$tickets_result = mysqli_query($conn,
    "SELECT id, subject, status, priority, created_at, updated_at
     FROM support_tickets
     WHERE user_id = $user_id
     ORDER BY created_at DESC"
);

// Handle new ticket submission
$ticket_errors  = [];
$ticket_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_ticket'])) {
    $subject  = mysqli_real_escape_string($conn, trim($_POST['subject']  ?? ''));
    $message  = mysqli_real_escape_string($conn, trim($_POST['message']  ?? ''));
    $priority = mysqli_real_escape_string($conn, $_POST['priority']      ?? 'medium');

    if (empty($subject) || empty($message)) {
        $ticket_errors[] = 'Please fill in all fields.';
    } else {
        $ok = mysqli_query($conn,
            "INSERT INTO support_tickets (user_id, subject, message, priority)
             VALUES ($user_id, '$subject', '$message', '$priority')"
        );
        if ($ok) {
            $ticket_success  = 'Your support ticket has been submitted successfully.';
            $tickets_result  = mysqli_query($conn,
                "SELECT id, subject, status, priority, created_at, updated_at
                 FROM support_tickets WHERE user_id=$user_id ORDER BY created_at DESC"
            );
        } else {
            $ticket_errors[] = 'Failed to submit ticket: ' . mysqli_error($conn);
        }
    }
}

function statusBadge($status) {
    $map = [
        'pending'=>'#f59e0b','confirmed'=>'#3b82f6','paid'=>'#3b82f6',
        'packed'=>'#3b82f6','shipped'=>'#8b5cf6',
        'out_for_delivery'=>'#f97316','delivered'=>'#22c55e','completed'=>'#22c55e',
        'cancelled'=>'#ef4444','refunded'=>'#94a3b8',
        'open'=>'#f59e0b','in_progress'=>'#3b82f6','resolved'=>'#22c55e','closed'=>'#94a3b8',
    ];
    $color = $map[$status] ?? '#888';
    return "<span style='display:inline-block;padding:.2rem .6rem;border-radius:20px;background:{$color}22;color:{$color};font-size:.72rem;font-weight:700;'>"
         . ucfirst(str_replace('_',' ',$status)) . "</span>";
}
function priorityBadge($p) {
    $c = ['low'=>'#22c55e','medium'=>'#f59e0b','high'=>'#ef4444'][$p] ?? '#888';
    return "<span style='display:inline-block;padding:.2rem .6rem;border-radius:20px;background:{$c}22;color:{$c};font-size:.72rem;font-weight:700;'>" . ucfirst($p) . "</span>";
}

$page_title = 'My Dashboard — REVIBE';
require_once 'includes/header.php';
?>

<style>
body.dark { background: #0f172a; }
body.dark .priv-heading       { color: #e2e8f0 !important; }
body.dark .priv-subheading    { color: #64748b !important; }
body.dark .priv-card          { background: #1e293b !important; box-shadow: 0 2px 12px rgba(0,0,0,.3) !important; }
body.dark .priv-card-head span { color: #64748b !important; }
body.dark .priv-td            { color: #cbd5e1; border-color: #334155 !important; }
body.dark .priv-td strong     { color: #e2e8f0; }
body.dark .priv-td-muted      { color: #64748b !important; }
body.dark .priv-empty         { color: #475569 !important; }
body.dark .priv-ticket-border { border-color: #334155 !important; }
body.dark .priv-ticket-label  { color: #94a3b8 !important; }
body.dark .priv-form-input    { background: #0f172a !important; border-color: #334155 !important; color: #e2e8f0 !important; }
body.dark .priv-form-input:focus { border-color: #c8f04a !important; }
</style>

<main style="max-width:1100px;margin:40px auto;padding:0 1.5rem;" id="top">

    <div style="margin-bottom:2rem;">
        <h1 class="priv-heading" style="font-size:1.5rem;font-weight:900;margin:0 0 4px;">My Private Dashboard</h1>
        <p class="priv-subheading" style="color:#888;font-size:.88rem;margin:0;">Your orders, payments and support tickets</p>
    </div>

    <?php
    $card  = 'background:#fff;border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,.06);margin-bottom:1.6rem;overflow:hidden;';
    $thead = 'background:#1a1a1a;color:#c8f04a;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;';
    $th    = 'padding:.8rem 1rem;text-align:left;white-space:nowrap;';
    $td    = 'padding:.8rem 1rem;font-size:.86rem;vertical-align:middle;border-bottom:1px solid #f5f5f5;';
    ?>

    <!-- ── Orders ── -->
    <div class="priv-card" style="<?= $card ?>">
        <div class="priv-card-head" style="padding:1.2rem 1.4rem;border-bottom:2px solid #c8f04a;display:flex;align-items:center;justify-content:space-between;">
            <h2 class="priv-heading" style="font-size:1rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;margin:0;">Order History</h2>
            <span style="font-size:.8rem;color:#888;"><?= mysqli_num_rows($orders_result) ?> order(s)</span>
        </div>
        <?php if (mysqli_num_rows($orders_result) > 0): ?>
        <table style="width:100%;border-collapse:collapse;">
            <thead><tr style="<?= $thead ?>">
                <th style="<?= $th ?>">Order #</th>
                <th style="<?= $th ?>">Items</th>
                <th style="<?= $th ?>">Total</th>
                <th style="<?= $th ?>">Status</th>
                <th style="<?= $th ?>">Date</th>
                <th style="<?= $th ?>">Track</th>
            </tr></thead>
            <tbody>
            <?php while ($o = mysqli_fetch_assoc($orders_result)): ?>
            <tr>
                <td class="priv-td" style="<?= $td ?>"><strong>#<?= $o['id'] ?></strong></td>
                <td class="priv-td" style="<?= $td ?>"><?= $o['item_count'] ?> item(s)</td>
                <td class="priv-td" style="<?= $td ?>">RM <?= number_format($o['total_amount'], 2) ?></td>
                <td class="priv-td" style="<?= $td ?>" id="order-status-<?= $o['id'] ?>"><?= statusBadge($o['display_status']) ?></td>
                <td class="priv-td priv-td-muted" style="<?= $td ?>;color:#888;"><?= date('d M Y', strtotime($o['created_at'])) ?></td>
                <td class="priv-td" style="<?= $td ?>">
                    <a href="/revibe/order_status.php?order_id=<?= $o['id'] ?>"
                       style="font-size:.78rem;font-weight:700;color:#1a1a1a;text-decoration:none;background:#c8f04a;padding:.25rem .7rem;border-radius:6px;">
                        Track →
                    </a>
                </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="priv-empty" style="padding:2.5rem;text-align:center;color:#aaa;font-size:.9rem;">You have no orders yet.</div>
        <?php endif; ?>
    </div>

    <!-- ── Payments ── -->
    <div class="priv-card" style="<?= $card ?>">
        <div class="priv-card-head" style="padding:1.2rem 1.4rem;border-bottom:2px solid #c8f04a;display:flex;align-items:center;justify-content:space-between;">
            <h2 class="priv-heading" style="font-size:1rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;margin:0;">Payment History</h2>
            <span style="font-size:.8rem;color:#888;"><?= mysqli_num_rows($payments_result) ?> payment(s)</span>
        </div>
        <?php if (mysqli_num_rows($payments_result) > 0): ?>
        <table style="width:100%;border-collapse:collapse;">
            <thead><tr style="<?= $thead ?>">
                <th style="<?= $th ?>">Ref</th>
                <th style="<?= $th ?>">Order</th>
                <th style="<?= $th ?>">Amount</th>
                <th style="<?= $th ?>">Method</th>
                <th style="<?= $th ?>">Status</th>
                <th style="<?= $th ?>">Date</th>
            </tr></thead>
            <tbody>
            <?php while ($p = mysqli_fetch_assoc($payments_result)): ?>
            <tr>
                <td class="priv-td" style="<?= $td ?>;font-family:monospace;font-size:.78rem;"><?= htmlspecialchars($p['transaction_ref'] ?? '—') ?></td>
                <td class="priv-td" style="<?= $td ?>">#<?= $p['order_id'] ?></td>
                <td class="priv-td" style="<?= $td ?>">RM <?= number_format($p['amount'], 2) ?></td>
                <td class="priv-td" style="<?= $td ?>"><?= ucwords(str_replace('_',' ', $p['payment_method'])) ?></td>
                <td class="priv-td" style="<?= $td ?>"><?= statusBadge($p['payment_status']) ?></td>
                <td class="priv-td priv-td-muted" style="<?= $td ?>;color:#888;"><?= date('d M Y', strtotime($p['created_at'])) ?></td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="priv-empty" style="padding:2.5rem;text-align:center;color:#aaa;font-size:.9rem;">No payment records found.</div>
        <?php endif; ?>
    </div>

    <!-- ── Support Tickets ── -->
    <div class="priv-card" style="<?= $card ?>" id="tickets">
        <div class="priv-card-head" style="padding:1.2rem 1.4rem;border-bottom:2px solid #c8f04a;">
            <h2 class="priv-heading" style="font-size:1rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;margin:0;">Support Tickets</h2>
        </div>
        <div style="padding:1.4rem;">

            <?php if (!empty($ticket_errors)): ?>
            <div style="background:#fdecea;color:#c62828;padding:.75rem 1rem;border-radius:8px;font-size:.85rem;margin-bottom:1rem;">
                <?php foreach ($ticket_errors as $e): ?><p style="margin:0 0 2px;">• <?= htmlspecialchars($e) ?></p><?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?php if ($ticket_success): ?>
            <div style="background:#dcfce7;color:#166534;padding:.75rem 1rem;border-radius:8px;font-size:.85rem;margin-bottom:1rem;">
                ✓ <?= htmlspecialchars($ticket_success) ?>
            </div>
            <?php endif; ?>

            <!-- New ticket form -->
            <div class="priv-ticket-border" style="padding-bottom:1.4rem;margin-bottom:1.4rem;border-bottom:1.5px solid #f0f0f0;">
                <h3 class="priv-ticket-label" style="font-size:.82rem;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:#888;margin:0 0 1rem;">Open a New Ticket</h3>
                <form method="POST">
                    <?php
                    $fi = 'width:100%;padding:.65rem .9rem;border:1.5px solid #ddd;border-radius:8px;font-size:.9rem;font-family:inherit;outline:none;box-sizing:border-box;';
                    $fl = 'display:block;font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#444;margin-bottom:.3rem;';
                    ?>
                    <div style="margin-bottom:.9rem;">
                        <label class="priv-ticket-label" style="<?= $fl ?>">Subject</label>
                        <input type="text" name="subject" required placeholder="Briefly describe your issue"
                               class="priv-form-input" style="<?= $fi ?>" onfocus="this.style.borderColor='#c8f04a'" onblur="this.style.borderColor=''">
                    </div>
                    <div style="margin-bottom:.9rem;">
                        <label class="priv-ticket-label" style="<?= $fl ?>">Message</label>
                        <textarea name="message" rows="3" required placeholder="Describe your issue in detail"
                                  class="priv-form-input" style="<?= $fi ?>resize:vertical;" onfocus="this.style.borderColor='#c8f04a'" onblur="this.style.borderColor=''"></textarea>
                    </div>
                    <div style="margin-bottom:1rem;">
                        <label class="priv-ticket-label" style="<?= $fl ?>">Priority</label>
                        <select name="priority" class="priv-form-input" style="<?= $fi ?>">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                        </select>
                    </div>
                    <button type="submit" name="submit_ticket"
                            style="padding:.7rem 1.6rem;background:#1a1a1a;color:#c8f04a;border:none;border-radius:10px;font-size:.88rem;font-weight:700;cursor:pointer;font-family:inherit;">
                        Submit Ticket
                    </button>
                </form>
            </div>

            <!-- Ticket list -->
            <?php if ($tickets_result && mysqli_num_rows($tickets_result) > 0): ?>
            <table style="width:100%;border-collapse:collapse;">
                <thead><tr style="<?= $thead ?>">
                    <th style="<?= $th ?>">#</th>
                    <th style="<?= $th ?>">Subject</th>
                    <th style="<?= $th ?>">Priority</th>
                    <th style="<?= $th ?>">Status</th>
                    <th style="<?= $th ?>">Submitted</th>
                    <th style="<?= $th ?>">Updated</th>
                </tr></thead>
                <tbody>
                <?php while ($t = mysqli_fetch_assoc($tickets_result)): ?>
                <tr>
                    <td class="priv-td" style="<?= $td ?>"><?= $t['id'] ?></td>
                    <td class="priv-td" style="<?= $td ?>"><?= htmlspecialchars($t['subject']) ?></td>
                    <td class="priv-td" style="<?= $td ?>"><?= priorityBadge($t['priority']) ?></td>
                    <td class="priv-td" style="<?= $td ?>"><?= statusBadge($t['status']) ?></td>
                    <td class="priv-td priv-td-muted" style="<?= $td ?>;color:#888;"><?= date('d M Y', strtotime($t['created_at'])) ?></td>
                    <td class="priv-td priv-td-muted" style="<?= $td ?>;color:#888;"><?= date('d M Y', strtotime($t['updated_at'])) ?></td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="priv-empty" style="text-align:center;color:#aaa;font-size:.9rem;padding:1.5rem 0;">No support tickets yet.</div>
            <?php endif; ?>
        </div>
    </div>

</main>

<script>
const STATUS_COLORS = {
    pending:'#f59e0b', confirmed:'#3b82f6', paid:'#3b82f6',
    packed:'#3b82f6', shipped:'#8b5cf6',
    out_for_delivery:'#f97316', delivered:'#22c55e', completed:'#22c55e',
    cancelled:'#ef4444', refunded:'#94a3b8'
};

function statusBadge(status) {
    const color = STATUS_COLORS[status] || '#888';
    const label = status.replace(/_/g,' ').replace(/\b\w/g, c => c.toUpperCase());
    return `<span style="display:inline-block;padding:.2rem .6rem;border-radius:20px;background:${color}22;color:${color};font-size:.72rem;font-weight:700;">${label}</span>`;
}

async function pollOrderStatuses() {
    try {
        const res = await fetch('/revibe/api/orders_api.php');
        if (!res.ok) return;
        const data = await res.json();
        for (const [orderId, status] of Object.entries(data)) {
            const cell = document.getElementById('order-status-' + orderId);
            if (cell) cell.innerHTML = statusBadge(status);
        }
    } catch (e) {}
}

setInterval(pollOrderStatuses, 10000);
</script>

<?php require_once 'includes/footer.php'; ?>
