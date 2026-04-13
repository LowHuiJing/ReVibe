<?php
require_once 'includes/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$page_title = 'FAQ — REVIBE';
require_once 'includes/header.php';
?>

<style>
  .faq-wrap{max-width:980px;margin:32px auto 64px;padding:0 16px;font-family:'Montserrat',sans-serif;}
  .faq-hero{background:#1a1a1a;border-radius:18px;padding:22px 22px;color:#fff;display:flex;align-items:flex-start;justify-content:space-between;gap:14px;flex-wrap:wrap;}
  .faq-hero h1{margin:0;color:#c8f04a;font-size:1.25rem;font-weight:900;letter-spacing:.5px;}
  .faq-hero p{margin:6px 0 0;color:#94a3b8;font-size:.9rem;max-width:60ch;line-height:1.45;}
  .faq-hero a{color:#c8f04a;text-decoration:none;font-weight:900;}
  .faq-actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center;}
  .faq-btn{display:inline-flex;align-items:center;justify-content:center;padding:.55rem 1rem;border-radius:10px;background:#fff;color:#1a1a1a;border:1.5px solid #ddd;text-decoration:none;font-weight:900;font-size:.82rem;cursor:pointer;white-space:nowrap;}
  .faq-btn:hover{border-color:#c8f04a;}
  .faq-btn-dark{background:#1a1a1a;color:#c8f04a;border-color:#1a1a1a;}
  .faq-btn-dark:hover{filter:brightness(1.05);}

  .faq-grid{display:grid;grid-template-columns:1fr;gap:14px;margin-top:18px;}
  .faq-card{background:#fff;border:1px solid #eef2f7;border-radius:18px;box-shadow:0 2px 10px rgba(0,0,0,.06);overflow:hidden;}
  .faq-section{padding:16px 18px;border-bottom:1px solid #eef2f7;}
  .faq-section:last-child{border-bottom:none;}
  .faq-section h2{margin:0 0 10px;font-size:.82rem;font-weight:900;letter-spacing:2px;text-transform:uppercase;color:#64748b;}

  .faq-item{border:1px solid #eef2f7;border-radius:14px;overflow:hidden;margin-bottom:10px;background:#fff;}
  .faq-q{width:100%;text-align:left;display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 14px;border:none;background:#fff;cursor:pointer;}
  .faq-q:focus-visible{outline:3px solid #c8f04a;outline-offset:2px;}
  .faq-q strong{font-size:.92rem;font-weight:900;color:#111827;line-height:1.35;}
  .faq-q .faq-icon{width:28px;height:28px;border-radius:10px;border:1.5px solid #e5e7eb;display:flex;align-items:center;justify-content:center;color:#111827;font-weight:900;flex-shrink:0;}
  .faq-a{display:none;padding:0 14px 14px;color:#475569;font-size:.9rem;line-height:1.55;}
  .faq-a p{margin:10px 0 0;}
  .faq-a ul{margin:10px 0 0;padding-left:18px;}
  .faq-a li{margin:6px 0;}
  .faq-a a{color:#111827;text-decoration:none;font-weight:900;border-bottom:2px solid #c8f04a;}
  .faq-a a:hover{opacity:.85;}

  .faq-note{margin-top:14px;color:#94a3b8;font-size:.82rem;}
  @media(max-width:600px){.faq-hero{padding:18px;}.faq-actions{width:100%;}}
</style>

<main class="faq-wrap">
  <div class="faq-hero">
    <div>
      <h1>FAQ</h1>
      <p>
        Everything you need to know about buying and selling second-hand and new items on REVIBE.
        For order updates, use <a href="/revibe/order_status.php">Order Status</a>.
      </p>
    </div>
    <div class="faq-actions">
      <a class="faq-btn" href="/revibe/products.php">Browse Products</a>
      <a class="faq-btn" href="/revibe/signup.php">Create Account</a>
      <a class="faq-btn faq-btn-dark" href="/revibe/signin.php">Sign In</a>
    </div>
  </div>

  <div class="faq-grid">
    <div class="faq-card">
      <div class="faq-section">
        <h2>Buying</h2>

        <div class="faq-item">
          <button class="faq-q" type="button">
            <strong>How do I place an order?</strong>
            <span class="faq-icon">+</span>
          </button>
          <div class="faq-a">
            <p>Add items to your cart, then proceed to checkout. After payment, you will receive an order slip and can track delivery.</p>
            <p>Start here: <a href="/revibe/products.php">Products</a>.</p>
          </div>
        </div>

        <div class="faq-item">
          <button class="faq-q" type="button">
            <strong>What is “PreLoved” vs “New”?</strong>
            <span class="faq-icon">+</span>
          </button>
          <div class="faq-a">
            <p><strong>PreLoved</strong> items are second-hand listings from sellers. <strong>New</strong> items are listed as brand new by the seller.</p>
            <p>Always read the item description and photos carefully before buying.</p>
          </div>
        </div>

        <div class="faq-item">
          <button class="faq-q" type="button">
            <strong>Is my payment secure?</strong>
            <span class="faq-icon">+</span>
          </button>
          <div class="faq-a">
            <p>REVIBE supports multiple payment methods (card, online banking, e-wallet). Your checkout will confirm the total and generate an order confirmation.</p>
          </div>
        </div>
      </div>

      <div class="faq-section">
        <h2>Selling</h2>

        <div class="faq-item">
          <button class="faq-q" type="button">
            <strong>How do I list an item for sale?</strong>
            <span class="faq-icon">+</span>
          </button>
          <div class="faq-a">
            <p>Go to your seller dashboard and create a listing with clear photos, an honest description, and the correct stock quantity.</p>
            <p>Seller tools: <a href="/revibe/product_management.php">My Products</a>.</p>
          </div>
        </div>

        <div class="faq-item">
          <button class="faq-q" type="button">
            <strong>What photos should I upload?</strong>
            <span class="faq-icon">+</span>
          </button>
          <div class="faq-a">
            <p>Use bright, clear photos. Include front/back, close-ups of details, brand labels, and any flaws. Listings with accurate photos sell faster and reduce returns.</p>
          </div>
        </div>

        <div class="faq-item">
          <button class="faq-q" type="button">
            <strong>What happens when my item is sold?</strong>
            <span class="faq-icon">+</span>
          </button>
          <div class="faq-a">
            <p>After payment, the order is confirmed. Stock quantity is reduced automatically and delivery tracking is generated.</p>
          </div>
        </div>
      </div>

      <div class="faq-section">
        <h2>Shipping & Delivery</h2>

        <div class="faq-item">
          <button class="faq-q" type="button">
            <strong>How do I track my delivery?</strong>
            <span class="faq-icon">+</span>
          </button>
          <div class="faq-a">
            <p>Use your order number in the tracking page to see your latest delivery status and tracking number.</p>
            <p><a href="/revibe/order_status.php">Track Order</a>.</p>
          </div>
        </div>

        <div class="faq-item">
          <button class="faq-q" type="button">
            <strong>How long does shipping take?</strong>
            <span class="faq-icon">+</span>
          </button>
          <div class="faq-a">
            <p>Delivery time depends on your location and courier. Your order page will show an estimated delivery date when available.</p>
          </div>
        </div>
      </div>

      <div class="faq-section">
        <h2>Returns & Refunds</h2>

        <div class="faq-item">
          <button class="faq-q" type="button">
            <strong>Can I request a return?</strong>
            <span class="faq-icon">+</span>
          </button>
          <div class="faq-a">
            <p>Returns depend on the item condition and your return reason. If your order is delivered, you may see a “Request Return / Refund” option.</p>
            <p>Returns portal: <a href="/revibe/return-request.php">Return Request</a>.</p>
          </div>
        </div>

        <div class="faq-item">
          <button class="faq-q" type="button">
            <strong>What reasons are accepted?</strong>
            <span class="faq-icon">+</span>
          </button>
          <div class="faq-a">
            <p>Common reasons include defective items, wrong item received, or not as described. Provide clear details and photos to speed up review.</p>
          </div>
        </div>
      </div>

      <div class="faq-section">
        <h2>Account & Safety</h2>

        <div class="faq-item">
          <button class="faq-q" type="button">
            <strong>How do I reset my password?</strong>
            <span class="faq-icon">+</span>
          </button>
          <div class="faq-a">
            <p>Use the reset flow from the sign-in screen.</p>
            <p><a href="/revibe/forgot_password.php">Forgot Password</a>.</p>
          </div>
        </div>

        <div class="faq-item">
          <button class="faq-q" type="button">
            <strong>How do I report inappropriate messages?</strong>
            <span class="faq-icon">+</span>
          </button>
          <div class="faq-a">
            <p>You can block users in chat. If you receive abusive content, take screenshots and contact support.</p>
          </div>
        </div>

        <div class="faq-item">
          <button class="faq-q" type="button">
            <strong>What items are not allowed?</strong>
            <span class="faq-icon">+</span>
          </button>
          <div class="faq-a">
            <p>Listings must follow local laws and platform rules. Prohibited items include illegal goods, dangerous items, and misleading listings.</p>
          </div>
        </div>

        <div class="faq-note">
          Need help? Contact support via your dashboard or check your notifications for updates.
        </div>
      </div>
    </div>
  </div>
</main>

<script>
  (function(){
    const items = document.querySelectorAll('.faq-item');
    items.forEach(item => {
      const btn = item.querySelector('.faq-q');
      const ans = item.querySelector('.faq-a');
      const icon = item.querySelector('.faq-icon');
      btn.addEventListener('click', () => {
        const open = ans.style.display === 'block';
        // close others
        items.forEach(i => {
          i.querySelector('.faq-a').style.display = 'none';
          i.querySelector('.faq-icon').textContent = '+';
        });
        if (!open) {
          ans.style.display = 'block';
          icon.textContent = '−';
        }
      });
    });
  })();
</script>

<?php require_once 'includes/footer.php'; ?>

