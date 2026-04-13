<?php
session_start();
$me = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$product_id = (int)($_GET['product_id'] ?? 0);

if (!$product_id) {
    header('Location: products.php');
    exit;
}

require_once 'includes/db.php';

// Get product details with primary image and category name
$stmt = mysqli_prepare($conn,
    "SELECT p.*, c.name AS category,
            (SELECT image_url FROM product_images pi
             WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1) AS image
     FROM products p
     LEFT JOIN categories c ON c.id = p.category_id
     WHERE p.id = ?"
);
mysqli_stmt_bind_param($stmt, 'i', $product_id);
mysqli_stmt_execute($stmt);
$product = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$product) {
    header('Location: products.php');
    exit;
}

$is_own_product = $me > 0 && (int)$product['seller_id'] === $me;

$page_title = 'Reviews — ' . $product['name'];
require_once 'includes/header.php';
?>

<style>
.reviews-page {
    max-width: 800px;
    margin: 32px auto;
    padding: 0 16px;
    font-family: 'Segoe UI', sans-serif;
}

/* Header */
.reviews-hero {
    background: linear-gradient(135deg, #1d4ed8, #3b82f6);
    border-radius: 16px;
    padding: 24px;
    color: #fff;
    display: flex;
    align-items: center;
    gap: 20px;
    margin-bottom: 28px;
}
.reviews-hero-img {
    width: 80px; height: 80px;
    border-radius: 12px;
    object-fit: cover;
    background: rgba(255,255,255,.2);
    display: flex; align-items: center; justify-content: center;
    font-size: 2rem; flex-shrink: 0;
}
.reviews-hero-info h2 { margin: 0 0 4px; font-size: 1.2rem; font-weight: 700; }
.reviews-hero-info p  { margin: 0; font-size: .9rem; opacity: .85; }

/* Rating summary */
.rating-summary {
    background: #fff;
    border-radius: 14px;
    border: 1px solid #e2e8f0;
    padding: 20px 24px;
    display: flex;
    align-items: center;
    gap: 24px;
    margin-bottom: 24px;
    box-shadow: 0 2px 12px rgba(59,130,246,.06);
}
.rating-big {
    text-align: center; flex-shrink: 0;
}
.rating-big .number {
    font-size: 3rem; font-weight: 800; color: #1d4ed8; line-height: 1;
}
.rating-big .stars { font-size: 1.2rem; margin: 4px 0; }
.rating-big .total { font-size: .8rem; color: #64748b; }

.rating-bars { flex: 1; }
.rating-bar-row {
    display: flex; align-items: center; gap: 8px;
    margin-bottom: 5px; font-size: .82rem; color: #64748b;
}
.rating-bar-row span { width: 12px; text-align: right; flex-shrink: 0; }
.bar-track {
    flex: 1; height: 6px; background: #e2e8f0; border-radius: 999px; overflow: hidden;
}
.bar-fill {
    height: 100%; background: linear-gradient(90deg, #3b82f6, #1d4ed8);
    border-radius: 999px; transition: width .5s ease;
}
.bar-count { width: 20px; text-align: right; flex-shrink: 0; }

/* Write review */
.write-review {
    background: #fff;
    border-radius: 14px;
    border: 1px solid #e2e8f0;
    padding: 20px 24px;
    margin-bottom: 24px;
    box-shadow: 0 2px 12px rgba(59,130,246,.06);
}
.write-review h3 { margin: 0 0 16px; font-size: 1rem; font-weight: 700; color: #1e293b; }

.star-picker { display: flex; gap: 6px; margin-bottom: 14px; }
.star-picker span {
    font-size: 1.8rem; cursor: pointer;
    opacity: 0.3; transition: opacity .15s, transform .15s;
}
.star-picker span.active { opacity: 1; transform: scale(1.1); }

.review-textarea {
    width: 100%; border: 1.5px solid #e2e8f0;
    border-radius: 10px; padding: 12px;
    font-size: .9rem; font-family: inherit;
    resize: none; outline: none; color: #1e293b;
    box-sizing: border-box; transition: border-color .2s;
}
.review-textarea:focus { border-color: #3b82f6; }

.review-actions {
    display: flex; align-items: center;
    justify-content: space-between; margin-top: 12px;
}
.review-img-label {
    display: flex; align-items: center; gap: 6px;
    color: #3b82f6; font-size: .85rem; font-weight: 600;
    cursor: pointer;
}
.submit-review-btn {
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    color: #fff; border: none; border-radius: 10px;
    padding: 10px 24px; font-size: .9rem; font-weight: 600;
    cursor: pointer; transition: opacity .15s;
}
.submit-review-btn:hover { opacity: .88; }

.img-preview {
    margin-top: 10px; display: none;
}
.img-preview img {
    max-width: 120px; border-radius: 8px;
    border: 2px solid #dbeafe;
}

/* Reviews list */
.reviews-list { display: flex; flex-direction: column; gap: 14px; }

.review-card {
    background: #fff;
    border-radius: 14px;
    border: 1px solid #e2e8f0;
    padding: 18px 20px;
    box-shadow: 0 2px 8px rgba(59,130,246,.05);
}
.review-card-header {
    display: flex; align-items: center;
    justify-content: space-between; margin-bottom: 10px;
}
.reviewer-info { display: flex; align-items: center; gap: 10px; }
.reviewer-avatar {
    width: 38px; height: 38px; border-radius: 50%;
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    color: #fff; display: flex; align-items: center;
    justify-content: center; font-weight: 700; font-size: .95rem;
}
.reviewer-name { font-weight: 600; font-size: .9rem; color: #1e293b; }
.reviewer-date { font-size: .75rem; color: #94a3b8; margin-top: 1px; }
.review-stars  { font-size: 1rem; }
.review-text   { font-size: .88rem; color: #475569; line-height: 1.6; margin-bottom: 10px; }
.review-image  { max-width: 180px; border-radius: 8px; cursor: pointer; }

.no-reviews {
    text-align: center; padding: 40px;
    color: #94a3b8; font-size: .9rem;
}

/* Dark mode */
body.dark .rating-summary,
body.dark .write-review,
body.dark .review-card { background: #1e293b; border-color: #334155; }
body.dark .write-review h3,
body.dark .reviewer-name { color: #e2e8f0; }
body.dark .review-text { color: #94a3b8; }
body.dark .review-textarea { background: #0f172a; border-color: #334155; color: #e2e8f0; }
body.dark .rating-big .number { color: #93c5fd; }
</style>

<div class="reviews-page">

    <!-- Product hero -->
    <div class="reviews-hero">
        <?php if ($product['image']): ?>
            <img src="/revibe/<?= htmlspecialchars($product['image']) ?>"
                 class="reviews-hero-img" alt="product"
                 onerror="this.style.display='none'">
        <?php else: ?>
            <div class="reviews-hero-img">📦</div>
        <?php endif; ?>
        <div class="reviews-hero-info">
            <h2><?= htmlspecialchars($product['name']) ?></h2>
            <p>RM <?= number_format($product['price'], 2) ?> · <?= htmlspecialchars($product['category']) ?></p>
        </div>
    </div>

    <!-- Rating summary -->
    <div class="rating-summary" id="ratingSummary">
        <div class="rating-big">
            <div class="number" id="avgRating">–</div>
            <div class="stars" id="avgStars">⭐⭐⭐⭐⭐</div>
            <div class="total" id="totalReviews">0 reviews</div>
        </div>
        <div class="rating-bars" id="ratingBars">
            <?php for ($i = 5; $i >= 1; $i--): ?>
            <div class="rating-bar-row">
                <span><?= $i ?></span>
                <div class="bar-track">
                    <div class="bar-fill" id="bar<?= $i ?>" style="width:0%"></div>
                </div>
                <span class="bar-count" id="count<?= $i ?>">0</span>
            </div>
            <?php endfor; ?>
        </div>
    </div>

    <!-- Write review -->
    <?php if ($me <= 0): ?>
    <div class="write-review" style="text-align:center;padding:1.5rem;">
        <p style="color:#64748b;margin:0 0 1rem;font-size:.92rem;">Sign in to leave a review.</p>
        <a href="/revibe/signin.php?next=<?= urlencode('/revibe/reviews.php?product_id=' . $product_id) ?>"
           style="display:inline-block;padding:.65rem 1.6rem;background:#1a1a1a;color:#c8f04a;border-radius:10px;font-weight:700;text-decoration:none;font-size:.88rem;">
            Sign In
        </a>
    </div>
    <?php elseif ($is_own_product): ?>
    <div class="write-review" style="text-align:center;padding:1.5rem;">
        <p style="color:#92400e;background:#fffbeb;border:1.5px solid #f59e0b;border-radius:10px;padding:.8rem 1rem;margin:0;font-size:.88rem;font-weight:600;">
            This is your listing — you cannot review your own product.
        </p>
    </div>
    <?php else: ?>
    <div class="write-review">
        <h3>✍️ Write a Review</h3>
        <div class="star-picker" id="starPicker">
            <span onclick="setStar(1)">⭐</span>
            <span onclick="setStar(2)">⭐</span>
            <span onclick="setStar(3)">⭐</span>
            <span onclick="setStar(4)">⭐</span>
            <span onclick="setStar(5)">⭐</span>
        </div>
        <textarea class="review-textarea" id="reviewText" rows="4"
            placeholder="Share your experience with this product…"></textarea>
        <div class="review-actions">
            <label class="review-img-label">
                📷 Add Photo
                <input type="file" id="reviewImg" accept="image/*" style="display:none" onchange="previewImg(this)">
            </label>
            <button class="submit-review-btn" onclick="submitReview()">Submit Review</button>
        </div>
        <div class="img-preview" id="imgPreview">
            <img id="imgPreviewEl" src="" alt="preview">
        </div>
    </div>
    <?php endif; ?>

    <!-- Reviews list -->
    <div class="reviews-list" id="reviewsList">
        <div class="no-reviews">⏳ Loading reviews…</div>
    </div>

</div>

<script>
const PRODUCT_ID = <?= $product_id ?>;
const REVIEW_API = '/revibe/api/review_api.php';
const MY_ID = <?= $me ?>;
let selectedRating = 0;
let uploadedImageUrl = null;

window.onload = function() { loadReviews(); };

async function loadReviews() {
    const data = await fetch(REVIEW_API + '?action=get_reviews&product_id=' + PRODUCT_ID)
        .then(r => r.json()).catch(() => ({reviews:[], avg_rating:0, total:0}));

    document.getElementById('avgRating').textContent = data.avg_rating || '0';
    document.getElementById('totalReviews').textContent = data.total + ' review' + (data.total != 1 ? 's' : '');

    const counts = {1:0, 2:0, 3:0, 4:0, 5:0};
    data.reviews.forEach(r => counts[r.rating]++);
    for (let i = 1; i <= 5; i++) {
        const pct = data.total > 0 ? (counts[i] / data.total * 100) : 0;
        document.getElementById('bar' + i).style.width = pct + '%';
        document.getElementById('count' + i).textContent = counts[i];
    }

    const list = document.getElementById('reviewsList');
    if (!data.reviews || !data.reviews.length) {
        list.innerHTML = '<div class="no-reviews">No reviews yet. Be the first to review!</div>';
        return;
    }

    list.innerHTML = data.reviews.map(function(r) {
        const stars = '⭐'.repeat(parseInt(r.rating));
        const name  = escHtml(r.buyer_name || 'User');
        const text  = escHtml(r.review_text || '');
        const date  = formatDate(r.created_at);
        const isMe  = r.user_id == MY_ID;

        const editBtn = isMe
            ? '<button onclick="startEdit(' + r.id + ')" style="background:none;border:1px solid #e2e8f0;border-radius:8px;padding:4px 10px;font-size:.75rem;cursor:pointer;color:#64748b;">✏️ Edit</button>'
            : '';

        const editBox = isMe
            ? '<div id="review-edit-' + r.id + '" style="display:none;margin-top:8px;">' +
              '<textarea class="review-textarea" id="edit-input-' + r.id + '" rows="3">' + text + '</textarea>' +
              '<div style="display:flex;gap:8px;margin-top:8px;justify-content:flex-end;">' +
              '<button onclick="cancelEdit(' + r.id + ')" style="background:none;border:1px solid #e2e8f0;border-radius:8px;padding:6px 14px;font-size:.82rem;cursor:pointer;color:#64748b;">Cancel</button>' +
              '<button onclick="saveEdit(' + r.id + ')" style="background:linear-gradient(135deg,#3b82f6,#1d4ed8);color:#fff;border:none;border-radius:8px;padding:6px 14px;font-size:.82rem;cursor:pointer;">Save</button>' +
              '</div></div>'
            : '';

        const img = r.image_url
            ? '<img src="' + escHtml(r.image_url) + '" class="review-image" onclick="window.open(this.src)">'
            : '';

        return '<div class="review-card" id="review-' + r.id + '">' +
            '<div class="review-card-header">' +
            '<div class="reviewer-info">' +
            '<div class="reviewer-avatar">' + name[0].toUpperCase() + '</div>' +
            '<div><div class="reviewer-name">' + name + '</div>' +
            '<div class="reviewer-date">' + date + '</div></div>' +
            '</div>' +
            '<div style="display:flex;align-items:center;gap:10px;">' +
            '<div class="review-stars">' + stars + '</div>' + editBtn +
            '</div></div>' +
            '<div class="review-text" id="review-text-' + r.id + '">' + text + '</div>' +
            editBox + img + '</div>';
    }).join('');
}

function setStar(n) {
    selectedRating = n;
    document.querySelectorAll('#starPicker span').forEach(function(s, i) {
        s.classList.toggle('active', i < n);
    });
}

function previewImg(input) {
    const file = input.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = function(e) {
        document.getElementById('imgPreviewEl').src = e.target.result;
        document.getElementById('imgPreview').style.display = 'block';
    };
    reader.readAsDataURL(file);

    const formData = new FormData();
    formData.append('action', 'upload_image');
    formData.append('image', file);
    fetch(REVIEW_API, {method:'POST', body: formData})
        .then(r => r.json())
        .then(function(d) { if (d.url) uploadedImageUrl = d.url; });
}

async function submitReview() {
    if (!selectedRating) { showToast('Please select a star rating!'); return; }
    const text = document.getElementById('reviewText').value.trim();

    const body = new URLSearchParams({
        action:      'submit_review',
        product_id:  PRODUCT_ID,
        rating:      selectedRating,
        review_text: text,
        image_url:   uploadedImageUrl || ''
    });

    const res = await fetch(REVIEW_API, {method:'POST', body}).then(r => r.json());
    if (res.error) { showToast('❌ ' + res.error); return; }

    showToast('✅ Review submitted! Thank you.');
    document.getElementById('reviewText').value = '';
    document.getElementById('imgPreview').style.display = 'none';
    uploadedImageUrl = null;
    selectedRating = 0;
    document.querySelectorAll('#starPicker span').forEach(function(s) { s.classList.remove('active'); });
    loadReviews();
}

function startEdit(id) {
    document.getElementById('review-text-' + id).style.display = 'none';
    document.getElementById('review-edit-' + id).style.display = 'block';
}

function cancelEdit(id) {
    document.getElementById('review-text-' + id).style.display = 'block';
    document.getElementById('review-edit-' + id).style.display = 'none';
}

async function saveEdit(id) {
    const text = document.getElementById('edit-input-' + id).value.trim();
    if (!text) { showToast('Review cannot be empty!'); return; }

    const res = await fetch(REVIEW_API, {
        method: 'POST',
        body: new URLSearchParams({action:'edit_review', review_id:id, review_text:text})
    }).then(r => r.json());

    if (res.error) { showToast('❌ ' + res.error); return; }
    showToast('✅ Review updated!');
    loadReviews();
}

function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
function formatDate(dateStr) {
    return new Date(dateStr.replace(' ','T')).toLocaleDateString('en-MY', {
        year:'numeric', month:'short', day:'numeric'
    });
}
function showToast(msg) {
    const t = document.createElement('div');
    t.textContent = msg;
    Object.assign(t.style, {
        position:'fixed', bottom:'24px', left:'50%', transform:'translateX(-50%)',
        background:'#1e293b', color:'#fff', padding:'10px 20px',
        borderRadius:'999px', fontSize:'.88rem', zIndex:'9999',
        boxShadow:'0 4px 16px rgba(0,0,0,.2)'
    });
    document.body.appendChild(t);
    setTimeout(function() { t.remove(); }, 3000);
}
</script>

<?php require_once 'includes/footer.php'; ?>