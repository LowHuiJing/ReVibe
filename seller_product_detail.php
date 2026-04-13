<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'includes/db.php';
require_once 'includes/session.php';
require_once 'includes/auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();

checkSessionTimeout();
if (!isset($_SESSION['user_id'])) {
    header('Location: /revibe/signin.php');
    exit;
}

$seller_id = (int)$_SESSION['user_id'];

// ── Mode detection ─────────────────────────────────────────────────────────
$edit_id  = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$view_id  = isset($_GET['view']) ? (int)$_GET['view'] : 0;
$is_edit  = $edit_id > 0;
$is_view  = $view_id > 0;
$is_add   = !$is_edit && !$is_view;
$entry_id = $edit_id ?: $view_id;

// ── Page meta ──────────────────────────────────────────────────────────────
$page_title    = $is_view ? "Product Details"  : ($is_edit ? "Edit Product"  : "Add New Product");
$page_subtitle = $is_view
    ? "This product is sold out — view only"
    : ($is_edit ? "Changes will be sent for admin approval" : "Fill in the details and submit for approval");

// ── Fetch categories ───────────────────────────────────────────────────────
$cat_error      = null;
$all_categories = [];
$cat_result     = $conn->query("SELECT id, parent_id, name FROM categories ORDER BY parent_id ASC, name ASC");
if ($cat_result) {
    while ($row = $cat_result->fetch_assoc()) {
        $all_categories[] = $row;
    }
} else {
    $cat_error = "Could not load categories.";
}
$main_categories = array_filter($all_categories, fn($c) => $c['parent_id'] === null);

// ── Fetch existing product (edit / view mode) ──────────────────────────────
$product         = null;
$existing_images = [];
$page_error      = null;

if (!$is_add && $entry_id) {
    $sql  = "SELECT p.*, c.parent_id AS sub_parent_id
             FROM products p
             LEFT JOIN categories c ON p.category_id = c.id
             WHERE p.id = ? AND p.seller_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $entry_id, $seller_id);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$product) {
        $page_error = "Product not found or you do not have permission to access it.";
    } else {
        $img_stmt = $conn->prepare(
            "SELECT image_url, is_primary, sort_order
             FROM product_images
             WHERE product_id = ?
             ORDER BY sort_order ASC"
        );
        $img_stmt->bind_param("i", $entry_id);
        $img_stmt->execute();
        $img_res = $img_stmt->get_result();
        while ($row = $img_res->fetch_assoc()) {
            $existing_images[] = $row;
        }
        $img_stmt->close();
    }
}

// ── Handle POST (add / edit) ───────────────────────────────────────────────
$form_errors = [];
$toast_type  = null;
$toast_msg   = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_view) {
    $name        = trim($_POST['name']              ?? '');
    $main_id     = (int)($_POST['main_category_id'] ?? 0);
    $sub_id      = (int)($_POST['sub_category_id']  ?? 0);
    $category_id = $sub_id;
    $description = trim($_POST['description']       ?? '');
    $price       = (float)($_POST['price']          ?? 0);
    $condition   = trim($_POST['condition']         ?? '');
    $stock       = (int)($_POST['stock_quantity']   ?? 0);
    $kept_images = $_POST['kept_images']            ?? [];   // written by JS on submit

    // ── Validation ─────────────────────────────────────────────────────────
    if (!$name)        $form_errors['name']          = "Name is required.";
    if (!$main_id)     $form_errors['main_category'] = "Please select a main category.";
    if (!$sub_id)      $form_errors['categoryId']    = "Please select a subcategory.";
    if (!$condition)   $form_errors['condition']     = "Please select a condition.";
    if ($stock <= 0)   $form_errors['stock']         = "Stock must be at least 1.";
    if (!$description) $form_errors['description']   = "Description is required.";
    if ($price <= 0)   $form_errors['price']         = "Price must be greater than 0.";

    $has_new_images = isset($_FILES['images']) 
                      && $_FILES['images']['error'][0] === 0;
    if (empty($kept_images) && !$has_new_images) {
        $form_errors['images'] = "Please upload at least 1 photo.";
    }

    if (empty($form_errors)) {
        $status = "pending";

        // ── The upload folder is relative to THIS file (at project root) ──
        $upload_dir = "images/";
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

        if ($is_edit) {
            // ── UPDATE ────────────────────────────────────────────────────
            $check = $conn->prepare("SELECT id FROM products WHERE id = ? AND seller_id = ?");
            $check->bind_param("ii", $edit_id, $seller_id);
            $check->execute();
            if (!$check->get_result()->fetch_assoc()) {
                $toast_type = "error";
                $toast_msg  = "Product not found or unauthorized.";
            } else {
                $check->close();

                // FIX: type string was "sisdssii" — stock is int (i), not string (s)
                // Correct order: name(s) category_id(i) description(s) price(d) condition(s) stock(i) status(s) id(i)
                $upd = $conn->prepare(
                    "UPDATE products
                     SET name=?, category_id=?, description=?, price=?, `condition`=?, stock_quantity=?, status=?
                     WHERE id=?"
                );
                $upd->bind_param("sisdsisi", $name, $category_id, $description, $price, $condition, $stock, $status, $edit_id);

                if ($upd->execute()) {
                    // Step 1 — find what's currently saved
                    $ef = $conn->prepare("SELECT image_url FROM product_images WHERE product_id = ?");
                    $ef->bind_param("i", $edit_id);
                    $ef->execute();
                    $res = $ef->get_result();
                    $all_existing = [];
                    while ($r = $res->fetch_assoc()) {
                        $all_existing[] = $r['image_url'];
                    }
                    $res->free();
                    $ef->close();

                    // Step 2 — delete images the user removed
                    foreach (array_diff($all_existing, $kept_images) as $img_url) {
                        $fp = __DIR__ . '/' . basename($img_url);
                        if (file_exists($fp)) unlink($fp);
                        $del = $conn->prepare("DELETE FROM product_images WHERE product_id = ? AND image_url = ?");
                        $del->bind_param("is", $edit_id, $img_url);
                        $del->execute();
                        $del->close();
                    }

                    // Step 3 — re-order kept images
                    foreach ($kept_images as $sort_idx => $img_url) {
                        $is_primary = ($sort_idx === 0) ? 1 : 0;
                        $upd2 = $conn->prepare(
                            "UPDATE product_images SET sort_order=?, is_primary=? WHERE product_id=? AND image_url=?"
                        );
                        $upd2->bind_param("iiis", $sort_idx, $is_primary, $edit_id, $img_url);
                        $upd2->execute();
                        $upd2->close();
                    }

                    // Step 4 — upload new images
                    if ($has_new_images) {
                        $img_ins    = $conn->prepare(
                            "INSERT INTO product_images (product_id, image_url, is_primary, sort_order) VALUES (?, ?, ?, ?)"
                        );
                        $start_sort = count($kept_images);
                        $no_kept    = empty($kept_images);
                        $file_count = count($_FILES['images']['name']);

                        for ($i = 0; $i < $file_count; $i++) {
                            if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) continue;
                            $ext        = pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION);
                            $filename   = uniqid("img_", true) . "." . $ext;
                            $dest       = $upload_dir . $filename;
                            $img_url    = "images/" . $filename;
                            $sort_order = $start_sort + $i;
                            $is_primary = ($no_kept && $i === 0) ? 1 : 0;
                            if (move_uploaded_file($_FILES['images']['tmp_name'][$i], $dest)) {
                                $img_ins->bind_param("isii", $edit_id, $img_url, $is_primary, $sort_order);
                                $img_ins->execute();
                            }
                        }
                        $img_ins->close();
                    }

                    header("Location: product_management.php");
                    exit();

                } else {
                    $toast_type = "error";
                    $toast_msg  = "Update failed: " . $upd->error;
                }
                $upd->close();
            }

        } else {
            // ── INSERT ────────────────────────────────────────────────────
            $ins = $conn->prepare(
                "INSERT INTO products (seller_id, name, category_id, description, price, `condition`, stock_quantity, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $ins->bind_param("isisdsis", $seller_id, $name, $category_id, $description, $price, $condition, $stock, $status);

            if ($ins->execute()) {
                $new_id = $ins->insert_id;

                if ($has_new_images) {
                    $img_ins    = $conn->prepare(
                        "INSERT INTO product_images (product_id, image_url, is_primary, sort_order) VALUES (?, ?, ?, ?)"
                    );
                    $file_count = count($_FILES['images']['name']);
                    for ($i = 0; $i < $file_count; $i++) {
                        if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) continue;
                        $ext        = pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION);
                        $filename   = uniqid("img_", true) . "." . $ext;
                        $dest       = $upload_dir . $filename;
                        $img_url    = "images/" . $filename;
                        $is_primary = ($i === 0) ? 1 : 0;
                        $sort_order = $i;
                        if (move_uploaded_file($_FILES['images']['tmp_name'][$i], $dest)) {
                            $img_ins->bind_param("isii", $new_id, $img_url, $is_primary, $sort_order);
                            $img_ins->execute();
                        }
                    }
                    $img_ins->close();
                }

                header("Location: product_management.php");
                exit();

            } else {
                $toast_type = "error";
                $toast_msg  = "Insert failed: " . $ins->error;
            }
            $ins->close();
        }

    } else {
        $toast_type = "error";
        $toast_msg  = "Please fix the highlighted errors before submitting.";
    }

    // Re-populate form so user doesn't lose input on validation failure
    $product = [
        'name'           => $name ?? '',
        'category_id'    => $sub_id ?? 0,
        'sub_parent_id'  => $main_id ?? null,
        'description'    => $description ?? '',
        'price'          => $price       ?? '',
        'condition'      => $condition   ?? '',
        'stock_quantity' => $stock       ?? '',
    ];
    // Rebuild existing_images from kept_images so the grid still shows them
    $existing_images = [];
    foreach ($kept_images as $sort_idx => $img_url) {
        $existing_images[] = [
            'image_url'  => $img_url,
            'is_primary' => ($sort_idx === 0) ? 1 : 0,
            'sort_order' => $sort_idx,
        ];
    }
}

// ── Category pre-selection helpers ─────────────────────────────────────────
$conditions = [
    ['label' => 'New',      'value' => 'new'],
    ['label' => 'Like New', 'value' => 'like_new'],
    ['label' => 'Good',     'value' => 'good'],
    ['label' => 'Fair',     'value' => 'fair'],
    ['label' => 'Poor',     'value' => 'poor'],
];

$sel_category_id = $product['category_id']   ?? 0;
$sel_sub_parent  = $product['sub_parent_id'] ?? null;

if ($sel_sub_parent !== null) {
    $sel_main_id = $sel_sub_parent;
    $sel_sub_id  = $sel_category_id;
} else {
    $sel_main_id = 0;
    $sel_sub_id  = 0;
}

if ($sel_main_id) {
    $sub_categories = array_filter($all_categories, fn($c) => $c['parent_id'] == $sel_main_id);
} else {
    $sub_categories = [];
}

?>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
<style>
  *{box-sizing:border-box;}
  body{font-family:'Montserrat',sans-serif;background:#f4f6f8;color:#1a1a1a;}

  .pm-page{max-width:1200px;margin:2rem auto;padding:0 1.5rem;}

  .pm-header{background:#1a1a1a;color:#fff;padding:.9rem 1.2rem;border-radius:18px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:1.2rem;}
  .pm-header-left{display:flex;align-items:center;gap:10px;}
  .pm-icon{color:#c8f04a;}
  .pm-header h1{font-size:1.05rem;font-weight:900;margin:0;color:#c8f04a;letter-spacing:.5px;}
  .pm-subtitle{font-size:.78rem;color:#94a3b8;margin-top:2px;}

  .pm-back-btn{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;border:1.5px solid #444;color:#ccc;text-decoration:none;}
  .pm-back-btn:hover{border-color:#c8f04a;color:#c8f04a;}

  .btn-teal{display:inline-flex;align-items:center;gap:8px;padding:.55rem 1rem;border-radius:10px;background:#fff;color:#1a1a1a;border:1.5px solid #ddd;text-decoration:none;font-weight:900;font-size:.82rem;cursor:pointer;white-space:nowrap;}
  .btn-teal:hover{border-color:#c8f04a;}
  .btn-outline-teal{display:inline-flex;align-items:center;justify-content:center;padding:.55rem 1rem;border-radius:10px;background:#fff;color:#1a1a1a;border:1.5px solid #ddd;font-weight:900;cursor:pointer;}
  .btn-outline-teal:hover{border-color:#c8f04a;}

  .pm-toast{position:fixed;top:18px;right:18px;z-index:1000;display:flex;align-items:center;gap:10px;padding:.75rem 1rem;border-radius:12px;background:#fff;box-shadow:0 10px 30px rgba(0,0,0,.12);border:1px solid #eee;min-width:280px;}
  .pm-toast-success{border-left:4px solid #22c55e;}
  .pm-toast-error{border-left:4px solid #ef4444;}
  .pm-toast-close{margin-left:auto;background:none;border:none;cursor:pointer;color:#888;font-size:16px;line-height:1;}

  .pm-view-banner{background:#fff;border-radius:16px;padding:1rem 1.1rem;box-shadow:0 2px 10px rgba(0,0,0,.06);border:1px solid #eef2f7;margin-bottom:1.2rem;color:#334155;font-size:.85rem;display:flex;gap:10px;align-items:flex-start;}
  .pm-view-banner strong{color:#1a1a1a;}

  .pd-container{display:grid;grid-template-columns:1fr;gap:1rem;}
  .pd-card{background:#fff;border-radius:18px;padding:1.4rem 1.5rem;box-shadow:0 2px 10px rgba(0,0,0,.06);border:1px solid #eef2f7;}
  .pd-card h2{font-size:.95rem;font-weight:900;margin:0 0 1rem;letter-spacing:.5px;text-transform:uppercase;}
  .pd-photo-count{margin-left:8px;font-size:.75rem;font-weight:900;color:#64748b;}

  .pd-form-group{margin-bottom:1rem;}
  .pd-form-group label{display:block;font-size:.75rem;font-weight:900;text-transform:uppercase;letter-spacing:.5px;color:#444;margin-bottom:.35rem;}
  .pd-required{color:#ef4444;font-weight:900;}

  input[type="text"], input[type="number"], input[type="file"], select, textarea{
    width:100%;padding:.7rem .9rem;border:1.5px solid #ddd;border-radius:10px;font-size:.92rem;font-family:inherit;outline:none;background:#fff;
  }
  textarea{min-height:110px;resize:vertical;}
  input:focus, select:focus, textarea:focus{border-color:#c8f04a;}
  .pd-input-readonly{background:#f8fafc;color:#64748b;}
  .pd-input-error{border-color:#ef4444 !important;background:#fef2f2;}
  .pd-field-error{display:block;color:#b91c1c;font-size:.8rem;margin-top:.35rem;font-weight:800;}
  .pd-cat-hint{display:block;color:#92400e;font-size:.8rem;margin:.35rem 0;}

  .pd-grid-2{display:grid;grid-template-columns:repeat(2, minmax(0, 1fr));gap:1rem;}

  .pd-upload-box{border:2px dashed #cbd5e1;border-radius:16px;padding:1.4rem 1.2rem;text-align:center;cursor:pointer;background:#f8fafc;}
  .pd-upload-box:hover{border-color:#c8f04a;}
  .pd-upload-icon{font-size:40px;color:#1a1a1a;}
  .pd-upload-title{font-weight:900;margin:.6rem 0 .2rem;}
  .pd-upload-text{color:#64748b;font-size:.85rem;margin:0 0 .9rem;}

  .pd-img-grid{display:grid;grid-template-columns:repeat(5, minmax(0, 1fr));gap:.8rem;margin-top:1rem;}
  .pd-img-item{position:relative;border-radius:14px;overflow:hidden;border:1px solid #e2e8f0;background:#f1f5f9;aspect-ratio:1/1;}
  .pd-img-item img{width:100%;height:100%;object-fit:cover;display:block;}
  .pd-badge-cover{position:absolute;left:8px;top:8px;background:#1a1a1a;color:#c8f04a;border-radius:999px;padding:.2rem .55rem;font-size:.7rem;font-weight:900;}
  .pd-badge-saved{position:absolute;right:8px;top:8px;background:#fff;color:#1a1a1a;border-radius:999px;padding:.2rem .55rem;font-size:.7rem;font-weight:900;border:1px solid #e2e8f0;}
  .pd-img-actions{position:absolute;right:8px;bottom:8px;display:flex;gap:6px;}
  .pd-img-btn{width:34px;height:34px;border-radius:10px;border:none;background:rgba(0,0,0,.75);color:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;}
  .pd-img-btn.remove{background:rgba(239,68,68,.9);}

  @media(max-width:980px){.pd-img-grid{grid-template-columns:repeat(3, minmax(0, 1fr));}}
  @media(max-width:640px){
    .pm-page{padding:0 1rem;}
    .pd-grid-2{grid-template-columns:1fr;}
    .pd-img-grid{grid-template-columns:repeat(2, minmax(0, 1fr));}
  }
</style>

<?php if ($page_error): ?>
<div class="pm-page">
  <div class="pm-empty" style="height:60vh">
    <span class="material-symbols-outlined pm-empty-icon">error</span>
    <p><?= htmlspecialchars($page_error) ?></p>
    <a href="product_management.php" class="btn-teal" style="margin-top:16px">Back to Products</a>
  </div>
</div>
<?php else: ?>

<div class="pm-page">

  <?php if ($toast_type): ?>
  <div id="pm-toast" class="pm-toast pm-toast-<?= $toast_type ?>">
    <span class="material-symbols-outlined">
      <?= $toast_type === 'success' ? 'check_circle' : 'error' ?>
    </span>
    <span><?= htmlspecialchars($toast_msg) ?></span>
    <button class="pm-toast-close" onclick="document.getElementById('pm-toast').remove()">✕</button>
  </div>
  <?php endif; ?>

  <!-- Header -->
  <header class="pm-header">
    <div class="pm-header-left">
      <a href="product_management.php" class="pm-back-btn">
        <span class="material-symbols-outlined">arrow_back</span>
      </a>
      <span class="material-symbols-outlined pm-icon">storefront</span>
      <div>
        <h1><?= htmlspecialchars($page_title) ?></h1>
        <p class="pm-subtitle"><?= htmlspecialchars($page_subtitle) ?></p>
      </div>
    </div>
    <?php if (!$is_view): ?>
    <button class="btn-teal" type="submit" form="productForm" id="submitBtn">
      <span class="material-symbols-outlined"><?= $is_edit ? 'send' : 'check' ?></span>
      <?= $is_edit ? 'Resubmit for Approval' : 'Submit for Approval' ?>
    </button>
    <?php endif; ?>
  </header>

  <?php if ($is_view): ?>
  <div class="pm-view-banner">
    <span class="material-symbols-outlined">info</span>
    This product is <strong>sold out</strong>. You can view the details but cannot make changes.
  </div>
  <?php endif; ?>

  <form
    id="productForm"
    method="POST"
    enctype="multipart/form-data"
    action="seller_product_detail.php<?= $is_edit ? '?edit=' . $edit_id : '' ?>"
    onsubmit="return prepareSubmit(event)"
    novalidate
  >

    <main class="pd-container">

      <!-- ── Photos ──────────────────────────────────────────────────── -->
      <section class="pd-card">
        <h2>
          Photos
          <?php if (!$is_view): ?>
          <span class="pd-photo-count" id="photoCount">
            <?= count($existing_images) ?> / 10
          </span>
          <?php endif; ?>
        </h2>

        <?php if (isset($form_errors['images'])): ?>
        <span class="pd-field-error"><?= htmlspecialchars($form_errors['images']) ?></span>
        <?php endif; ?>

        <?php if (!$is_view): ?>
        <div
          class="pd-upload-box"
          id="uploadBox"
          onclick="document.getElementById('fileInput').click()"
          ondragover="handleDragOver(event)"
          ondragleave="handleDragLeave(event)"
          ondrop="handleDrop(event)"
        >
          <input
            type="file"
            id="fileInput"
            accept="image/*"
            multiple
            style="display:none"
            onchange="addFiles(this.files); this.value='';"
          >
          <span class="material-symbols-outlined pd-upload-icon">upload_file</span>
          <p class="pd-upload-title">Upload product photos</p>
          <p class="pd-upload-text">Drag and drop or click to browse. Add up to 10 photos.</p>
          <button
            class="btn-outline-teal"
            type="button"
            id="selectPhotosBtn"
            onclick="event.stopPropagation(); document.getElementById('fileInput').click();"
          >
            Select Photos
          </button>
        </div>
        <?php endif; ?>

        <div class="pd-img-grid <?= $is_view ? 'pd-view-grid' : '' ?>" id="previewGrid">
          <?php foreach ($existing_images as $i => $img): ?>
          <div
            class="pd-img-item <?= $i === 0 ? 'primary' : '' ?>"
            data-existing="1"
            data-url="<?= htmlspecialchars($img['image_url']) ?>"
          >
            <img src="<?= htmlspecialchars($img['image_url']) ?>" alt="Product image <?= $i + 1 ?>">
            <?php if ($i === 0): ?>
            <span class="pd-badge-cover">Cover</span>
            <?php endif; ?>
            <?php if (!$is_view): ?>
            <span class="pd-badge-saved">Saved</span>
            <div class="pd-img-actions">
              <?php if ($i !== 0): ?>
              <button type="button" class="pd-img-btn" title="Set as cover"
                      onclick="setPrimary(this.closest('.pd-img-item'))">
                <span class="material-symbols-outlined">star</span>
              </button>
              <?php endif; ?>
              <button type="button" class="pd-img-btn remove" title="Remove"
                      onclick="removeImage(this.closest('.pd-img-item'))">
                <span class="material-symbols-outlined">close</span>
              </button>
            </div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
      </section>

      <!-- ── Product Information ─────────────────────────────────────── -->
      <section class="pd-card">
        <h2>Product Information</h2>

        <div class="pd-form-group">
          <label>Name <?php if (!$is_view): ?><span class="pd-required">*</span><?php endif; ?></label>
          <input
            type="text"
            name="name"
            placeholder="What are you selling? (e.g., iPhone 13 Pro)"
            value="<?= htmlspecialchars($product['name'] ?? '') ?>"
            <?= $is_view ? 'readonly' : '' ?>
            class="<?= isset($form_errors['name']) ? 'pd-input-error' : '' ?> <?= $is_view ? 'pd-input-readonly' : '' ?>"
          >
          <?php if (isset($form_errors['name'])): ?>
          <span class="pd-field-error"><?= htmlspecialchars($form_errors['name']) ?></span>
          <?php endif; ?>
        </div>

        <div class="pd-grid-2">

          <!-- Main Category -->
          <div class="pd-form-group">
            <label>Category <?php if (!$is_view): ?><span class="pd-required">*</span><?php endif; ?></label>
            <?php if ($cat_error): ?>
            <span class="pd-cat-hint">⚠ <?= htmlspecialchars($cat_error) ?></span>
            <?php endif; ?>
            <select
              id="mainCategorySelect"
              name="main_category_id"
              <?= $is_view ? 'disabled' : '' ?>
              onchange="handleMainCategoryChange(this)"
              class="<?= (isset($form_errors['categoryId']) && !$sel_main_id) ? 'pd-input-error' : '' ?> <?= $is_view ? 'pd-input-readonly' : '' ?>"
            >
              <option value="">-- Select Main Category --</option>
              <?php foreach ($main_categories as $cat): ?>
              <option value="<?= $cat['id'] ?>" <?= $sel_main_id == $cat['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($cat['name']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Subcategory — name="sub_category_id"; disabled fields are not POSTed,
               so if no subs exist, $sub_id = 0 and $category_id falls back to $main_id -->
          <div class="pd-form-group">
            <label>Subcategory
              <?php if (!$is_view && !empty($sub_categories)): ?>
              <span class="pd-required"> *</span>
              <?php endif; ?>
            </label>
            <select
              id="subCategorySelect"
              name="sub_category_id"
              <?= $is_view ? 'disabled' : '' ?>
              class="<?= (isset($form_errors['categoryId']) && $sel_main_id && !empty($sub_categories) && !$sel_sub_id) ? 'pd-input-error' : '' ?> <?= $is_view ? 'pd-input-readonly' : '' ?>"
            >
              <option value="">
                <?= !$sel_main_id
                    ? 'Select a main category first'
                    : (empty($sub_categories) ? 'No subcategories available' : '-- Select Subcategory --') ?>
              </option>
              <?php foreach ($sub_categories as $sub): ?>
              <option value="<?= $sub['id'] ?>" <?= $sel_sub_id == $sub['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($sub['name']) ?>
              </option>
              <?php endforeach; ?>
            </select>
            <?php if (isset($form_errors['categoryId'])): ?>
            <span class="pd-field-error"><?= htmlspecialchars($form_errors['categoryId']) ?></span>
            <?php endif; ?>
          </div>

          <!-- Condition -->
          <div class="pd-form-group">
            <label>Condition <?php if (!$is_view): ?><span class="pd-required">*</span><?php endif; ?></label>
            <select
              name="condition"
              <?= $is_view ? 'disabled' : '' ?>
              class="<?= isset($form_errors['condition']) ? 'pd-input-error' : '' ?> <?= $is_view ? 'pd-input-readonly' : '' ?>"
            >
              <option value="">-- Select Condition --</option>
              <?php foreach ($conditions as $c): ?>
              <option value="<?= $c['value'] ?>"
                <?= ($product['condition'] ?? '') === $c['value'] ? 'selected' : '' ?>>
                <?= $c['label'] ?>
              </option>
              <?php endforeach; ?>
            </select>
            <?php if (isset($form_errors['condition'])): ?>
            <span class="pd-field-error"><?= htmlspecialchars($form_errors['condition']) ?></span>
            <?php endif; ?>
          </div>

          <!-- Stock -->
          <div class="pd-form-group">
            <label>Stock Quantity <?php if (!$is_view): ?><span class="pd-required">*</span><?php endif; ?></label>
            <input
              type="number"
              name="stock_quantity"
              min="1"
              step="1"
              placeholder="How many items?"
              value="<?= htmlspecialchars($product['stock_quantity'] ?? '') ?>"
              <?= $is_view ? 'readonly' : '' ?>
              onkeydown="if(event.key==='.') event.preventDefault();"
              class="<?= isset($form_errors['stock']) ? 'pd-input-error' : '' ?> <?= $is_view ? 'pd-input-readonly' : '' ?>"
            >
            <?php if (isset($form_errors['stock'])): ?>
            <span class="pd-field-error"><?= htmlspecialchars($form_errors['stock']) ?></span>
            <?php endif; ?>
          </div>

        </div><!-- .pd-grid-2 -->

        <!-- Description -->
        <div class="pd-form-group">
          <label>Description <?php if (!$is_view): ?><span class="pd-required">*</span><?php endif; ?></label>
          <textarea
            name="description"
            rows="4"
            placeholder="Describe the product — include any defects, accessories included, reason for selling…"
            <?= $is_view ? 'readonly' : '' ?>
            class="<?= isset($form_errors['description']) ? 'pd-input-error' : '' ?> <?= $is_view ? 'pd-input-readonly' : '' ?>"
          ><?= htmlspecialchars($product['description'] ?? '') ?></textarea>
          <?php if (isset($form_errors['description'])): ?>
          <span class="pd-field-error"><?= htmlspecialchars($form_errors['description']) ?></span>
          <?php endif; ?>
        </div>

      </section>

      <!-- ── Pricing ─────────────────────────────────────────────────── -->
      <section class="pd-card">
        <h2>Pricing</h2>
        <div class="pd-form-group" style="max-width:300px">
          <label>Price (RM) <?php if (!$is_view): ?><span class="pd-required">*</span><?php endif; ?></label>
          <input
            type="number"
            name="price"
            min="0.01"
            step="0.01"
            placeholder="0.00"
            value="<?= htmlspecialchars(isset($product['price']) ? number_format((float)$product['price'], 2, '.', '') : '') ?>"
            <?= $is_view ? 'readonly' : '' ?>
            onblur="if(this.value) this.value = parseFloat(this.value).toFixed(2);"
            class="<?= isset($form_errors['price']) ? 'pd-input-error' : '' ?> <?= $is_view ? 'pd-input-readonly' : '' ?>"
          >
          <?php if (isset($form_errors['price'])): ?>
          <span class="pd-field-error"><?= htmlspecialchars($form_errors['price']) ?></span>
          <?php endif; ?>
        </div>
      </section>

    </main>
  </form>
</div>

<!-- ── JavaScript ─────────────────────────────────────────────────────────── -->
<script>
const MAX_PHOTOS  = 10;
const IS_VIEW     = <?= $is_view ? 'true' : 'false' ?>;
const ALL_CATEGORIES = <?= json_encode(array_values($all_categories)) ?>;

// ── Category handler ────────────────────────────────────────────────────────
function handleMainCategoryChange(sel) {
  if (IS_VIEW) return;
  const mainId = parseInt(sel.value) || 0;
  const subSel = document.getElementById('subCategorySelect');
  const subs   = ALL_CATEGORIES.filter(c => c.parent_id == mainId);

  if (subs.length === 0) {
    subSel.disabled = true;
    subSel.innerHTML = '<option value="">No subcategories available</option>';
  } else {
    subSel.disabled = false;
    subSel.innerHTML = '<option value="">-- Select Subcategory --</option>';
    subs.forEach(s => {
      const opt = document.createElement('option');
      opt.value = s.id;
      opt.textContent = s.name;
      subSel.appendChild(opt);
    });
  }
}

// ── Image state (new files only; existing ones live in the DOM) ────────────
let newImages = []; // [{ file, previewUrl }]

function updatePhotoCount() {
  const total = document.querySelectorAll('#previewGrid .pd-img-item').length;
  const el    = document.getElementById('photoCount');
  if (el) el.textContent = total + ' / ' + MAX_PHOTOS;
  const btn = document.getElementById('selectPhotosBtn');
  if (btn) btn.disabled = (total >= MAX_PHOTOS);
}

function addFiles(files) {
  if (IS_VIEW) return;
  const grid      = document.getElementById('previewGrid');
  const current   = grid.querySelectorAll('.pd-img-item').length;
  const remaining = MAX_PHOTOS - current;
  if (remaining <= 0) { showToast('error', 'Maximum ' + MAX_PHOTOS + ' photos allowed.'); return; }

  const list = Array.from(files);
  if (list.length > remaining) showToast('error', 'Only ' + remaining + ' more photo(s) added.');
  list.slice(0, remaining).forEach((file, i) => {
    const url       = URL.createObjectURL(file);
    const idx       = newImages.length;
    const isPrimary = (current + i === 0);
    newImages.push({ file, previewUrl: url });

    const div = document.createElement('div');
    div.className      = 'pd-img-item' + (isPrimary ? ' primary' : '');
    div.dataset.newIdx = idx;
    div.innerHTML = `
      <img src="${url}" alt="Preview">
      ${isPrimary ? '<span class="pd-badge-cover">Cover</span>' : ''}
      <div class="pd-img-actions">
        ${!isPrimary
          ? `<button type="button" class="pd-img-btn" title="Set as cover"
               onclick="setPrimary(this.closest('.pd-img-item'))">
               <span class="material-symbols-outlined">star</span>
             </button>`
          : ''}
        <button type="button" class="pd-img-btn remove" title="Remove"
                onclick="removeImage(this.closest('.pd-img-item'))">
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>`;
    grid.appendChild(div);
  });
  updatePhotoCount();
}

function removeImage(item) {
  if (!item) return;

  if (item.dataset.existing) {
    item.remove();
  } else {
    const idx = parseInt(item.dataset.newIdx);
    if (!isNaN(idx)) newImages[idx] = null;
    item.remove();
  }

  reindexPrimary();
  updatePhotoCount();
}

function setPrimary(item) {
  if (IS_VIEW) return;
  document.getElementById('previewGrid').prepend(item);
  reindexPrimary();
}

function reindexPrimary() {
  const items = document.querySelectorAll('#previewGrid .pd-img-item');
  items.forEach((item, i) => {
    item.classList.toggle('primary', i === 0);

    // Cover badge
    const badge = item.querySelector('.pd-badge-cover');
    if (i === 0 && !badge) {
      const b = document.createElement('span');
      b.className = 'pd-badge-cover'; b.textContent = 'Cover';
      item.insertBefore(b, item.querySelector('img').nextSibling);
    } else if (i !== 0 && badge) badge.remove();

    // Star button
    const actions = item.querySelector('.pd-img-actions');
    if (!actions) return;
    const starBtn = actions.querySelector('.pd-img-btn:not(.remove)');
    if (i === 0 && starBtn) starBtn.remove();
    else if (i !== 0 && !starBtn) {
      const btn = document.createElement('button');
      btn.type = 'button'; btn.className = 'pd-img-btn'; btn.title = 'Set as cover';
      btn.innerHTML = '<span class="material-symbols-outlined">star</span>';
      btn.onclick = () => setPrimary(item);
      actions.insertBefore(btn, actions.firstChild);
    }
  });
}

// ── Drag & drop ─────────────────────────────────────────────────────────────
function handleDragOver(e) {
  if (!IS_VIEW) { e.preventDefault(); document.getElementById('uploadBox').classList.add('drag-over'); }
}
function handleDragLeave() { document.getElementById('uploadBox').classList.remove('drag-over'); }
function handleDrop(e) {
  e.preventDefault();
  document.getElementById('uploadBox').classList.remove('drag-over');
  if (!IS_VIEW) addFiles(e.dataTransfer.files);
}

// ── prepareSubmit — runs before every form submission ───────────────────────
// FIX: This function solves both critical bugs:
//   1. Writes kept_images[] inputs fresh from the current DOM (so removed
//      images are NOT included and will be deleted server-side)
//   2. Attaches newly added files via DataTransfer so PHP can receive them
function prepareSubmit(e) {
  if (IS_VIEW) { e.preventDefault(); return false; }

  const form = document.getElementById('productForm');
  const grid = document.getElementById('previewGrid');

  // Remove any kept_images[] inputs from a previous failed submission
  form.querySelectorAll('input[name="kept_images[]"]').forEach(el => el.remove());

  const items = grid.querySelectorAll('.pd-img-item');
  const orderedNewFiles = [];

  items.forEach(item => {
  // ❗只有还在 DOM 里的 existing 才保留
  if (item.dataset.existing === "1") {
    const inp = document.createElement('input');
    inp.type  = 'hidden';
    inp.name  = 'kept_images[]';
    inp.value = item.getAttribute('data-url'); // ✅ 确保取对值
    form.appendChild(inp);
  } else {
      // New file → collect for DataTransfer
      const idx = parseInt(item.dataset.newIdx);
      if (!isNaN(idx) && newImages[idx]) orderedNewFiles.push(newImages[idx].file);
    }
  });

  // Attach new files as a proper file input using DataTransfer
  const oldFi = document.getElementById('dynamicFileInput');
  if (oldFi) oldFi.remove();

  if (orderedNewFiles.length > 0) {
    const dt = new DataTransfer();
    orderedNewFiles.forEach(f => dt.items.add(f));
    const fi = document.createElement('input');
    fi.type     = 'file';
    fi.name     = 'images[]';
    fi.id       = 'dynamicFileInput';
    fi.multiple = true;
    fi.style.display = 'none';
    fi.files = dt.files;
    form.appendChild(fi);
  }

  return true;
}

// ── Toast ─────────────────────────────────────────────────────────────────
// FIX: was using class 'toast toast-success/error' which clashes with the
// global style.css .toast (black pill). Now uses .pm-toast .pm-toast-* instead.
function showToast(type, msg) {
  const existing = document.getElementById('dynamicToast');
  if (existing) existing.remove();
  const t = document.createElement('div');
  t.id        = 'dynamicToast';
  t.className = 'pm-toast pm-toast-' + type;
  t.innerHTML = `
    <span class="material-symbols-outlined">${type === 'success' ? 'check_circle' : 'error'}</span>
    <span>${msg}</span>
    <button class="pm-toast-close" onclick="this.parentElement.remove()">✕</button>`;
  document.body.appendChild(t);
  setTimeout(() => { if (t.parentElement) t.remove(); }, 4000);
}

// Auto-dismiss server-side toast
// FIX: was referencing id="pageToast" which didn't exist (the element used id="pm-toast")
const serverToast = document.getElementById('pm-toast');
if (serverToast) setTimeout(() => serverToast.remove(), 4000);

updatePhotoCount();
</script>

<?php endif; ?>
<?php require_once 'includes/footer.php'; ?>
